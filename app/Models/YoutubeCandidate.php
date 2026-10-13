<?php

namespace App\Models;

use Database\Factories\YoutubeCandidateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['video_id', 'url', 'title', 'channel_title', 'description', 'published_at', 'score', 'rank'])]
class YoutubeCandidate extends Model
{
    /** @use HasFactory<YoutubeCandidateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'score' => 'integer',
            'rank' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ImportItem, $this>
     */
    public function importItem(): BelongsTo
    {
        return $this->belongsTo(ImportItem::class);
    }
}
