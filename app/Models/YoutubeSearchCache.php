<?php

namespace App\Models;

use Database\Factories\YoutubeSearchCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Table('youtube_search_cache')]
#[Fillable(['query_hash', 'query', 'results', 'provider', 'expires_at'])]
class YoutubeSearchCache extends Model
{
    /** @use HasFactory<YoutubeSearchCacheFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'results' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
