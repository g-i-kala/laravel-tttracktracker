<?php

namespace App\Services;

/**
 * Recognizes YouTube video links (`watch?v=`, `youtu.be/`, `shorts/`, also
 * `embed/` and `live/`, on www/m/music hosts, with or without scheme) and
 * normalizes them to `https://www.youtube.com/watch?v=ID`. Extra parameters
 * (playlist, timestamp, share id) are dropped. Pure: no network access.
 */
class YoutubeUrlNormalizer
{
    private const string VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    /**
     * @var list<string>
     */
    private const array YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'];

    /**
     * @var list<string>
     */
    private const array PATH_PREFIXES = ['shorts', 'embed', 'live'];

    public function videoId(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = "https://{$url}";
        }

        $parts = parse_url($url);

        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower($parts['host'] ?? '');
        $segments = array_values(array_filter(explode('/', $parts['path'] ?? '')));

        $videoId = match (true) {
            $host === 'youtu.be' => $segments[0] ?? null,
            in_array($host, self::YOUTUBE_HOSTS, true) => $this->videoIdFromYoutubeUrl($segments, $parts['query'] ?? ''),
            default => null,
        };

        return $videoId !== null && preg_match(self::VIDEO_ID_PATTERN, $videoId) ? $videoId : null;
    }

    /**
     * The normalized watch URL, or null when the link is not a YouTube video link.
     */
    public function normalize(string $url): ?string
    {
        $videoId = $this->videoId($url);

        return $videoId === null ? null : "https://www.youtube.com/watch?v={$videoId}";
    }

    /**
     * @param  list<string>  $segments
     */
    private function videoIdFromYoutubeUrl(array $segments, string $query): ?string
    {
        if (($segments[0] ?? null) === 'watch') {
            parse_str($query, $parameters);

            return is_string($parameters['v'] ?? null) ? $parameters['v'] : null;
        }

        if (in_array($segments[0] ?? null, self::PATH_PREFIXES, true)) {
            return $segments[1] ?? null;
        }

        return null;
    }
}
