<?php

namespace Tests\Unit;

use App\Models\ImportBatch;
use App\Services\ExportFilenameService;
use PHPUnit\Framework\TestCase;

class ExportFilenameServiceTest extends TestCase
{
    public function test_first_version_has_no_suffix_and_later_versions_do(): void
    {
        $batch = (new ImportBatch)->forceFill(['id' => 7, 'name' => 'YouTube Export 2026-10-14 08:49']);
        $filenames = new ExportFilenameService;

        $this->assertSame('youtube-export-2026-10-14-0849_youtube_links.txt', $filenames->supersimpleTxt($batch, 1));
        $this->assertSame('youtube-export-2026-10-14-0849_youtube_links_v2.txt', $filenames->supersimpleTxt($batch, 2));
        $this->assertSame('youtube-export-2026-10-14-0849_youtube_links_v3.txt', $filenames->supersimpleTxt($batch, 3));
    }

    public function test_name_without_sluggable_characters_falls_back_to_the_batch_id(): void
    {
        $batch = (new ImportBatch)->forceFill(['id' => 7, 'name' => '🎧🎧']);

        $this->assertSame('import-7_youtube_links.txt', (new ExportFilenameService)->supersimpleTxt($batch, 1));
    }
}
