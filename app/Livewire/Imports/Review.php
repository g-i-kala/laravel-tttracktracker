<?php

namespace App\Livewire\Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\AiTrackListParserService;
use App\Services\DuplicateDetectionService;
use App\Services\ImportBatchService;
use App\Services\ImportItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Parse Review: edit artist/title/genre of each parsed track, plus BPM, key and
 * label in a panel opened by clicking the row (all autosaved on blur), and
 * remove junk lines before searching YouTube. Exact duplicates open a modal
 * once right after parsing (later: banner); similar tracks are marked in the
 * rows. Save & close marks the batch
 * as reviewed and returns to the imports list. While AI parsing runs in the
 * background, the page polls until the items are ready.
 */
#[Title('Parse Review')]
class Review extends Component
{
    public ImportBatch $batch;

    /**
     * Editable values keyed by item id.
     *
     * @var array<int, array{artist: string, title: string, genre: string, bpm: string, musical_key: string, label: string}>
     */
    public array $items = [];

    public bool $showDuplicateModal = false;

    /**
     * Set by "Keep all": hides the duplicates banner until the page is reloaded.
     */
    public bool $duplicatesDismissed = false;

    public ?string $notice = null;

    public function mount(ImportBatch $batch): void
    {
        $this->batch = $batch;

        $this->loadItems();

        if (session('check_duplicates')) {
            $this->openDuplicateModalIfNeeded();
        }
    }

    /**
     * Polled while AI parsing runs; loads the items once the batch is parsed.
     */
    public function checkParsing(): void
    {
        $this->batch->refresh();

        if ($this->batch->status !== ImportBatchStatus::Parsing) {
            $this->refreshRows();
            $this->loadItems();
            $this->openDuplicateModalIfNeeded();
        }
    }

    public function retryAiParsing(ImportBatchService $importBatches, AiTrackListParserService $aiParser): void
    {
        if (! $aiParser->isEnabled()) {
            $this->addError('retry', 'AI parsing is off: no API key or the monthly AI budget is reached. Use the simple parser or check Settings → AI.');

            return;
        }

        $importBatches->retryAiParsing($this->batch);
    }

    public function useLineParser(ImportBatchService $importBatches): void
    {
        $importBatches->parseWithLineParser($this->batch);

        $this->batch->refresh();
        $this->refreshRows();
        $this->loadItems();
        $this->openDuplicateModalIfNeeded();
    }

    /**
     * "Remove duplicates" in the modal: delete exact duplicates, keep the first of each group.
     */
    public function removeDuplicates(ImportBatchService $importBatches): void
    {
        $removed = $importBatches->removeExactDuplicates($this->batch);

        $this->showDuplicateModal = false;
        $this->notice = "Removed {$removed} ".str('duplicate')->plural($removed).'.';
        $this->refreshRows();
        $this->loadItems();
    }

    /**
     * "Keep all" in the modal: keep every track and hide the banner for now.
     */
    public function keepAllDuplicates(): void
    {
        $this->showDuplicateModal = false;
        $this->duplicatesDismissed = true;
    }

    private function openDuplicateModalIfNeeded(): void
    {
        $this->showDuplicateModal = $this->batch->status !== ImportBatchStatus::Parsing
            && $this->exactDuplicates !== [];
    }

    private function refreshRows(): void
    {
        unset($this->rows, $this->exactDuplicates, $this->similarItems);
    }

    private function loadItems(): void
    {
        $this->items = $this->rows->mapWithKeys(fn (ImportItem $item): array => [$item->id => [
            'artist' => (string) $item->artist,
            'title' => $item->title,
            'genre' => (string) $item->genre,
            'bpm' => (string) $item->bpm,
            'musical_key' => (string) $item->musical_key,
            'label' => (string) $item->label,
        ]])->all();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'items.*.artist' => ['nullable', 'string', 'max:255'],
            'items.*.title' => ['required', 'string', 'max:255'],
            'items.*.genre' => ['nullable', 'string', 'max:255'],
            'items.*.bpm' => ['nullable', 'integer', 'between:'.AiTrackListParserService::MIN_BPM.','.AiTrackListParserService::MAX_BPM],
            'items.*.musical_key' => ['nullable', 'string', 'max:20'],
            'items.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'items.*.artist' => 'artist',
            'items.*.title' => 'title',
            'items.*.genre' => 'genre',
            'items.*.bpm' => 'BPM',
            'items.*.musical_key' => 'key',
            'items.*.label' => 'label',
        ];
    }

    /**
     * Autosave a single field after it loses focus. `$key` looks like `12.artist`.
     * Dispatches `item-saved` so the row can flash its "Saved" notice.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        $itemId = (int) explode('.', $key)[0];

        $this->validateOnly("items.{$key}");

        $item = $this->batch->items()->findOrFail($itemId);

        app(ImportItemService::class)->updateTrackDetails($item, $this->items[$itemId]);

        $this->dispatch('item-saved', id: $itemId);
        $this->refreshRows();
    }

    /**
     * Validate all fields, mark the batch as reviewed and go to the imports list.
     * Edits are already saved on blur; this only closes the review step.
     */
    public function saveAndClose(ImportBatchService $importBatches): void
    {
        $this->validate();

        $importBatches->markReviewed($this->batch);

        session()->flash('status', "Saved \"{$this->batch->name}\".");

        $this->redirectRoute('imports.index', navigate: true);
    }

    public function removeItem(int $itemId): void
    {
        $this->batch->items()->findOrFail($itemId)->delete();

        unset($this->items[$itemId]);
        $this->refreshRows();
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
     * @return array<int, int> duplicate item id => original item id
     */
    #[Computed]
    public function exactDuplicates(): array
    {
        return app(DuplicateDetectionService::class)->exactDuplicates($this->rows);
    }

    /**
     * @return array<int, list<int>> item id => ids of similar items
     */
    #[Computed]
    public function similarItems(): array
    {
        return app(DuplicateDetectionService::class)->similarItems($this->rows);
    }

    public function render(): View
    {
        return view('livewire.imports.review');
    }
}
