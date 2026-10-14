<?php

namespace App\Livewire\Imports;

use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\YoutubeSearchUrlService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * YouTube Results: one row per track with a link to the YouTube search
 * (no-key mode). Custom links, include-in-export and the TXT export come next.
 */
#[Title('YouTube Results')]
class Show extends Component
{
    public ImportBatch $batch;

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
