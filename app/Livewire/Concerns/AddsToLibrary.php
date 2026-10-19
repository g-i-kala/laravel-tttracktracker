<?php

namespace App\Livewire\Concerns;

use App\Models\ImportItem;
use App\Models\Track;
use App\Services\LibraryService;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

/**
 * "Add to library" modal shared by Parse Review and YouTube Results: choose the
 * tracks (all that are not in the library yet are preselected), optionally mark
 * them as wanted, then decide "Same track / New track" for possible matches
 * (other version, swapped artists; "New track" by default). The component
 * decides what happens next in `addedToLibrary()`.
 */
trait AddsToLibrary
{
    public bool $showLibraryModal = false;

    /**
     * Ids of the items to add.
     *
     * @var list<int|string>
     */
    public array $librarySelection = [];

    public bool $markAsWanted = false;

    /**
     * Second step of the modal: "Same track / New track" for possible matches.
     */
    public bool $libraryChoosingSimilar = false;

    /**
     * Item id => `new` or the id of the similar track that is the same track.
     *
     * @var array<int, string>
     */
    public array $similarChoices = [];

    abstract protected function addedToLibrary(string $notice): void;

    public function openLibraryModal(): void
    {
        $this->resetLibraryModal();
        $this->librarySelection = $this->batch->items()->whereNull('track_id')->orderBy('line_number')->pluck('id')->all();
        $this->showLibraryModal = true;
    }

    public function backToLibrarySelection(): void
    {
        $this->libraryChoosingSimilar = false;
    }

    /**
     * First call: go to the "Same track / New track" step when some selected
     * items have possible matches. Otherwise (or from that step) add the items.
     */
    public function addToLibrary(LibraryService $library): void
    {
        $items = $this->selectedLibraryItems();

        if ($items->isEmpty()) {
            $this->addError('librarySelection', 'Select at least one track.');

            return;
        }

        $matches = $library->similarMatches($items);

        if (! $this->libraryChoosingSimilar && $matches !== []) {
            $this->similarChoices = collect($matches)->map(fn (): string => 'new')->all();
            $this->libraryChoosingSimilar = true;

            return;
        }

        $sameTrackIds = collect($this->similarChoices)
            ->filter(fn (string $choice, int $itemId): bool => isset($matches[$itemId]) && $matches[$itemId]->contains('id', (int) $choice))
            ->map(fn (string $choice): int => (int) $choice)
            ->all();

        $result = $library->addItems($items, $this->markAsWanted, $sameTrackIds);
        $added = $result['created'] + $result['linked'];

        $this->resetLibraryModal();
        $this->addedToLibrary("Added {$added} ".str('track')->plural($added).' to the library: '
            ."{$result['created']} new, {$result['linked']} linked to tracks already there.");
    }

    /**
     * Possible matches of the selected items, for the second step.
     *
     * @return array<int, Collection<int, Track>>
     */
    #[Computed]
    public function librarySimilarMatches(): array
    {
        return $this->libraryChoosingSimilar
            ? app(LibraryService::class)->similarMatches($this->selectedLibraryItems())
            : [];
    }

    /**
     * @return Collection<int, ImportItem>
     */
    private function selectedLibraryItems(): Collection
    {
        return $this->batch->items()
            ->whereIn('id', collect($this->librarySelection)->map(fn (int|string $id): int => (int) $id)->all())
            ->orderBy('line_number')
            ->get();
    }

    private function resetLibraryModal(): void
    {
        $this->reset('showLibraryModal', 'librarySelection', 'markAsWanted', 'libraryChoosingSimilar', 'similarChoices');
        $this->resetErrorBag('librarySelection');
    }
}
