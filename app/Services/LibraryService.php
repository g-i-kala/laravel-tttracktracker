<?php

namespace App\Services;

use App\Models\ImportItem;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Adds import items to the music library. One track is one version: an item
 * with the same normalized key links to the existing track, otherwise a new
 * track is created. Possible matches (other version, swapped artists) share
 * the similar key and are left to the user.
 */
class LibraryService
{
    /**
     * Track fields filled from an item when the track has no value yet, as
     * track column => item column.
     *
     * @var array<string, string>
     */
    private const array FILLABLE_FROM_ITEM = [
        'genre' => 'genre',
        'bpm' => 'bpm',
        'musical_key' => 'musical_key',
        'camelot_key' => 'camelot_key',
        'label' => 'label',
        'youtube_url' => 'selected_youtube_url',
    ];

    public function __construct(private ImportParserService $parser) {}

    /**
     * Link the item to the track with the same normalized key, or create a new
     * track. "Mark as wanted" marks the track as wanted unless it is already
     * playable offline.
     */
    public function addItem(ImportItem $item, bool $markAsWanted = false): Track
    {
        $track = $this->exactTrack($item);

        return $track !== null
            ? $this->linkToTrack($item, $track, $markAsWanted)
            : $this->createTrack($item, $markAsWanted);
    }

    /**
     * The library track with the item's normalized key, if any.
     */
    public function exactTrack(ImportItem $item): ?Track
    {
        return Track::query()->where('normalized_key', $item->normalized_key)->first();
    }

    /**
     * Library tracks that may be the same track as the item (other version,
     * swapped artists): same similar key, other normalized key.
     *
     * @return Collection<int, Track>
     */
    public function similarTracks(ImportItem $item): Collection
    {
        return Track::query()
            ->where('similar_key', $this->parser->similarKey($item->artist, $item->title))
            ->where('normalized_key', '!=', $item->normalized_key)
            ->orderBy('id')
            ->get();
    }

    /**
     * Link the item to the given track (an exact match, or the user's choice
     * "Same track"). Empty track fields are filled from the item; fields with a
     * value are kept. Wanted is only ever switched on here, never off.
     */
    public function linkToTrack(ImportItem $item, Track $track, bool $markAsWanted = false): Track
    {
        DB::transaction(function () use ($item, $track, $markAsWanted): void {
            foreach (self::FILLABLE_FROM_ITEM as $trackColumn => $itemColumn) {
                if ($track->{$trackColumn} === null && $item->{$itemColumn} !== null) {
                    $track->{$trackColumn} = $item->{$itemColumn};
                }
            }

            if ($markAsWanted) {
                $track->wanted = true;
            }

            $track->save();
            $item->track()->associate($track)->save();
        });

        return $track;
    }

    private function createTrack(ImportItem $item, bool $markAsWanted): Track
    {
        return DB::transaction(function () use ($item, $markAsWanted): Track {
            $track = Track::create([
                'artist' => $item->artist,
                'title' => $item->title,
                'normalized_key' => $item->normalized_key,
                'similar_key' => $this->parser->similarKey($item->artist, $item->title),
                'genre' => $item->genre,
                'bpm' => $item->bpm,
                'musical_key' => $item->musical_key,
                'camelot_key' => $item->camelot_key,
                'label' => $item->label,
                'youtube_url' => $item->selected_youtube_url,
                'wanted' => $markAsWanted,
            ]);

            $item->track()->associate($track)->save();

            return $track;
        });
    }
}
