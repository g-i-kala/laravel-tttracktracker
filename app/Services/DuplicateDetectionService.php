<?php

namespace App\Services;

use App\Models\ImportItem;
use Illuminate\Support\Collection;

/**
 * Finds duplicates within one import batch. Exact duplicates share the
 * `normalized_key`; similar tracks share the similar key (other version or
 * artists in another order) or differ by a small typo in one part, but not the
 * normalized key. Pure: works on the given items, no database access.
 */
class DuplicateDetectionService
{
    /**
     * Most character edits (Levenshtein distance) for a part to count as a typo.
     */
    private const int MAX_TYPO_EDITS = 2;

    public function __construct(private ImportParserService $parser) {}

    /**
     * Exact duplicates mapped to the first item (lowest line number) with the same key.
     *
     * @param  Collection<int, ImportItem>  $items
     * @return array<int, int> duplicate item id => original item id
     */
    public function exactDuplicates(Collection $items): array
    {
        $duplicates = [];

        foreach ($items->sortBy('line_number')->groupBy('normalized_key') as $group) {
            $originalId = $group->first()->id;

            foreach ($group->skip(1) as $duplicate) {
                $duplicates[$duplicate->id] = $originalId;
            }
        }

        return $duplicates;
    }

    /**
     * Items that look like another item in the batch but are not exact duplicates
     * of it: the same similar key (other version, artists in another order), or
     * one part (artists or base title) equal and the other with a small typo.
     *
     * @param  Collection<int, ImportItem>  $items
     * @return array<int, list<int>> item id => ids of similar items, in line order
     */
    public function similarItems(Collection $items): array
    {
        $entries = $items->sortBy('line_number')
            ->values()
            ->map(fn (ImportItem $item): array => [
                'item' => $item,
                'parts' => explode('|', $this->parser->similarKey($item->artist, $item->title), 2),
            ]);

        $similar = [];

        foreach ($entries as $index => $entry) {
            foreach ($entries as $otherIndex => $other) {
                if ($index === $otherIndex || $entry['item']->normalized_key === $other['item']->normalized_key) {
                    continue;
                }

                if ($this->looksSimilar($entry['parts'], $other['parts'])) {
                    $similar[$entry['item']->id][] = $other['item']->id;
                }
            }
        }

        return $similar;
    }

    /**
     * @param  array{0: string, 1: string}  $parts  similar-key artists and base title
     * @param  array{0: string, 1: string}  $otherParts
     */
    private function looksSimilar(array $parts, array $otherParts): bool
    {
        [$artists, $title] = $parts;
        [$otherArtists, $otherTitle] = $otherParts;

        if ($artists === $otherArtists) {
            return $this->isSameOrSmallTypo($title, $otherTitle);
        }

        return $title === $otherTitle && $this->isSameOrSmallTypo($artists, $otherArtists);
    }

    /**
     * Equal, or at most MAX_TYPO_EDITS edits that change less than half of the
     * shorter text (so "Glu"/"Glue" match, but "Hey"/"Sky" do not).
     */
    private function isSameOrSmallTypo(string $value, string $otherValue): bool
    {
        if ($value === $otherValue) {
            return true;
        }

        $distance = levenshtein($value, $otherValue);

        return $distance <= self::MAX_TYPO_EDITS && $distance * 2 < min(strlen($value), strlen($otherValue));
    }
}
