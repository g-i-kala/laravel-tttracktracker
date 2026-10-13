<?php

namespace App\Livewire\Imports;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Services\ImportBatchService;
use App\Services\ImportItemService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Parse Review: edit artist/title/genre of each parsed track (autosaved on blur)
 * and remove junk lines before searching YouTube. Save & close marks the batch
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
     * @var array<int, array{artist: string, title: string, genre: string}>
     */
    public array $items = [];

    public ?int $savedItemId = null;

    public function mount(ImportBatch $batch): void
    {
        $this->batch = $batch;

        $this->loadItems();
    }

    /**
     * Polled while AI parsing runs; loads the items once the batch is parsed.
     */
    public function checkParsing(): void
    {
        $this->batch->refresh();

        if ($this->batch->status !== ImportBatchStatus::Parsing) {
            unset($this->rows);
            $this->loadItems();
        }
    }

    public function retryAiParsing(ImportBatchService $importBatches): void
    {
        $importBatches->retryAiParsing($this->batch);
    }

    public function useLineParser(ImportBatchService $importBatches): void
    {
        $importBatches->parseWithLineParser($this->batch);

        $this->batch->refresh();
        unset($this->rows);
        $this->loadItems();
    }

    private function loadItems(): void
    {
        $this->items = $this->rows->mapWithKeys(fn (ImportItem $item): array => [$item->id => [
            'artist' => (string) $item->artist,
            'title' => $item->title,
            'genre' => (string) $item->genre,
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
        ];
    }

    /**
     * Autosave a single field after it loses focus. `$key` looks like `12.artist`.
     */
    public function updatedItems(mixed $value, string $key): void
    {
        $itemId = (int) explode('.', $key)[0];

        $this->validateOnly("items.{$key}");

        $item = $this->batch->items()->findOrFail($itemId);

        app(ImportItemService::class)->updateTrackDetails($item, $this->items[$itemId]);

        $this->savedItemId = $itemId;
        unset($this->rows);
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

        unset($this->items[$itemId], $this->rows);
    }

    /**
     * @return Collection<int, ImportItem>
     */
    #[Computed]
    public function rows(): Collection
    {
        return $this->batch->items()->orderBy('line_number')->get();
    }

    public function render(): View
    {
        return view('livewire.imports.review');
    }
}
