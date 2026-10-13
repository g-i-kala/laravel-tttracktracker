<?php

namespace App\Services;

/**
 * Converts musical keys as written by stores (e.g. "E Minor", "F♯ Major", "Bbm")
 * to Camelot notation (e.g. "9A", "2B") for harmonic mixing. Pure, no DB.
 */
class MusicalKeyService
{
    /**
     * Pitch class (0 = C … 11 = B) of each note name.
     *
     * @var array<string, int>
     */
    private const array PITCH_CLASSES = [
        'C' => 0, 'B#' => 0,
        'C#' => 1, 'DB' => 1,
        'D' => 2,
        'D#' => 3, 'EB' => 3,
        'E' => 4, 'FB' => 4,
        'F' => 5, 'E#' => 5,
        'F#' => 6, 'GB' => 6,
        'G' => 7,
        'G#' => 8, 'AB' => 8,
        'A' => 9,
        'A#' => 10, 'BB' => 10,
        'B' => 11, 'CB' => 11,
    ];

    /**
     * Camelot number of each pitch class for minor keys (A); major keys (B) are the relative major, 3 semitones up.
     *
     * @var array<int, int>
     */
    private const array MINOR_CAMELOT = [
        0 => 5, 1 => 12, 2 => 7, 3 => 2, 4 => 9, 5 => 4,
        6 => 11, 7 => 6, 8 => 1, 9 => 8, 10 => 3, 11 => 10,
    ];

    /**
     * Camelot code for a key, or null when it cannot be recognized.
     * Keys already in Camelot notation are returned normalized ("09a" → "9A").
     */
    public function toCamelot(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $key = trim(str_replace(['♯', '♭'], ['#', 'b'], $key));

        if (preg_match('/^(1[0-2]|0?[1-9])\s*([AB])$/i', $key, $matches)) {
            return (int) $matches[1].strtoupper($matches[2]);
        }

        if (! preg_match('/^([A-G])\s*([#b]?)\s*(major|maj|minor|min|m)?$/i', $key, $matches)) {
            return null;
        }

        $pitchClass = self::PITCH_CLASSES[strtoupper($matches[1].$matches[2])] ?? null;

        if ($pitchClass === null) {
            return null;
        }

        $isMinor = in_array(strtolower($matches[3] ?? ''), ['minor', 'min', 'm'], true);

        if ($isMinor) {
            return self::MINOR_CAMELOT[$pitchClass].'A';
        }

        return self::MINOR_CAMELOT[($pitchClass + 9) % 12].'B';
    }
}
