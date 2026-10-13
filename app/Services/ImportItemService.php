<?php

namespace App\Services;

use App\Models\ImportItem;
use Illuminate\Support\Str;

/**
 * Edits import items during Parse Review.
 */
class ImportItemService
{
    public function __construct(private ImportParserService $parser) {}

    /**
     * Update artist, title and genre. Empty artist/genre become null, the
     * normalized key is recomputed and the missing-artist notice is set or cleared.
     *
     * @param  array{artist: ?string, title: string, genre: ?string}  $attributes
     */
    public function updateTrackDetails(ImportItem $item, array $attributes): ImportItem
    {
        $artist = $this->nullIfBlank($attributes['artist']);
        $title = Str::squish($attributes['title']);

        $item->update([
            'artist' => $artist,
            'title' => $title,
            'genre' => $this->nullIfBlank($attributes['genre']),
            'normalized_key' => $this->parser->normalizedKey($artist, $title),
            'error_message' => $artist === null ? ImportParserService::ARTIST_NOT_DETECTED : null,
        ]);

        return $item;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }
}
