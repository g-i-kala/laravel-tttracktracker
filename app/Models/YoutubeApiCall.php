<?php

namespace App\Models;

use App\Enums\YoutubeApiCallStatus;
use Database\Factories\YoutubeApiCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real YouTube Data API request (cache hits are not recorded): estimated quota units and outcome.
 */
#[Fillable(['import_batch_id', 'endpoint', 'query', 'quota_units', 'status', 'error_reason', 'results_count', 'duration_ms'])]
class YoutubeApiCall extends Model
{
    /** @use HasFactory<YoutubeApiCallFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => YoutubeApiCallStatus::class,
            'quota_units' => 'integer',
            'results_count' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
