<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Builds YouTube search URLs for no-key mode. Pure: no database or network access.
 */
class YoutubeSearchUrlService
{
    private const string BASE_URL = 'https://www.youtube.com/results';

    /**
     * `(Original Mix)` / `[Original Mix]`: rarely in YouTube video titles, so it only narrows the results.
     */
    private const string ORIGINAL_MIX_PATTERN = '/[(\[]\s*original mix\s*[)\]]/iu';

    /**
     * Search query: artist and title without `(Original Mix)`. Other versions,
     * remixes and featured artists are kept. Without artist: the title only.
     */
    public function query(?string $artist, string $title): string
    {
        $title = preg_replace(self::ORIGINAL_MIX_PATTERN, ' ', $title);

        return Str::squish(($artist ?? '').' '.$title);
    }

    public function url(?string $artist, string $title): string
    {
        return self::BASE_URL.'?'.http_build_query(['search_query' => $this->query($artist, $title)]);
    }
}
