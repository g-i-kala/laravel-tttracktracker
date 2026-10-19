<?php

namespace App\Models;

use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A playlist of library tracks in a manual order (`playlist_track.position`,
 * 1..n). A track is on a playlist at most once.
 */
#[Fillable(['name', 'notes'])]
class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Track, $this>
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
