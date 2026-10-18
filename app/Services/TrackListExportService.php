<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the track list of an import for download: TXT (`Artist - Title`, one per line, like a paste)
 * or CSV (all track fields + the selected YouTube URL). Every track is included, in line order,
 * regardless of Export ticks or links. Generated on request; nothing is stored.
 */
class TrackListExportService
{
    public const string FORMAT_TXT = 'txt';

    public const string FORMAT_CSV = 'csv';

    /**
     * @var list<string>
     */
    public const array FORMATS = [self::FORMAT_TXT, self::FORMAT_CSV];

    /**
     * @var list<string>
     */
    public const array CSV_HEADER = ['Artist', 'Title', 'Genre', 'BPM', 'Key', 'Camelot', 'Label', 'YouTube URL'];

    /**
     * UTF-8 byte order mark, so Excel reads Polish characters correctly (Numbers and Sheets ignore it).
     */
    private const string UTF8_BOM = "\u{FEFF}";

    public function content(ImportBatch $batch, string $format): string
    {
        return $format === self::FORMAT_CSV ? $this->csv($batch) : $this->txt($batch);
    }

    /**
     * `Artist - Title` per line (only the title when there is no artist), no trailing newline.
     */
    public function txt(ImportBatch $batch): string
    {
        return $this->items($batch)
            ->map(fn (ImportItem $item): string => filled($item->artist) ? "{$item->artist} - {$item->title}" : $item->title)
            ->implode("\n");
    }

    /**
     * Comma-separated with a header row, UTF-8 with BOM; empty cells for missing values.
     */
    public function csv(ImportBatch $batch): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, self::CSV_HEADER, escape: '');

        foreach ($this->items($batch) as $item) {
            fputcsv($handle, [
                $item->artist,
                $item->title,
                $item->genre,
                $item->bpm,
                $item->musical_key,
                $item->camelot_key,
                $item->label,
                $item->selected_youtube_url,
            ], escape: '');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return self::UTF8_BOM.$csv;
    }

    /**
     * @return Collection<int, ImportItem>
     */
    private function items(ImportBatch $batch): Collection
    {
        return $batch->items()->orderBy('line_number')->get();
    }
}
