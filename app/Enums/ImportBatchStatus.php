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
    case Searching = 'searching';
    case Searched = 'searched';
    case Exported = 'exported';
    case Failed = 'failed';
}
