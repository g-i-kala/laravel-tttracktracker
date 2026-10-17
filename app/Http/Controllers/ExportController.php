<?php

namespace App\Http\Controllers;

use App\Models\ExportFile;
use App\Services\SupersimpleTxtExportService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads export files. The only controller: a plain link that works from
 * any screen (YouTube Results now, the exports list later).
 */
class ExportController extends Controller
{
    public function download(ExportFile $export): StreamedResponse
    {
        $disk = Storage::disk(SupersimpleTxtExportService::DISK);

        abort_unless($disk->exists($export->path), 404);

        return $disk->download($export->path, $export->filename, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
