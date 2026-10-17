<?php

namespace App\Livewire\Exports;

use App\Models\ExportFile;
use App\Services\SupersimpleTxtExportService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Exports list: every export file (all versions), newest first, with its
 * import and a download link. Files can be deleted one by one or several at
 * once (checkboxes) after a confirmation modal; the imports stay.
 */
#[Title('Exports')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 25;

    /**
     * Ticked export ids (kept across pages).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    /**
     * Export ids waiting for confirmation in the delete modal.
     *
     * @var list<int>
     */
    public array $pendingDeleteIds = [];

    public bool $showDeleteModal = false;

    public ?string $notice = null;

    /**
     * Tick every export on this page, or untick them when all are ticked already.
     */
    public function toggleAllOnPage(): void
    {
        $pageIds = $this->exports->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $selected = array_map('strval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === []
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
    }

    /**
     * Open the delete modal for one export, or for the ticked ones when no id is given.
     */
    public function confirmDelete(?int $exportId = null): void
    {
        $this->pendingDeleteIds = $exportId !== null ? [$exportId] : array_map('intval', $this->selected);

        $this->showDeleteModal = $this->pendingDeleteIds !== [];
    }

    public function delete(SupersimpleTxtExportService $exports): void
    {
        $deleted = $exports->deleteExports($this->pendingDeleteIds);

        $this->selected = array_values(array_diff(array_map('intval', $this->selected), $this->pendingDeleteIds));
        $this->pendingDeleteIds = [];
        $this->showDeleteModal = false;
        $this->notice = "Deleted {$deleted} export ".str('file')->plural($deleted).'.';

        unset($this->exports, $this->latestVersions, $this->pendingExports);

        if ($this->exports->isEmpty() && $this->getPage() > 1) {
            $this->setPage($this->exports->lastPage());
        }
    }

    /**
     * Exports waiting for confirmation, for the modal.
     *
     * @return Collection<int, ExportFile>
     */
    #[Computed]
    public function pendingExports(): Collection
    {
        return ExportFile::query()->whereKey($this->pendingDeleteIds)->orderByDesc('updated_at')->get();
    }

    /**
     * @return LengthAwarePaginator<int, ExportFile>
     */
    #[Computed]
    public function exports(): LengthAwarePaginator
    {
        return ExportFile::query()
            ->with('importBatch')
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Highest version per import on the current page, to mark the latest file.
     *
     * @return array<int, int> import batch id => latest version
     */
    #[Computed]
    public function latestVersions(): array
    {
        return ExportFile::query()
            ->whereIn('import_batch_id', $this->exports->pluck('import_batch_id')->unique())
            ->groupBy('import_batch_id')
            ->selectRaw('import_batch_id, max(version) as latest_version')
            ->pluck('latest_version', 'import_batch_id')
            ->map(fn (mixed $version): int => (int) $version)
            ->all();
    }

    public function render(): View
    {
        return view('livewire.exports.index');
    }
}
