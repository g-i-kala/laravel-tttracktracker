<?php

namespace App\Services;

use App\Ai\Agents\PlaylistTitleParser;
use App\Ai\Agents\TrackListParser;
use Illuminate\Support\Str;

/**
 * Parses a pasted track list with an AI model (Laravel AI SDK). Enabled when
 * an API key is set (Settings → AI or .env) and the monthly budget is not
 * reached; otherwise the line parser is used.
 */
class AiTrackListParserService
{
    /**
     * BPM values outside this range are treated as parsing noise.
     */
    public const int MIN_BPM = 40;

    public const int MAX_BPM = 300;

    public function __construct(private ImportParserService $lineParser, private AiSettingsService $aiSettings, private MusicalKeyService $musicalKeys) {}

    public function isEnabled(): bool
    {
        return filled($this->aiSettings->apiKey()) && ! $this->aiSettings->isBudgetReached();
    }

    /**
     * @return list<array{line_number: int, raw_line: string, artist: ?string, title: string, genre: ?string, bpm: ?int, musical_key: ?string, camelot_key: ?string, label: ?string, normalized_key: string, error_message: ?string}>
     */
    public function parse(string $rawInput): array
    {
        $response = $this->aiSettings->withApiKey(fn () => TrackListParser::make()->prompt(
            $rawInput,
            provider: $this->aiSettings->provider(),
            model: $this->aiSettings->model(),
            timeout: config('services.track_list_ai.timeout'),
        ));

        $tracks = collect($response['tracks'] ?? [])
            ->map(fn (array $track): array => [
                'artist' => $this->nullIfBlank($track['artist'] ?? null),
                'title' => Str::squish((string) ($track['title'] ?? '')),
                'genre' => $this->nullIfBlank($track['genre'] ?? null),
                'bpm' => $this->bpm($track['bpm'] ?? null),
                'key' => $this->nullIfBlank($track['key'] ?? null),
                'label' => $this->nullIfBlank($track['label'] ?? null),
                'source' => Str::squish((string) ($track['source'] ?? '')),
            ])
            ->filter(fn (array $track): bool => $track['title'] !== '')
            ->values();

        return $tracks->map(fn (array $track, int $index): array => [
            'line_number' => $index + 1,
            'raw_line' => $track['source'] !== '' ? $track['source'] : trim(($track['artist'] ?? '').' - '.$track['title'], ' -'),
            'artist' => $track['artist'],
            'title' => $track['title'],
            'genre' => $track['genre'],
            'bpm' => $track['bpm'],
            'musical_key' => $track['key'],
            'camelot_key' => $this->musicalKeys->toCamelot($track['key']),
            'label' => $track['label'],
            'normalized_key' => $this->lineParser->normalizedKey($track['artist'], $track['title']),
            'error_message' => $track['artist'] === null ? ImportParserService::ARTIST_NOT_DETECTED : null,
        ])->all();
    }

    /**
     * Parse YouTube video titles keyed by number. Results with unknown numbers or empty
     * titles are dropped, so the caller keeps its own value for those videos.
     *
     * @param  array<int, array{title: string, channel_title: ?string}>  $videos
     * @return array<int, array{artist: ?string, title: string}>
     */
    public function parsePlaylistTitles(array $videos): array
    {
        $prompt = collect($videos)
            ->map(fn (array $video, int $number): string => "{$number}. {$video['title']} [channel: ".($video['channel_title'] ?? 'unknown').']')
            ->implode("\n");

        $response = $this->aiSettings->withApiKey(fn () => PlaylistTitleParser::make()->prompt(
            $prompt,
            provider: $this->aiSettings->provider(),
            model: $this->aiSettings->model(),
            timeout: config('services.track_list_ai.timeout'),
        ));

        return collect($response['tracks'] ?? [])
            ->filter(fn (array $track): bool => is_int($track['number'] ?? null) && isset($videos[$track['number']]))
            ->mapWithKeys(fn (array $track): array => [$track['number'] => [
                'artist' => $this->nullIfBlank($track['artist'] ?? null),
                'title' => Str::squish((string) ($track['title'] ?? '')),
            ]])
            ->filter(fn (array $track): bool => $track['title'] !== '')
            ->all();
    }

    private function bpm(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $bpm = (int) round((float) $value);

        return $bpm >= self::MIN_BPM && $bpm <= self::MAX_BPM ? $bpm : null;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }
}
