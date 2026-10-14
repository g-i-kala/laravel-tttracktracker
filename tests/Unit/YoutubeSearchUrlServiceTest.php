<?php

namespace Tests\Unit;

use App\Services\YoutubeSearchUrlService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YoutubeSearchUrlServiceTest extends TestCase
{
    private YoutubeSearchUrlService $searchUrls;

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchUrls = new YoutubeSearchUrlService;
    }

    /**
     * @return array<string, array{?string, string, string}>
     */
    public static function queryProvider(): array
    {
        return [
            'original mix removed' => ['Bicep', 'Glue (Original Mix)', 'Bicep Glue'],
            'square brackets and any case' => ['Bicep', 'Glue [original mix]', 'Bicep Glue'],
            'remix kept' => ['Bicep', 'Glue (Hammer Remix)', 'Bicep Glue (Hammer Remix)'],
            'extended mix kept' => ['Midas Field, Marijn Jansen', 'What We Got (Extended Mix)', 'Midas Field, Marijn Jansen What We Got (Extended Mix)'],
            'featuring kept' => ['Bonobo', 'Cirrus (feat. Erykah Badu)', 'Bonobo Cirrus (feat. Erykah Badu)'],
            'no artist' => [null, 'Teach Me (Original Mix)', 'Teach Me'],
        ];
    }

    #[DataProvider('queryProvider')]
    public function test_query(?string $artist, string $title, string $expectedQuery): void
    {
        $this->assertSame($expectedQuery, $this->searchUrls->query($artist, $title));
    }

    public function test_url_encodes_the_query(): void
    {
        $this->assertSame(
            'https://www.youtube.com/results?search_query=Ti%C3%ABsto+%26+Karol+G+Don%27t+Be+Shy',
            $this->searchUrls->url('Tiësto & Karol G', "Don't Be Shy"),
        );
    }
}
