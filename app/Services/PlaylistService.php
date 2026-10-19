<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Support\Facades\DB;

/**
 * Playlists of library tracks: a manual order kept as positions 1..n, each
 * track at most once. Removing tracks or playlists never touches the library.
 */
class PlaylistService
{
    /**
     * Append tracks at the end in the given order; tracks already on the
     * playlist (or not in the library) are skipped.
     *
     * @param  list<int>  $trackIds
     * @return array{added: int, skipped: int}
     */
    public function addTracks(Playlist $playlist, array $trackIds): array
    {
        $trackIds = array_values(array_unique(array_map('intval', $trackIds)));

        return DB::transaction(function () use ($playlist, $trackIds): array {
            $existing = $playlist->tracks()->pluck('tracks.id')->all();
            $known = Track::query()->whereKey($trackIds)->pluck('id')->all();
            $toAdd = array_values(array_filter($trackIds, fn (int $id): bool => in_array($id, $known, true) && ! in_array($id, $existing, true)));

            $offset = count($existing);
            $playlist->tracks()->attach(collect($toAdd)->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $offset + $index + 1]])->all());
            $playlist->touch();

            return ['added' => count($toAdd), 'skipped' => count($trackIds) - count($toAdd)];
        });
    }

    /**
     * Move a track to a zero-based position (drag and drop).
     */
    public function moveTrack(Playlist $playlist, int $trackId, int $position): void
    {
        $order = $playlist->tracks()->pluck('tracks.id')->all();

        if (! in_array($trackId, $order, true)) {
            return;
        }

        $order = array_values(array_diff($order, [$trackId]));
        array_splice($order, max(0, min($position, count($order))), 0, [$trackId]);

        $this->saveOrder($playlist, $order);
    }

    /**
     * Save the given order of the playlist's tracks ("Apply this order").
     * Tracks missing from the list keep their relative order at the end.
     *
     * @param  list<int>  $trackIds
     */
    public function applyOrder(Playlist $playlist, array $trackIds): void
    {
        $current = $playlist->tracks()->pluck('tracks.id')->all();
        $ordered = array_values(array_intersect(array_map('intval', $trackIds), $current));

        $this->saveOrder($playlist, [...$ordered, ...array_values(array_diff($current, $ordered))]);
    }

    public function removeTrack(Playlist $playlist, int $trackId): void
    {
        DB::transaction(function () use ($playlist, $trackId): void {
            $playlist->tracks()->detach($trackId);
            $this->saveOrder($playlist, $playlist->tracks()->pluck('tracks.id')->all());
        });
    }

    /**
     * Merge: the current track's playlist rows go to the kept track at the
     * same position; where the kept track is already on the playlist, the
     * current track's row is dropped.
     */
    public function moveToTrack(Track $current, Track $kept): void
    {
        foreach ($current->playlists()->get() as $playlist) {
            $order = $playlist->tracks()->pluck('tracks.id')->all();
            $order = in_array($kept->id, $order, true)
                ? array_values(array_diff($order, [$current->id]))
                : array_map(fn (int $id): int => $id === $current->id ? $kept->id : $id, $order);

            $playlist->tracks()->detach($current->id);
            $this->saveOrder($playlist, $order);
        }
    }

    /**
     * Write positions 1..n in the given order (rows for missing tracks are added).
     *
     * @param  list<int>  $trackIds
     */
    private function saveOrder(Playlist $playlist, array $trackIds): void
    {
        DB::transaction(function () use ($playlist, $trackIds): void {
            $playlist->tracks()->sync(collect($trackIds)->values()->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $index + 1]])->all());
            $playlist->touch();
        });
    }
}
