<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Services\ExportFilenameService;
use App\Services\TrackListExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the track list of an import (TXT or CSV), generated on request.
 */
class TrackListController extends Controller
{
    public function download(ImportBatch $batch, string $format, TrackListExportService $trackLists, ExportFilenameService $filenames): StreamedResponse
    {
        $content = $trackLists->content($batch, $format);
        $contentType = $format === TrackListExportService::FORMAT_CSV ? 'text/csv' : 'text/plain';

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            $filenames->trackList($batch, $format),
            ['Content-Type' => "{$contentType}; charset=UTF-8"],
        );
    }
}
