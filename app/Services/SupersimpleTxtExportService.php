<?php

namespace App\Services;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportItemStatus;
use App\Models\ExportFile;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Generates the Supersimple TXT export: the selected YouTube URLs of the
 * included items, one per line, nothing else. Files live on the `local` disk
 * under `exports/{batch id}/`; each version has an `ExportFile` row.
 */
class SupersimpleTxtExportService
{
    public const string TYPE = 'supersimple_txt';

    public const string DISK = 'local';

    public function __construct(private ExportFilenameService $filenames) {}

    /**
     * Items that go into the file: included and with a selected link, in line order.
     *
     * @return Collection<int, ImportItem>
     */
    public function exportableItems(ImportBatch $batch): Collection
    {
        return $batch->items()
            ->where('include_in_export', true)
            ->whereNotNull('selected_youtube_url')
            ->orderBy('line_number')
            ->get();
    }

    /**
     * File content: URLs joined by newlines, without a trailing newline or empty lines.
     */
    public function content(ImportBatch $batch): string
    {
        return $this->exportableItems($batch)->pluck('selected_youtube_url')->implode("\n");
    }

    public function latestExport(ImportBatch $batch): ?ExportFile
    {
        return $batch->exportFiles()->where('type', self::TYPE)->orderByDesc('version')->first();
    }

    /**
     * Write the file as a new version, or overwrite the latest version (same
     * filename and version; a new version when there is none yet). Exported
     * items and the batch get the `exported` status.
     */
    public function export(ImportBatch $batch, bool $overwriteLatest = false): ExportFile
    {
        return DB::transaction(function () use ($batch, $overwriteLatest): ExportFile {
            $items = $this->exportableItems($batch);
            $content = $items->pluck('selected_youtube_url')->implode("\n");
            $latest = $this->latestExport($batch);

            if ($overwriteLatest && $latest !== null) {
                $exportFile = $latest;
                $exportFile->update(['items_count' => $items->count()]);
                $exportFile->touch();
            } else {
                $version = ($latest?->version ?? 0) + 1;
                $filename = $this->filenames->supersimpleTxt($batch, $version);

                $exportFile = $batch->exportFiles()->create([
                    'type' => self::TYPE,
                    'filename' => $filename,
                    'path' => "exports/{$batch->id}/{$filename}",
                    'version' => $version,
                    'items_count' => $items->count(),
                ]);
            }

            Storage::disk(self::DISK)->put($exportFile->path, $content);

            $batch->items()->whereKey($items->modelKeys())->update(['status' => ImportItemStatus::Exported]);
            $batch->update(['status' => ImportBatchStatus::Exported]);

            return $exportFile;
        });
    }
}
