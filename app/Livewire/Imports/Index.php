<?php

namespace App\Livewire\Imports;

use App\Models\ImportBatch;
use App\Services\ImportBatchService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Imports list: all batches, newest first, with links to continue each one.
 * Imports can be deleted one by one or several at once (checkboxes), after a
 * confirmation modal that shows what will be removed.
 */
#[Title('Imports')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 25;

    /**
     * Ticked batch ids (kept across pages).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    /**
     * Batch ids waiting for confirmation in the delete modal.
     *
     * @var list<int>
     */
    public array $pendingDeleteIds = [];

    public bool $showDeleteModal = false;

    public ?string $notice = null;

    /**
     * Tick every import on this page, or untick them when all are ticked already.
     */
    public function toggleAllOnPage(): void
    {
        $pageIds = $this->batches->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $selected = array_map('strval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === []
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
    }

    /**
     * Open the delete modal for one import, or for the ticked ones when no id is given.
     */
    public function confirmDelete(?int $batchId = null): void
    {
        $this->pendingDeleteIds = $batchId !== null ? [$batchId] : array_map('intval', $this->selected);

        $this->showDeleteModal = $this->pendingDeleteIds !== [];
    }

    public function delete(ImportBatchService $importBatches): void
    {
        $deleted = $importBatches->deleteBatches($this->pendingDeleteIds);

        $this->selected = array_values(array_diff(array_map('intval', $this->selected), $this->pendingDeleteIds));
        $this->pendingDeleteIds = [];
        $this->showDeleteModal = false;
        $this->notice = "Deleted {$deleted} ".str('import')->plural($deleted).'.';

        unset($this->batches, $this->deleteSummary);

        if ($this->batches->isEmpty() && $this->getPage() > 1) {
            $this->setPage($this->batches->lastPage());
        }
    }

    /**
     * @return LengthAwarePaginator<int, ImportBatch>
     */
    #[Computed]
    public function batches(): LengthAwarePaginator
    {
        return ImportBatch::query()
            ->withCount('items')
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * @return array{imports: int, tracks: int, exports: int, name: ?string}
     */
    #[Computed]
    public function deleteSummary(): array
    {
        $summary = app(ImportBatchService::class)->deletionSummary($this->pendingDeleteIds);

        return [
            ...$summary,
            'name' => count($this->pendingDeleteIds) === 1 ? ImportBatch::query()->find($this->pendingDeleteIds[0])?->name : null,
        ];
    }

    public function render(): View
    {
        return view('livewire.imports.index');
    }
}
