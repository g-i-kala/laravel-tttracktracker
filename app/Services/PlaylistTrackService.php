<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Turns YouTube playlist videos into import items without AI: `X - Topic` channels give
 * the artist, other titles are split as `Artist - Title` after removing upload noise
 * like "(Official Video)". Each item keeps its video as the selected, exported link. Pure.
 */
class PlaylistTrackService
{
    /**
     * Auto-generated YouTube Music channels: "Artist - Topic".
     */
    private const string TOPIC_CHANNEL_PATTERN = '/^(.+?)\s+-\s+Topic$/u';

    /**
     * Bracketed upload noise; versions and remixes stay ("(Extended Mix)", "(Bicep Remix)").
     */
    private const string NOISE_PATTERN = '/\s*[(\[]\s*(?:official\s*)?(?:(?:hd|hq|4k)\s*)?(?:music\s*video|video\s*clip|lyrics?\s*video|lyrics?|visuali[sz]er|audio|video|clip(?:\s*officiel)?|hd|hq|4k|remastered\s*(?:\d{4})?|\d{4}\s*remaster(?:ed)?)\s*[)\]]/iu';

    /**
     * Leading "Premiere:" / "PREMIERE |" / "[Premiere]" on label and promo channels.
     */
    private const string PREMIERE_PATTERN = '/^\s*[\[(]?premiere[\])]?\s*[:|\-–—]?\s*/iu';

    public function __construct(private ImportParserService $parser) {}

    /**
     * Whether the video comes from an auto-generated Topic channel (artist known, title is the track).
     */
    public function isTopicChannel(?string $channelTitle): bool
    {
        return $channelTitle !== null && preg_match(self::TOPIC_CHANNEL_PATTERN, $channelTitle) === 1;
    }

    public function cleanTitle(string $title): string
    {
        $title = preg_replace(self::PREMIERE_PATTERN, '', $title);
        $title = preg_replace(self::NOISE_PATTERN, '', $title);

        return Str::squish($title);
    }

    /**
     * @param  list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, position: int}>  $videos
     * @return list<array{line_number: int, raw_line: string, artist: ?string, title: string, normalized_key: string, error_message: ?string, selected_youtube_url: string, selected_youtube_video_id: string, selected_youtube_title: string, selected_youtube_channel: ?string, include_in_export: bool}>
     */
    public function parsedLines(array $videos): array
    {
        return array_map(function (array $video, int $index): array {
            [$artist, $title] = $this->artistAndTitle($video);

            return [
                'line_number' => $index + 1,
                'raw_line' => $video['title'],
                'artist' => $artist,
                'title' => $title,
                'normalized_key' => $this->parser->normalizedKey($artist, $title),
                'error_message' => $artist === null ? ImportParserService::ARTIST_NOT_DETECTED : null,
                'selected_youtube_url' => $video['url'],
                'selected_youtube_video_id' => $video['video_id'],
                'selected_youtube_title' => $video['title'],
                'selected_youtube_channel' => $video['channel_title'],
                'include_in_export' => true,
            ];
        }, $videos, array_keys($videos));
    }

    /**
     * @param  array{title: string, channel_title: ?string}  $video
     * @return array{0: ?string, 1: string}
     */
    private function artistAndTitle(array $video): array
    {
        $title = $this->cleanTitle($video['title']);

        if ($title === '') {
            $title = Str::squish($video['title']);
        }

        if ($video['channel_title'] !== null && preg_match(self::TOPIC_CHANNEL_PATTERN, $video['channel_title'], $matches)) {
            return [Str::squish($matches[1]), $title];
        }

        $line = $this->parser->parse($title)[0] ?? null;

        return $line === null ? [null, $title] : [$line['artist'], $line['title']];
    }
}
