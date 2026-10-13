<?php

namespace Tests\Unit;

use App\Services\ImportParserService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImportParserServiceTest extends TestCase
{
    private ImportParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ImportParserService;
    }

    public function test_it_parses_artist_and_title_keeping_version_in_title(): void
    {
        $lines = $this->parser->parse('Adam Beyer - Teach Me (Original Mix)');

        $this->assertSame([[
            'line_number' => 1,
            'raw_line' => 'Adam Beyer - Teach Me (Original Mix)',
            'artist' => 'Adam Beyer',
            'title' => 'Teach Me (Original Mix)',
            'normalized_key' => 'adam beyer|teach me',
            'error_message' => null,
        ]], $lines);
    }

    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function separatorProvider(): array
    {
        return [
            'hyphen' => ['Bicep - Glue', 'Bicep', 'Glue'],
            'en dash' => ['Bicep – Glue', 'Bicep', 'Glue'],
            'em dash' => ['Bicep — Glue', 'Bicep', 'Glue'],
            'splits on first separator only' => ['Jay-Z – Song - Edit', 'Jay-Z', 'Song - Edit'],
            'hyphen without spaces does not split' => ['Artist-Title', null, 'Artist-Title'],
            'extra whitespace is collapsed' => ['  Adam   Beyer  -   Teach Me  ', 'Adam Beyer', 'Teach Me'],
        ];
    }

    #[DataProvider('separatorProvider')]
    public function test_it_splits_on_spaced_dashes(string $line, ?string $artist, string $title): void
    {
        $parsed = $this->parser->parse($line)[0];

        $this->assertSame($artist, $parsed['artist']);
        $this->assertSame($title, $parsed['title']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function prefixProvider(): array
    {
        return [
            'dot numbering' => ['1. Bicep - Glue'],
            'parenthesis numbering' => ['12) Bicep - Glue'],
            'hash numbering' => ['#3 Bicep - Glue'],
            'zero-padded numbering' => ['01 Bicep - Glue'],
            'timestamp' => ['00:12 Bicep - Glue'],
            'long timestamp' => ['1:02:33 Bicep - Glue'],
            'bracketed timestamp' => ['[00:12] Bicep - Glue'],
            'timestamp with dash' => ['00:12 - Bicep - Glue'],
            'numbering and timestamp' => ['1. [00:12] Bicep - Glue'],
        ];
    }

    #[DataProvider('prefixProvider')]
    public function test_it_strips_leading_numbering_and_timestamps(string $line): void
    {
        $parsed = $this->parser->parse($line)[0];

        $this->assertSame('Bicep', $parsed['artist']);
        $this->assertSame('Glue', $parsed['title']);
        $this->assertSame($line, $parsed['raw_line']);
    }

    public function test_it_keeps_artist_names_starting_with_a_number(): void
    {
        $lines = $this->parser->parse("50 Cent - In Da Club\n808 State - Pacific State");

        $this->assertSame('50 Cent', $lines[0]['artist']);
        $this->assertSame('808 State', $lines[1]['artist']);
    }

    public function test_it_skips_empty_lines_and_keeps_original_line_numbers(): void
    {
        $lines = $this->parser->parse("Bicep - Glue\r\n\r\n   \nAdam Beyer - Teach Me\n");

        $this->assertCount(2, $lines);
        $this->assertSame(1, $lines[0]['line_number']);
        $this->assertSame(4, $lines[1]['line_number']);
    }

    public function test_line_without_separator_has_no_artist_and_an_error_message(): void
    {
        $parsed = $this->parser->parse('Teach Me')[0];

        $this->assertNull($parsed['artist']);
        $this->assertSame('Teach Me', $parsed['title']);
        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $parsed['error_message']);
        $this->assertSame('|teach me', $parsed['normalized_key']);
    }

    /**
     * @return array<string, array{?string, string, string}>
     */
    public static function normalizedKeyProvider(): array
    {
        return [
            'lowercase and squished' => ['Adam  BEYER', 'Teach Me', 'adam beyer|teach me'],
            'original mix removed' => ['Adam Beyer', 'Teach Me (Original Mix)', 'adam beyer|teach me'],
            'original mix in square brackets removed' => ['Adam Beyer', 'Teach Me [Original Mix]', 'adam beyer|teach me'],
            'extended mix kept' => ['Adam Beyer', 'Teach Me (Extended Mix)', 'adam beyer|teach me extended mix'],
            'remix kept' => ['Adam Beyer', 'Teach Me (Bart Skils Remix)', 'adam beyer|teach me bart skils remix'],
            'feat in artist removed' => ['Bicep feat. Clara La San', 'Glue', 'bicep|glue'],
            'ft in artist removed' => ['Bicep ft. Clara La San', 'Glue', 'bicep|glue'],
            'bracketed feat in title removed' => ['Bicep', 'Glue (feat. Clara La San)', 'bicep|glue'],
            'trailing feat in title removed up to bracket' => ['Bicep', 'Glue featuring Clara La San (Extended Mix)', 'bicep|glue extended mix'],
            'accents transliterated' => ['Âme', 'Rej', 'ame|rej'],
            'ampersand becomes and' => ['Tale Of Us & Mind Against', 'Astral', 'tale of us and mind against|astral'],
            'punctuation removed' => ['Mr. Oizo', 'Flat Beat!', 'mr oizo|flat beat'],
            'words containing ft are kept' => ['Daft Punk', 'Aerodynamic', 'daft punk|aerodynamic'],
            'no artist' => [null, 'Teach Me', '|teach me'],
        ];
    }

    #[DataProvider('normalizedKeyProvider')]
    public function test_normalized_key(?string $artist, string $title, string $expectedKey): void
    {
        $this->assertSame($expectedKey, $this->parser->normalizedKey($artist, $title));
    }

    public function test_exact_duplicates_share_the_same_key(): void
    {
        $lines = $this->parser->parse("Adam Beyer - Teach Me (Original Mix)\nadam beyer – Teach Me");

        $this->assertSame($lines[0]['normalized_key'], $lines[1]['normalized_key']);
    }
}
