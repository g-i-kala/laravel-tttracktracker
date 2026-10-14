<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Parses a pasted track list into `Artist - Title` lines and builds the keys
 * used for exact and similar duplicate detection. Pure: no database access.
 */
class ImportParserService
{
    public const string ARTIST_NOT_DETECTED = 'Artist not detected';

    /**
     * Separator between artist and title: a hyphen, en dash or em dash surrounded by whitespace.
     */
    private const string SEPARATOR_PATTERN = '/\s+[-–—]\s+/u';

    /**
     * Leading numbering: `1.`, `1)`, `#1` or a zero-padded number like `01`.
     * A bare number without a leading zero is kept (e.g. `50 Cent`, `808 State`).
     */
    private const string NUMBERING_PATTERN = '/^(?:\d+[.)]|#\d+|0\d+)\s+/u';

    /**
     * Leading timestamp: `00:12`, `1:02:33`, `[00:12]`, `(00:12)`, optionally followed by a dash or pipe.
     */
    private const string TIMESTAMP_PATTERN = '/^[\[(]?\d{1,2}(?::\d{2}){1,2}[\])]?(?:\s*[-–—|])?\s+/u';

    /**
     * Featured artist credit, bracketed (`(feat. X)`) or trailing (`feat. X` up to the next bracket).
     */
    private const string FEATURING_PATTERN = '/[(\[]\s*(?:feat\.?|ft\.?|featuring)\s[^)\]]*[)\]]|\b(?:feat\.?|ft\.?|featuring)\s.*?(?=[(\[]|$)/u';

    private const string ORIGINAL_MIX_PATTERN = '/[(\[]\s*original mix\s*[)\]]/u';

    /**
     * Separator between artists for the similar key: `&`, `,`, `and`, `x`, `vs`.
     */
    private const string ARTIST_SEPARATOR_PATTERN = '/\s*(?:&|,|\band\b|\bx\b|\bvs\b\.?)\s*/iu';

    private const string BRACKETED_PATTERN = '/[(\[][^)\]]*[)\]]/u';

    /**
     * Parse raw textarea input into track lines. Empty lines are skipped;
     * line numbers refer to the original input.
     *
     * @return list<array{line_number: int, raw_line: string, artist: ?string, title: string, normalized_key: string, error_message: ?string}>
     */
    public function parse(string $rawInput): array
    {
        $parsedLines = [];

        foreach (preg_split('/\R/u', $rawInput) as $index => $rawLine) {
            $cleanedLine = $this->cleanLine($rawLine);

            if ($cleanedLine === '') {
                continue;
            }

            [$artist, $title] = $this->splitArtistAndTitle($cleanedLine);

            $parsedLines[] = [
                'line_number' => $index + 1,
                'raw_line' => $rawLine,
                'artist' => $artist,
                'title' => $title,
                'normalized_key' => $this->normalizedKey($artist, $title),
                'error_message' => $artist === null ? self::ARTIST_NOT_DETECTED : null,
            ];
        }

        return $parsedLines;
    }

    /**
     * Build the key for exact duplicate detection: ASCII lowercase, without
     * featured artists and `(Original Mix)`, `&` as `and`, punctuation removed.
     * Other versions (remixes, extended mixes) stay distinct.
     */
    public function normalizedKey(?string $artist, string $title): string
    {
        return $this->normalizeKeyPart($artist ?? '').'|'.$this->normalizeKeyPart($title);
    }

    /**
     * Build the key for similar duplicate detection: like the normalized key, but
     * the title without any bracketed part (version, remix) and the artists in
     * alphabetical order, so other versions and swapped artists match.
     */
    public function similarKey(?string $artist, string $title): string
    {
        $artists = collect(preg_split(self::ARTIST_SEPARATOR_PATTERN, $artist ?? ''))
            ->map(fn (string $name): string => $this->normalizeKeyPart($name))
            ->filter()
            ->sort()
            ->implode(' ');

        $baseTitle = $this->normalizeKeyPart(preg_replace(self::BRACKETED_PATTERN, ' ', $title));

        return $artists.'|'.($baseTitle !== '' ? $baseTitle : $this->normalizeKeyPart($title));
    }

    private function cleanLine(string $rawLine): string
    {
        $line = Str::squish($rawLine);
        $line = preg_replace(self::NUMBERING_PATTERN, '', $line);

        return trim(preg_replace(self::TIMESTAMP_PATTERN, '', $line));
    }

    /**
     * Split on the first separator. Without one, the whole line is the title.
     *
     * @return array{0: ?string, 1: string}
     */
    private function splitArtistAndTitle(string $line): array
    {
        $parts = preg_split(self::SEPARATOR_PATTERN, $line, 2);

        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            return [$parts[0], $parts[1]];
        }

        return [null, $line];
    }

    private function normalizeKeyPart(string $value): string
    {
        $value = Str::lower(Str::ascii($value));
        $value = preg_replace(self::FEATURING_PATTERN, ' ', $value);
        $value = preg_replace(self::ORIGINAL_MIX_PATTERN, ' ', $value);
        $value = str_replace('&', ' and ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
