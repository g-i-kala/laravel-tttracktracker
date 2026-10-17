<?php

namespace App\Livewire;

use App\Enums\ImportBatchStatus;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Services\SupersimpleTxtExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard: imports needing attention (to review, tracks without a link,
 * outdated TXT), the 10 most recent imports and exports, and "How it works".
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public const int RECENT_LIMIT = 10;

    public bool $showHowItWorks = false;

    /**
     * Parsed imports not reviewed yet.
     *
     * @return Collection<int, ImportBatch>
     */
    #[Computed]
    public function toReview(): Collection
    {
        return ImportBatch::query()
            ->where('status', ImportBatchStatus::Parsed)
            ->withCount('items')
            ->latest()
            ->get();
    }

    /**
     * Searched or exported imports with tracks that have no YouTube link.
     *
     * @return Collection<int, ImportBatch>
     */
    #[Computed]
    public function missingLinks(): Collection
    {
        $withoutLink = fn (Builder $items): Builder => $items->whereNull('selected_youtube_url');

        return ImportBatch::query()
            ->whereIn('status', [ImportBatchStatus::Searched, ImportBatchStatus::Exported])
            ->whereHas('items', $withoutLink)
            ->withCount(['items', 'items as missing_links_count' => $withoutLink])
            ->latest()
            ->get();
    }

    /**
     * Imports whose latest TXT no longer matches their current links.
     *
     * @return Collection<int, ImportBatch>
     */
    #[Computed]
    public function outdatedExports(): Collection
    {
        $exports = app(SupersimpleTxtExportService::class);

        return ImportBatch::query()
            ->whereHas('exportFiles')
            ->latest()
            ->get()
            ->filter(fn (ImportBatch $batch): bool => $exports->isLatestExportOutdated($batch))
            ->values();
    }

    /**
     * @return Collection<int, ImportBatch>
     */
    #[Computed]
    public function recentImports(): Collection
    {
        return ImportBatch::query()->withCount('items')->latest()->latest('id')->limit(self::RECENT_LIMIT)->get();
    }

    /**
     * @return Collection<int, ExportFile>
     */
    #[Computed]
    public function recentExports(): Collection
    {
        return ExportFile::query()->with('importBatch')->latest('updated_at')->latest('id')->limit(self::RECENT_LIMIT)->get();
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
