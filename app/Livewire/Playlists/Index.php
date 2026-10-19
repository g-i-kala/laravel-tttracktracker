<?php

namespace App\Livewire\Playlists;

use App\Models\Playlist;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Playlists list: name, track count, last change. Create a playlist in a
 * modal; delete one after confirmation (its tracks stay in the library).
 */
#[Title('Playlists')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 25;

    public bool $showCreateModal = false;

    public string $name = '';

    public string $notes = '';

    public ?int $pendingDeleteId = null;

    public bool $showDeleteModal = false;

    public ?string $notice = null;

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $playlist = Playlist::create([
            'name' => trim($validated['name']),
            'notes' => trim((string) $validated['notes']) ?: null,
        ]);

        $this->redirectRoute('playlists.show', $playlist, navigate: true);
    }

    public function confirmDelete(int $playlistId): void
    {
        $this->pendingDeleteId = Playlist::query()->findOrFail($playlistId)->id;
        $this->showDeleteModal = true;
    }

    public function delete(): void
    {
        $playlist = Playlist::query()->find($this->pendingDeleteId);

        if ($playlist !== null) {
            $playlist->delete();
            $this->notice = "Deleted the playlist “{$playlist->name}”. Its tracks stay in the library.";
        }

        $this->reset('pendingDeleteId', 'showDeleteModal');
        unset($this->playlists);

        if ($this->playlists->isEmpty() && $this->getPage() > 1) {
            $this->setPage($this->playlists->lastPage());
        }
    }

    #[Computed]
    public function pendingDelete(): ?Playlist
    {
        return $this->pendingDeleteId === null ? null : Playlist::query()->withCount('tracks')->find($this->pendingDeleteId);
    }

    /**
     * @return LengthAwarePaginator<int, Playlist>
     */
    #[Computed]
    public function playlists(): LengthAwarePaginator
    {
        return Playlist::query()
            ->withCount('tracks')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    public function render(): View
    {
        return view('livewire.playlists.index');
    }
}
