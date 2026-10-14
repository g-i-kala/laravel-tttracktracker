<?php

namespace App\Enums;

/**
 * Pipeline stage of an import batch. Derived states (has duplicates,
 * ready to export, missing links) are computed from items, not stored.
 */
enum ImportBatchStatus: string
{
    case Draft = 'draft';
    case Parsing = 'parsing';
    case Parsed = 'parsed';
    case Reviewed = 'reviewed';
    case Searching = 'searching';
    case Searched = 'searched';
    case Exported = 'exported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Parsing => 'Parsing',
            self::Parsed => 'To review',
            self::Reviewed => 'Reviewed',
            self::Searching => 'Searching',
            self::Searched => 'Searched',
            self::Exported => 'Exported',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether the batch has been searched on YouTube, so "Continue" opens YouTube Results instead of Parse Review.
     */
    public function hasSearchResults(): bool
    {
        return in_array($this, [self::Searched, self::Exported], true);
    }
}
