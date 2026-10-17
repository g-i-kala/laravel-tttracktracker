<?php

namespace App\Services;

use App\Models\ImportBatch;
use Illuminate\Support\Str;

/**
 * Builds export filenames: `{slug}_youtube_links.txt`, then `_v2`, `_v3`, … Pure.
 */
class ExportFilenameService
{
    public function supersimpleTxt(ImportBatch $batch, int $version): string
    {
        $slug = Str::slug($batch->name) ?: "import-{$batch->id}";
        $suffix = $version > 1 ? "_v{$version}" : '';

        return "{$slug}_youtube_links{$suffix}.txt";
    }
}
