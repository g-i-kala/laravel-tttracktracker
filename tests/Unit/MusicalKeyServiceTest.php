<?php

namespace Tests\Unit;

use App\Services\MusicalKeyService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MusicalKeyServiceTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string}>
     */
    public static function keys(): array
    {
        return [
            'beatport minor' => ['E Minor', '9A'],
            'beatport major' => ['F Major', '7B'],
            'C minor' => ['C Minor', '5A'],
            'A minor' => ['A Minor', '8A'],
            'C major' => ['C Major', '8B'],
            'B major' => ['B Major', '1B'],
            'sharp sign' => ['F♯ Minor', '11A'],
            'flat sign' => ['D♭ Major', '3B'],
            'ascii sharp' => ['G# Minor', '1A'],
            'ascii flat' => ['Eb Minor', '2A'],
            'enharmonic flat equals sharp' => ['Ab Minor', '1A'],
            'short minor' => ['Am', '8A'],
            'short flat minor' => ['Bbm', '3A'],
            'short min' => ['C# min', '12A'],
            'maj' => ['Gb maj', '2B'],
            'note only is major' => ['D', '10B'],
            'note B only is major' => ['B', '1B'],
            'lowercase' => ['e minor', '9A'],
            'extra spaces' => ['  A   Major ', '11B'],
            'already camelot' => ['9A', '9A'],
            'camelot leading zero and lowercase' => ['09b', '9B'],
            'camelot 12' => ['12A', '12A'],
            'unknown' => ['H Minor', null],
            'camelot out of range' => ['13A', null],
            'garbage' => ['128 BPM', null],
            'empty' => ['', null],
            'null' => [null, null],
        ];
    }

    #[DataProvider('keys')]
    public function test_it_converts_keys_to_camelot(?string $key, ?string $expected): void
    {
        $this->assertSame($expected, (new MusicalKeyService)->toCamelot($key));
    }
}
