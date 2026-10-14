<?php

namespace Tests\Unit;

use App\Models\ImportItem;
use App\Services\DuplicateDetectionService;
use App\Services\ImportParserService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    private DuplicateDetectionService $duplicates;

    private ImportParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ImportParserService;
        $this->duplicates = new DuplicateDetectionService($this->parser);
    }

    public function test_exact_duplicates_point_to_the_first_item_in_line_order(): void
    {
        $items = $this->items([
            [1, 3, 'Adam Beyer', 'Teach Me (Original Mix)'],
            [2, 1, 'adam beyer', 'Teach Me'],
            [3, 2, 'Bicep', 'Glue'],
            [4, 5, 'ADAM BEYER', 'Teach Me'],
        ]);

        $this->assertSame([1 => 2, 4 => 2], $this->duplicates->exactDuplicates($items));
    }

    public function test_no_exact_duplicates_for_other_versions(): void
    {
        $items = $this->items([
            [1, 1, 'Bicep', 'Glue'],
            [2, 2, 'Bicep', 'Glue (Extended Mix)'],
        ]);

        $this->assertSame([], $this->duplicates->exactDuplicates($items));
    }

    public function test_similar_items_list_other_versions_and_swapped_artists_but_not_exact_duplicates(): void
    {
        $items = $this->items([
            [1, 1, 'Bicep', 'Glue'],
            [2, 2, 'Bicep', 'Glue (Extended Mix)'],
            [3, 3, 'Bicep', 'Glue'],
            [4, 4, 'Adam Beyer & Bart Skils', 'Your Mind'],
            [5, 5, 'Bart Skils, Adam Beyer', 'Your Mind'],
            [6, 6, 'Bonobo', 'Kerala'],
        ]);

        $this->assertSame([
            1 => [2],
            2 => [1, 3],
            3 => [2],
            4 => [5],
            5 => [4],
        ], $this->duplicates->similarItems($items));
    }

    /**
     * @return array<string, array{?string, string, ?string, string, bool}>
     */
    public static function typoProvider(): array
    {
        return [
            'typo in artist, same title' => ['Brunello', 'Ghost Dance (Original Mix)', 'Brunel', 'Ghost Dance (Origl Mix)', true],
            'typo in title, same artist' => ['Bicep', 'Glue', 'Bicep', 'Glu', true],
            'one letter in title (accepted false alarm)' => ['Adam Beyer', 'Teach Me', 'Adam Beyer', 'Reach Me', true],
            'short words differing by half' => ['Bicep', 'Hey', 'Bicep', 'Sky', false],
            'both parts differ' => ['Bicep', 'Glue', 'Bonobo', 'Glow', false],
            'typos in both parts' => ['Brunello', 'Ghost Dance', 'Brunel', 'Ghost Dence', false],
            'three edits' => ['Bicep', 'Kerala', 'Bicep', 'Kerplunk', false],
        ];
    }

    #[DataProvider('typoProvider')]
    public function test_small_typos_in_one_part_are_similar(?string $artist, string $title, ?string $otherArtist, string $otherTitle, bool $expectedSimilar): void
    {
        $items = $this->items([
            [1, 1, $artist, $title],
            [2, 2, $otherArtist, $otherTitle],
        ]);

        $expected = $expectedSimilar ? [1 => [2], 2 => [1]] : [];

        $this->assertSame($expected, $this->duplicates->similarItems($items));
    }

    /**
     * @param  list<array{int, int, ?string, string}>  $rows  id, line number, artist, title
     * @return Collection<int, ImportItem>
     */
    private function items(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row): ImportItem => (new ImportItem)->forceFill([
            'id' => $row[0],
            'line_number' => $row[1],
            'artist' => $row[2],
            'title' => $row[3],
            'normalized_key' => $this->parser->normalizedKey($row[2], $row[3]),
        ]));
    }
}
