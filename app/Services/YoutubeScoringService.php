<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Scores YouTube search results (0–100) against a track, using only the video title,
 * channel and description (no duration lookup). Pure: no database or network access.
 */
class YoutubeScoringService
{
    public const int TITLE_POINTS = 40;

    public const int ARTIST_POINTS = 30;

    public const int CHANNEL_POINTS = 15;

    public const int OFFICIAL_POINTS = 5;

    public const int VERSION_MATCH_POINTS = 10;

    public const int VERSION_MISMATCH_PENALTY = 25;

    public const int UNWANTED_KEYWORD_PENALTY = 30;

    /**
     * Unwanted kinds of videos. Each one found in the video title costs the penalty once,
     * unless the track itself contains it (e.g. a track called "Live Forever").
     *
     * @var array<string, list<string>>
     */
    private const array UNWANTED_KEYWORDS = [
        'live' => ['live'],
        'set' => ['set', 'dj set'],
        'podcast' => ['podcast'],
        'lyrics' => ['lyrics', 'lyric'],
        'sped up' => ['sped up', 'speed up'],
        'slowed' => ['slowed'],
        'nightcore' => ['nightcore'],
        'cover' => ['cover'],
        'reaction' => ['reaction', 'reacts', 'reacting'],
        'tutorial' => ['tutorial'],
        'karaoke' => ['karaoke'],
        '8d' => ['8d'],
        '1 hour' => ['1 hour', '1hour', '10 hours'],
    ];

    /**
     * Words marking another producer's version of a track.
     */
    private const array REMIX_MARKERS = ['remix', 'rmx', 'rework', 'bootleg', 'vip', 'flip', 'refix'];

    /**
     * Words that say nothing about which version it is.
     */
    private const array GENERIC_VERSION_WORDS = ['mix', 'version', 'original'];

    /**
     * Words joining artists in an artist field.
     */
    private const array ARTIST_CONNECTORS = ['and', 'x', 'vs', 'feat', 'ft', 'featuring', 'with'];

    /**
     * Channel name words ignored when comparing a channel with an artist or label
     * (a glued-on `vevo`, as in "OasisVEVO", is removed too).
     */
    private const array CHANNEL_SUFFIXES = ['topic', 'vevo', 'official', 'music', 'records', 'recordings'];

    private const string BRACKETED_PATTERN = '/[(\[][^)\]]*[)\]]/u';

    private const string FEATURING_PATTERN = '/[(\[]\s*(?:feat\.?|ft\.?|featuring)\s[^)\]]*[)\]]|\b(?:feat\.?|ft\.?|featuring)\s.*?(?=[(\[]|$)/iu';

