<?php

namespace App\Livewire\Imports;

use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\ImportItemService;
use App\Services\YoutubeSearchUrlService;
use App\Services\YoutubeUrlNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * YouTube Results: one row per track with a link to the YouTube search
 * (no-key mode), a field to paste the chosen video link (autosaved on paste
 * or blur; a valid link includes the track in the export) and an
 * include-in-export checkbox. The TXT export comes next.
 */
#[Title('YouTube Results')]
class Show extends Component
{
    public const string INVALID_LINK_MESSAGE = 'Paste a YouTube video link (youtube.com/watch?v=…, youtu.be/… or youtube.com/shorts/…).';

    public ImportBatch $batch;

    /**
     * Pasted link per item id, as shown in the row's field.
     *
     * @var array<int, string>
     */
    public array $links = [];

    public function mount(ImportBatch $batch): void
    {
        $this->batch = $batch;

        $this->links = $this->rows->mapWithKeys(fn (ImportItem $item): array => [
            $item->id => (string) $item->selected_youtube_url,
        ])->all();
    }

    /**
     * Autosave a pasted link. `$key` is the item id. Empty clears the link,
     * an invalid one shows an error and is not saved, a valid one is normalized.
     */
    public function updatedLinks(mixed $value, string|int $key): void
    {
        $itemId = (int) $key;
        $item = $this->batch->items()->findOrFail($itemId);
        $items = app(ImportItemService::class);

        $this->resetErrorBag("links.{$itemId}");

        if (trim((string) $value) === '') {
            $items->clearYoutubeLink($item);
        } else {
            $videoId = app(YoutubeUrlNormalizer::class)->videoId((string) $value);

            if ($videoId === null) {
                $this->addError("links.{$itemId}", self::INVALID_LINK_MESSAGE);

                return;
            }

            $items->selectCustomYoutubeLink($item, $videoId);
        }

        $this->links[$itemId] = (string) $item->selected_youtube_url;
        $this->dispatch('item-saved', id: $itemId);
        unset($this->rows);
    }

    public function toggleExport(int $itemId, ImportItemService $items): void
    {
        $items->toggleExport($this->batch->items()->findOrFail($itemId));

        unset($this->rows);
    }

    /**
     * @return Collection<int, ImportItem>
     */
    #[Computed]
    public function rows(): Collection
    {
        return $this->batch->items()->orderBy('line_number')->get();
    }

    /**
     * Search URL per item: the stored one, or one built now if the item has none yet.
     *
     * @return array<int, string> item id => YouTube search URL
     */
    #[Computed]
    public function searchUrls(): array
    {
        $searchUrls = app(YoutubeSearchUrlService::class);

        return $this->rows->mapWithKeys(fn (ImportItem $item): array => [
            $item->id => $item->youtube_search_url ?? $searchUrls->url($item->artist, $item->title),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.imports.show');
    }
}
