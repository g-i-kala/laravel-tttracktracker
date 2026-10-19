<?php

namespace App\Services;

use App\Models\ImportItem;
use App\Models\Track;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * Fields compared in the merge modal, as column => label.
     *
     * @var array<string, string>
     */
    public const array MERGE_FIELDS = [
        'artist' => 'Artist',
        'title' => 'Title',
        'genre' => 'Genre',
        'bpm' => 'BPM',
        'musical_key' => 'Key',
        'label' => 'Label',
        'release_year' => 'Year',
        'youtube_url' => 'YouTube link',
    ];

    public function __construct(private ImportParserService $parser, private MusicalKeyService $musicalKeys, private TagService $tags, private PlaylistService $playlists) {}

    /**
     * Link the item to its track (already in the library) or the track with the
     * same normalized key, or create a new track. "Mark as wanted" marks the
     * track as wanted unless it is already playable offline.
     */
    public function addItem(ImportItem $item, bool $markAsWanted = false): Track
    {
        $track = $item->track_id !== null ? $item->track : $this->exactTrack($item);

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
     * Add many items in line order (Add to library). `$sameTrackIds` holds the
     * user's "Same track" choices for possible matches; other items go through
     * `addItem()`.
     *
     * @param  Collection<int, ImportItem>  $items
     * @param  array<int, int>  $sameTrackIds  item id => track id
     * @return array{created: int, linked: int}
     */
    public function addItems(Collection $items, bool $markAsWanted = false, array $sameTrackIds = []): array
    {
        $created = 0;
        $chosenTracks = Track::query()->findMany(array_values($sameTrackIds))->keyBy('id');

        foreach ($items->sortBy('line_number') as $item) {
            $chosenTrack = $chosenTracks->get($sameTrackIds[$item->id] ?? 0);

            $track = $chosenTrack !== null
                ? $this->linkToTrack($item, $chosenTrack, $markAsWanted)
                : $this->addItem($item, $markAsWanted);

            $created += $track->wasRecentlyCreated ? 1 : 0;
        }

        return ['created' => $created, 'linked' => $items->count() - $created];
    }

    /**
     * Possible matches for items that are not in the library yet and have no
     * exact match there, for the "Same track / New track" step. Two queries for
     * the whole list.
     *
     * @param  Collection<int, ImportItem>  $items
     * @return array<int, Collection<int, Track>> item id => similar tracks
     */
    public function similarMatches(Collection $items): array
    {
        $items = $items->whereNull('track_id');
        $exactKeys = Track::query()
            ->whereIn('normalized_key', $items->pluck('normalized_key')->unique()->values())
            ->pluck('normalized_key')
            ->flip();
        $items = $items->reject(fn (ImportItem $item): bool => $exactKeys->has($item->normalized_key));

        $similarKeys = $items->mapWithKeys(fn (ImportItem $item): array => [
            $item->id => $this->parser->similarKey($item->artist, $item->title),
        ]);
        $tracksBySimilarKey = Track::query()
            ->whereIn('similar_key', $similarKeys->unique()->values())
            ->orderBy('id')
            ->get()
            ->groupBy('similar_key');

        $matches = [];

        foreach ($items->sortBy('line_number') as $item) {
            $tracks = $tracksBySimilarKey->get($similarKeys[$item->id]);

            if ($tracks !== null) {
                $matches[$item->id] = $tracks->values();
            }
        }

        return $matches;
    }

    /**
     * Another library track that the given artist and title would make the
     * same track (same normalized key), if any.
     */
    public function conflictingTrack(Track $track, ?string $artist, string $title): ?Track
    {
        return Track::query()
            ->whereKeyNot($track->id)
            ->where('normalized_key', $this->parser->normalizedKey($this->nullIfBlank($artist), Str::squish($title)))
            ->first();
    }

    /**
     * Save the fields edited on the library screen. Empty optional fields
     * become null; the normalized, similar and Camelot keys are recomputed.
     * The YouTube link must already be normalized (or null). Wanted is
     * cleared by the model when the track is playable offline.
     *
     * @param  array{artist: ?string, title: string, genre: ?string, bpm: int|string|null, musical_key: ?string, label: ?string, release_year: int|string|null, notes: ?string, youtube_url: ?string, playable_offline: bool, wanted: bool}  $attributes
     */
    public function updateTrackDetails(Track $track, array $attributes): Track
    {
        $artist = $this->nullIfBlank($attributes['artist']);
        $title = Str::squish($attributes['title']);
        $musicalKey = $this->nullIfBlank($attributes['musical_key']);
        $bpm = $this->nullIfBlank((string) $attributes['bpm']);
        $releaseYear = $this->nullIfBlank((string) $attributes['release_year']);
        $notes = trim((string) $attributes['notes']);

        $track->update([
            'artist' => $artist,
            'title' => $title,
            'normalized_key' => $this->parser->normalizedKey($artist, $title),
            'similar_key' => $this->parser->similarKey($artist, $title),
            'genre' => $this->nullIfBlank($attributes['genre']),
            'bpm' => $bpm === null ? null : (int) $bpm,
            'musical_key' => $musicalKey,
            'camelot_key' => $this->musicalKeys->toCamelot($musicalKey),
            'label' => $this->nullIfBlank($attributes['label']),
            'release_year' => $releaseYear === null ? null : (int) $releaseYear,
            'notes' => $notes === '' ? null : $notes,
            'youtube_url' => $this->nullIfBlank($attributes['youtube_url']),
            'playable_offline' => (bool) $attributes['playable_offline'],
            'wanted' => (bool) $attributes['wanted'],
        ]);

        return $track;
    }

    /**
     * Compare the edited track with the existing track it collides with. A
     * field conflicts when both tracks have a different value; otherwise the
     * merged track gets the value that is there. `$currentOverrides` holds the
     * unsaved edits of the current track (artist, title).
     *
     * @param  array<string, mixed>  $currentOverrides
     * @return array<string, array{current: string|int|null, existing: string|int|null, conflict: bool}>
     */
    public function mergeComparison(Track $current, Track $existing, array $currentOverrides = []): array
    {
        $comparison = [];

        foreach (array_keys(self::MERGE_FIELDS) as $field) {
            $currentValue = $this->blankToNull(array_key_exists($field, $currentOverrides) ? $currentOverrides[$field] : $current->{$field});
            $existingValue = $this->blankToNull($existing->{$field});

            $comparison[$field] = [
                'current' => $currentValue,
                'existing' => $existingValue,
                'conflict' => $currentValue !== null && $existingValue !== null && (string) $currentValue !== (string) $existingValue,
            ];
        }

        return $comparison;
    }

    /**
     * Merge the current (edited) track into the existing one: conflicting
     * fields take the chosen side (`current` or `existing`, default existing),
     * empty fields are filled from the other track, POF and Wanted are kept if
     * either track has them (Wanted stays off when POF), notes are joined and
     * the import items, tags and playlist places move to the existing track. The current track is deleted.
     *
     * @param  array<string, mixed>  $currentOverrides
     * @param  array<string, string>  $choices  field => `current` | `existing`
     */
    public function mergeTracks(Track $current, Track $existing, array $currentOverrides = [], array $choices = []): Track
    {
        DB::transaction(function () use ($current, $existing, $currentOverrides, $choices): void {
            foreach ($this->mergeComparison($current, $existing, $currentOverrides) as $field => $values) {
                $existing->{$field} = $values['conflict']
                    ? (($choices[$field] ?? 'existing') === 'current' ? $values['current'] : $values['existing'])
                    : ($values['existing'] ?? $values['current']);
            }

            $existing->fill([
                'normalized_key' => $this->parser->normalizedKey($existing->artist, $existing->title),
                'similar_key' => $this->parser->similarKey($existing->artist, $existing->title),
                'camelot_key' => $this->musicalKeys->toCamelot($existing->musical_key),
                'rating' => $existing->rating ?? $current->rating,
                'notes' => collect([$existing->notes, $current->notes])->filter()->unique()->implode("\n\n") ?: null,
                'playable_offline' => $existing->playable_offline || $current->playable_offline,
                'wanted' => $existing->wanted || $current->wanted,
            ]);

            ImportItem::query()->where('track_id', $current->id)->update(['track_id' => $existing->id]);
            $existing->tags()->syncWithoutDetaching($current->tags()->pluck('tags.id')->all());
            $this->playlists->moveToTrack($current, $existing);
            $current->delete();
            $existing->save();
        });

        return $existing;
    }

    /**
     * What removing the tracks touches, for the confirmation modal.
     *
     * @param  list<int>  $trackIds
     * @return array{tracks: int, linkedItems: int}
     */
    public function deletionSummary(array $trackIds): array
    {
        return [
            'tracks' => Track::query()->whereKey($trackIds)->count(),
            'linkedItems' => ImportItem::query()->whereIn('track_id', $trackIds)->count(),
        ];
    }

    /**
     * Remove tracks from the library. Their import items are unlinked by the
     * database (`nullOnDelete`); the imports stay.
     *
     * @param  list<int>  $trackIds
     * @return int number of tracks removed
     */
    public function deleteTracks(array $trackIds): int
    {
        $deleted = Track::query()->whereKey($trackIds)->delete();
        $this->tags->deleteUnused();

        return $deleted;
    }

    /**
     * Set the rating (1–5); the same rating again clears it.
     */
    public function rate(Track $track, int $stars): Track
    {
        $track->update(['rating' => $track->rating === $stars ? null : $stars]);

        return $track;
    }

    /**
     * Mark tracks as playable offline (the user has the files), which also
     * clears wanted, or unmark them.
     *
     * @param  list<int>  $trackIds
     * @return int number of tracks updated
     */
    public function setPlayableOffline(array $trackIds, bool $playableOffline): int
    {
        return Track::query()->whereKey($trackIds)->update($playableOffline
            ? ['playable_offline' => true, 'wanted' => false]
            : ['playable_offline' => false]);
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

    private function nullIfBlank(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }

    private function blankToNull(mixed $value): string|int|null
    {
        if (is_string($value)) {
            $value = Str::squish($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }
}
