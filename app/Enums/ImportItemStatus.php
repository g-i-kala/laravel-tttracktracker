<?php

namespace App\Enums;

/**
 * Pipeline stage of an import item. Derived states (has link, no link,
 * excluded, possible duplicate) are computed, not stored.
 */
enum ImportItemStatus: string
{
    case Parsed = 'parsed';
    case Duplicate = 'duplicate';
    case SearchPending = 'search_pending';
    case Searched = 'searched';
    case Exported = 'exported';
    case Failed = 'failed';
}
