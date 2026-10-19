<?php

namespace App\Models;

use Database\Factories\TrackFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of a track in the music library. Import items link to it
 * through `import_items.track_id`.
 *
 * Playable offline (POF) and wanted are stored; wanted means "want a file", so
 * it is cleared whenever the track becomes playable offline. Playable online
 * (PON) is derived from the links.
 */
#[Fillable([
    'artist',
    'title',
    'normalized_key',
    'similar_key',
    'genre',
    'bpm',
    'musical_key',
    'camelot_key',
    'label',
    'release_year',
    'rating',
    'notes',
    'playable_offline',
    'wanted',
    'youtube_url',
])]
class Track extends Model
{
    /**
     * Sortable list columns => SQL used for ordering (nulls always last).
     *
     * @var array<string, string>
     */
    public const array SORTS = [
        'artist' => 'lower(tracks.artist)',
        'title' => 'lower(tracks.title)',
        'bpm' => 'tracks.bpm',
        'camelot' => "cast(substring(tracks.camelot_key from '^[0-9]+') as integer)",
        'year' => 'tracks.release_year',
        'rating' => 'tracks.rating',
        'added' => 'tracks.created_at',
    ];

    /** @use HasFactory<TrackFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'playable_offline' => false,
        'wanted' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'playable_offline' => 'boolean',
            'wanted' => 'boolean',
            'bpm' => 'integer',
            'release_year' => 'integer',
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Track $track): void {
            if ($track->playable_offline) {
                $track->wanted = false;
            }
        });
    }

    /**
     * Playable online (PON): the track has a link to play it from.
     */
    public function isPlayableOnline(): bool
    {
        return $this->youtube_url !== null;
    }

    /**
     * @return HasMany<ImportItem, $this>
     */
    public function importItems(): HasMany
    {
        return $this->hasMany(ImportItem::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderByRaw('lower(tags.name)');
    }

    /**
     * @return BelongsToMany<Playlist, $this>
     */
    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)->withPivot('position')->withTimestamps();
    }
}
