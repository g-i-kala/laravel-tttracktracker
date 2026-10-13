<?php

namespace App\Models;

use App\Enums\ImportItemStatus;
use Database\Factories\ImportItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'line_number',
    'raw_line',
    'artist',
    'title',
    'genre',
    'bpm',
    'musical_key',
    'camelot_key',
    'label',
    'normalized_key',
    'status',
    'selected_youtube_url',
    'selected_youtube_video_id',
    'selected_youtube_title',
    'selected_youtube_channel',
    'youtube_score',
    'youtube_search_url',
    'include_in_export',
    'error_message',
])]
class ImportItem extends Model
{
    /** @use HasFactory<ImportItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'parsed',
        'include_in_export' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportItemStatus::class,
            'line_number' => 'integer',
            'bpm' => 'integer',
            'youtube_score' => 'integer',
            'include_in_export' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * @return HasMany<YoutubeCandidate, $this>
     */
    public function youtubeCandidates(): HasMany
    {
        return $this->hasMany(YoutubeCandidate::class);
    }
}
