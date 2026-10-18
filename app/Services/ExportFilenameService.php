<?php

namespace App\Services;

use App\Models\ImportBatch;
use Illuminate\Support\Str;

/**
 * Builds export filenames: `{slug}_youtube_links.txt`, then `_v2`, `_v3`, …, and track lists
 * `{slug}_tracks.txt` / `.csv`. Pure.
 */
class ExportFilenameService
{
    public function supersimpleTxt(ImportBatch $batch, int $version): string
    {
        $slug = $this->slug($batch);
        $suffix = $version > 1 ? "_v{$version}" : '';

        return "{$slug}_youtube_links{$suffix}.txt";
    }

    public function trackList(ImportBatch $batch, string $extension): string
    {
        return "{$this->slug($batch)}_tracks.{$extension}";
    }

    private function slug(ImportBatch $batch): string
    {
        return Str::slug($batch->name) ?: "import-{$batch->id}";
    }
}
