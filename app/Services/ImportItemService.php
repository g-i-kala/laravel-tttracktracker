<?php

namespace App\Services;

use App\Models\ImportItem;
use Illuminate\Support\Str;

/**
 * Edits import items during Parse Review.
 */
class ImportItemService
{
    public function __construct(private ImportParserService $parser, private MusicalKeyService $musicalKeys) {}

    /**
     * Update artist, title, genre, BPM, key and label. Empty optional fields become
     * null, the normalized key and Camelot key are recomputed and the
     * missing-artist notice is set or cleared.
     *
     * @param  array{artist: ?string, title: string, genre: ?string, bpm: int|string|null, musical_key: ?string, label: ?string}  $attributes
     */
    public function updateTrackDetails(ImportItem $item, array $attributes): ImportItem
    {
        $artist = $this->nullIfBlank($attributes['artist']);
        $title = Str::squish($attributes['title']);
        $musicalKey = $this->nullIfBlank($attributes['musical_key']);
        $bpm = $this->nullIfBlank((string) $attributes['bpm']);

        $item->update([
            'artist' => $artist,
            'title' => $title,
            'genre' => $this->nullIfBlank($attributes['genre']),
            'bpm' => $bpm === null ? null : (int) $bpm,
            'musical_key' => $musicalKey,
            'camelot_key' => $this->musicalKeys->toCamelot($musicalKey),
            'label' => $this->nullIfBlank($attributes['label']),
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
