<?php

namespace App\Livewire\Exports;

use App\Models\ExportFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Exports list: every export file (all versions), newest first, with its
 * import and a download link.
 */
#[Title('Exports')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 25;

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
