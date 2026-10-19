<?php

namespace App\Livewire\Playlists;

use App\Models\Playlist;
use App\Models\Track;
use App\Services\PlaylistService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Playlist screen: the tracks in their manual order (drag and drop). Sorting
 * by a column only changes the view (dragging is off then); "Apply this
 * order" saves it as the manual order. Tracks can be removed from the
 * playlist (they stay in the library); name and notes are edited in a modal.
 */
class Show extends Component
{
    /**
     * Columns the view can be sorted by (subset of `Track::SORTS`).
     *
     * @var list<string>
     */
    public const array SORTABLE = ['artist', 'title', 'bpm', 'camelot', 'year', 'rating'];

    public Playlist $playlist;

    /**
     * Null = the manual order.
     */
    public ?string $sort = null;

    public string $direction = 'asc';

    public bool $showEditModal = false;

    public string $name = '';

    public string $notes = '';

    public ?string $notice = null;

    public function mount(Playlist $playlist): void
    {
        $this->playlist = $playlist;
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, self::SORTABLE, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function manualOrder(): void
    {
        $this->reset('sort', 'direction');
    }

    /**
     * `wire:sort` handler: move a track to a zero-based position.
     */
    public function moveTrack(int|string $trackId, int $position, PlaylistService $playlists): void
    {
        if ($this->sort !== null) {
            return;
        }

        $playlists->moveTrack($this->playlist, (int) $trackId, $position);
        unset($this->tracks);
    }

    /**
     * Save the sorted view as the manual order.
     */
    public function applyOrder(PlaylistService $playlists): void
    {
        if ($this->sort === null) {
            return;
        }

        $playlists->applyOrder($this->playlist, $this->tracks->modelKeys());

        $this->reset('sort', 'direction');
        $this->notice = 'Saved this order as the playlist order.';
        unset($this->tracks);
    }

    public function removeTrack(int $trackId, PlaylistService $playlists): void
    {
        $playlists->removeTrack($this->playlist, $trackId);

        $this->notice = 'Removed the track from the playlist. It stays in the library.';
        unset($this->tracks);
    }

    public function openEditModal(): void
    {
        $this->name = $this->playlist->name;
        $this->notes = (string) $this->playlist->notes;
        $this->resetErrorBag();
        $this->showEditModal = true;
    }

    public function saveDetails(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->playlist->update([
            'name' => trim($validated['name']),
            'notes' => trim((string) $validated['notes']) ?: null,
        ]);

        $this->showEditModal = false;
    }

    /**
     * @return Collection<int, Track>
     */
    #[Computed]
    public function tracks(): Collection
    {
        $query = $this->playlist->tracks()->with('tags');

        if ($this->sort !== null && in_array($this->sort, self::SORTABLE, true)) {
            $direction = $this->direction === 'desc' ? 'desc' : 'asc';

            $query->reorder()
                ->orderByRaw(Track::SORTS[$this->sort]." {$direction} nulls last")
                ->orderBy('playlist_track.position');
        }

        return $query->get();
    }

    public function render(): View
    {
        return view('livewire.playlists.show')->title($this->playlist->name);
    }
}