    /**
     * Score candidates and return the best ones, highest score first (API order on ties).
     *
     * @param  list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string}>  $results
     * @return list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string, score: int}>
     */
    public function rank(?string $artist, string $title, ?string $label, array $results, int $limit): array
    {
        return collect($results)
            ->map(fn (array $result): array => [...$result, 'score' => $this->score($artist, $title, $label, $result)])
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  array{title: string, channel_title?: ?string, description?: ?string}  $result
     */
    public function score(?string $artist, string $title, ?string $label, array $result): int
    {
        $videoTitle = $this->normalize($result['title']);
        $channel = $this->normalize($result['channel_title'] ?? '');
        $trackText = $this->normalize(($artist ?? '').' '.$title);

        $score = $this->titlePoints($title, $videoTitle)
            + $this->artistPoints($artist, $videoTitle.' '.$channel)
            + $this->channelPoints($artist, $label, $channel)
            + $this->officialPoints($videoTitle, $this->normalize($result['description'] ?? ''))
            + $this->versionPoints($title, $videoTitle)
            - $this->unwantedKeywordPenalty($videoTitle, $trackText);

        return max(0, min(100, $score));
    }

    /**
     * Share of the base title's words (without version / featured artists) found in the video title.
     */
    private function titlePoints(string $title, string $videoTitle): int
    {
        $baseTitle = $this->tokens(preg_replace(self::BRACKETED_PATTERN, ' ', $this->withoutFeaturing($title)));

        if ($baseTitle === []) {
            $baseTitle = $this->tokens($title);
        }

        return (int) round(self::TITLE_POINTS * $this->matchedShare($baseTitle, $videoTitle));
    }

    /**
     * Share of the artists' words found in the video title or channel name.
     */
    private function artistPoints(?string $artist, string $videoText): int
    {
        $artistWords = array_values(array_diff($this->tokens($artist ?? ''), self::ARTIST_CONNECTORS));

        if ($artistWords === []) {
            return 0;
        }

        return (int) round(self::ARTIST_POINTS * $this->matchedShare($artistWords, $videoText));
    }

    /**
     * The channel is one of the artists (also "Artist - Topic", "ArtistVEVO") or the track's label.
     */
    private function channelPoints(?string $artist, ?string $label, string $channel): int
    {
        $channelName = $this->withoutChannelSuffixes($channel);

        if ($channelName === '') {
            return 0;
        }

        $names = collect(preg_split('/\s*(?:,|&|\band\b|\bx\b|\bvs\.?|\bfeat\.?|\bft\.?|\bfeaturing\b)\s*/iu', $artist ?? ''))
            ->push($label ?? '')
            ->map(fn (string $name): string => $this->withoutChannelSuffixes($this->normalize($name)))
            ->filter();

        $matches = $names->contains(fn (string $name): bool => $name === $channelName
            || str_replace(' ', '', $name) === str_replace(' ', '', $channelName));

        return $matches ? self::CHANNEL_POINTS : 0;
    }

    private function officialPoints(string $videoTitle, string $description): int
    {
        $isOfficial = $this->containsPhrase($videoTitle, 'official audio')
            || $this->containsPhrase($videoTitle, 'official video')
            || $this->containsPhrase($videoTitle, 'official music video')
            || $this->containsPhrase($description, 'provided to youtube');

        return $isOfficial ? self::OFFICIAL_POINTS : 0;
    }

    /**
     * The requested version (e.g. "Extended Mix", "Hammer Remix") found in the video title: bonus.
     * A remix when the original was requested, or the wrong remix / no remix when one was requested: penalty.
     */
    private function versionPoints(string $title, string $videoTitle): int
    {
        preg_match_all(self::BRACKETED_PATTERN, $this->withoutFeaturing($title), $matches);
        $versionWords = array_values(array_diff($this->tokens(implode(' ', $matches[0])), self::GENERIC_VERSION_WORDS));
        $trackIsRemix = array_intersect($versionWords, self::REMIX_MARKERS) !== [];

        if ($versionWords !== [] && $this->matchedShare($versionWords, $videoTitle) === 1.0) {
            return self::VERSION_MATCH_POINTS;
        }

        if ($trackIsRemix) {
            return -self::VERSION_MISMATCH_PENALTY;
        }

        $videoIsRemix = array_intersect($this->tokens($videoTitle), self::REMIX_MARKERS) !== [];
        $titleHasRemixWord = array_intersect($this->tokens($title), self::REMIX_MARKERS) !== [];

        return $videoIsRemix && ! $titleHasRemixWord ? -self::VERSION_MISMATCH_PENALTY : 0;
    }

    private function unwantedKeywordPenalty(string $videoTitle, string $trackText): int
    {
        $found = collect(self::UNWANTED_KEYWORDS)->filter(
            fn (array $phrases): bool => collect($phrases)->contains(
                fn (string $phrase): bool => $this->containsPhrase($videoTitle, $phrase) && ! $this->containsPhrase($trackText, $phrase)
            )
        );

        return $found->count() * self::UNWANTED_KEYWORD_PENALTY;
    }

    /**
     * Lowercase ASCII words separated by single spaces; `&` becomes `and`.
     */
    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = str_replace('&', ' and ', $text);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $normalized = $this->normalize($text);

        return $normalized === '' ? [] : array_values(array_unique(explode(' ', $normalized)));
    }

    /**
     * @param  list<string>  $words
     */
    private function matchedShare(array $words, string $normalizedText): float
    {
        if ($words === []) {
            return 0.0;
        }

        $textWords = explode(' ', $normalizedText);

        return count(array_intersect($words, $textWords)) / count($words);
    }

    private function containsPhrase(string $normalizedText, string $phrase): bool
    {
        return str_contains(' '.$normalizedText.' ', ' '.$phrase.' ');
    }

    private function withoutFeaturing(string $title): string
    {
        return preg_replace(self::FEATURING_PATTERN, ' ', $title);
    }

    private function withoutChannelSuffixes(string $normalizedName): string
    {
        $words = array_diff(explode(' ', $normalizedName), self::CHANNEL_SUFFIXES);

        return trim(preg_replace('/vevo$/', '', implode(' ', $words)));
    }
}
