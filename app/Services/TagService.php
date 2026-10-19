<?php

namespace App\Services;

use App\Models\Tag;
use App\Models\Track;
use Illuminate\Support\Str;

/**
 * Free tags on library tracks. Names are matched regardless of case (the
 * first spelling is kept) and tags left without tracks are deleted.
 */
class TagService
{
    public const int MAX_LENGTH = 50;

    /**
     * The tag with this name in any case, or a new one.
     */
    public function findOrCreate(string $name): Tag
    {
        $name = Str::squish($name);

        return $this->find($name) ?? Tag::create(['name' => $name]);
    }

    public function find(string $name): ?Tag
    {
        return Tag::query()->whereRaw('lower(name) = lower(?)', [Str::squish($name)])->first();
    }

    /**
     * Tag the tracks (ones that already have the tag are skipped).
     *
     * @param  list<int>  $trackIds
     */
    public function tagTracks(array $trackIds, string $name): Tag
    {
        $tag = $this->findOrCreate($name);
        $tag->tracks()->syncWithoutDetaching(Track::query()->whereKey($trackIds)->pluck('id')->all());

        return $tag;
    }

    /**
     * Remove the tag from the tracks; the tag is deleted when no track has it.
     *
     * @param  list<int>  $trackIds
     * @return int number of tracks the tag was removed from
     */
    public function untagTracks(array $trackIds, Tag $tag): int
    {
        $removed = $tag->tracks()->detach($trackIds);
        $this->deleteUnused();

        return $removed;
    }

    public function deleteUnused(): int
    {
        return Tag::query()->doesntHave('tracks')->delete();
    }

    /**
     * All tag names, for suggestions and the filter.
     *
     * @return array<int, string> tag id => name
     */
    public function all(): array
    {
        return Tag::query()->orderByRaw('lower(name)')->pluck('name', 'id')->all();
    }
}
