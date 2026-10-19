<?php

namespace App\Livewire\Library;

use App\Models\Tag;
use App\Models\Track;
use App\Services\AiTrackListParserService;
use App\Services\LibraryService;
use App\Services\TagService;
use App\Services\YoutubeUrlNormalizer;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Library: all library tracks (one per version), 50 per page, with search,
 * filters (availability, genre, Camelot, BPM and year ranges, minimum rating,
 * tags) and sortable columns. Ratings are set with stars in the row or the
 * panel; tags in the panel or for the ticked tracks. Filters and sorting live in the URL. POF can be switched per row
 * or for the ticked tracks. Clicking a row expands it to edit the track
 * (autosaved on blur) and shows the imports it came from. An artist/title
 * that matches another library track opens a merge modal. Tracks can be
 * removed one by one or the ticked ones, after a confirmation modal.
 */
#[Title('Library')]
class Index extends Component
{
    use WithPagination;

    public const int PER_PAGE = 50;

    /**
     * Availability filters: a track must match every chosen one; `none` means
     * not playable offline, not playable online and not wanted.
     *
     * @var list<string>
     */
    public const array AVAILABILITY = ['pof', 'pon', 'wanted', 'none'];

    private const array FILTERS = ['search', 'availability', 'genre', 'camelot', 'bpmFrom', 'bpmTo', 'yearFrom', 'yearTo', 'minRating', 'tagFilter'];

    /**
     * Ticked track ids (kept across pages and filters).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    public ?string $notice = null;

    public ?int $expandedTrackId = null;

    /**
     * Track ids waiting for confirmation in the remove modal.
     *
     * @var list<int>
     */
    public array $pendingDeleteIds = [];

    public bool $showDeleteModal = false;

    public bool $showMergeModal = false;

    /**
     * The existing track the expanded track collides with.
     */
    public ?int $mergeTargetId = null;

    /**
     * Conflicting field => `current` (the edited track) or `existing`.
     *
     * @var array<string, string>
     */
    public array $mergeChoices = [];

    /**
     * Editable values of the expanded track.
     *
     * @var array{artist?: string, title?: string, genre?: string, bpm?: string, musical_key?: string, label?: string, release_year?: string, notes?: string, youtube_url?: string, playable_offline?: bool, wanted?: bool}
     */
    public array $edit = [];

    #[Url(except: '')]
    public string $search = '';

    /**
     * @var list<string>
     */
    #[Url(except: [])]
    public array $availability = [];

    #[Url(except: '')]
    public string $genre = '';

    #[Url(except: '')]
    public string $camelot = '';

    #[Url(except: '')]
    public string $bpmFrom = '';

    #[Url(except: '')]
    public string $bpmTo = '';

    #[Url(except: '')]
    public string $yearFrom = '';

    #[Url(except: '')]
    public string $yearTo = '';

    #[Url(except: '')]
    public string $minRating = '';

    /**
     * Tag ids; a track must have all of them.
     *
     * @var list<int|string>
     */
    #[Url(as: 'tags', except: [])]
    public array $tagFilter = [];

    /**
     * New tag typed in the expanded row.
     */
    public string $newTag = '';

    /**
     * Tag typed in the selection bar.
     */
    public string $bulkTag = '';

    #[Url(except: 'artist')]
    public string $sort = 'artist';

    #[Url(except: 'asc')]
    public string $direction = 'asc';

    public function updated(string $property): void
    {
        if (in_array(Str::before($property, '.'), self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    /**
     * Toggle an availability filter. `none` excludes the others.
     */
    public function toggleAvailability(string $value): void
    {
        if (! in_array($value, self::AVAILABILITY, true)) {
            return;
        }

        if (in_array($value, $this->availability, true)) {
            $this->availability = array_values(array_diff($this->availability, [$value]));
        } elseif ($value === 'none') {
            $this->availability = ['none'];
        } else {
            $this->availability = [...array_values(array_diff($this->availability, ['none'])), $value];
        }

        $this->resetPage();
    }

    /**
     * Sort by a column; clicking the current column flips the direction.
     */
    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, Track::SORTS)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
        $this->resetPage();
    }

    /**
     * Expand a row to edit the track, or collapse it when it is open already.
     * One row is open at a time.
     */
    public function toggleTrack(int $trackId): void
    {
        $this->resetErrorBag();

        if ($this->expandedTrackId === $trackId) {
            $this->reset('expandedTrackId', 'edit');

            return;
        }

        $track = Track::query()->findOrFail($trackId);

        $this->expandedTrackId = $track->id;
        $this->edit = [
            'artist' => (string) $track->artist,
            'title' => $track->title,
            'genre' => (string) $track->genre,
            'bpm' => (string) $track->bpm,
            'musical_key' => (string) $track->musical_key,
            'label' => (string) $track->label,
            'release_year' => (string) $track->release_year,
            'notes' => (string) $track->notes,
            'youtube_url' => (string) $track->youtube_url,
            'playable_offline' => $track->playable_offline,
            'wanted' => $track->wanted,
        ];
    }

    /**
     * Autosave one field of the expanded track (on blur; checkboxes right away).
     * An artist/title that matches another library track is not saved.
     * Dispatches `track-saved` so the panel can flash "Saved".
     */
    public function updatedEdit(mixed $value, string $key): void
    {
        if ($this->expandedTrackId === null) {
            return;
        }

        $this->validateOnly("edit.{$key}");

        $track = Track::query()->findOrFail($this->expandedTrackId);
        $library = app(LibraryService::class);

        if (in_array($key, ['artist', 'title'], true)) {
            $conflict = $library->conflictingTrack($track, $this->edit['artist'], $this->edit['title']);

            if ($conflict !== null) {
                $this->openMergeModal($track, $conflict);

                return;
            }
        }

        $library->updateTrackDetails($track, [
            ...$this->edit,
            'youtube_url' => $this->edit['youtube_url'] === '' ? null : app(YoutubeUrlNormalizer::class)->normalize($this->edit['youtube_url']),
        ]);

        $this->edit['youtube_url'] = (string) $track->youtube_url;
        $this->edit['wanted'] = $track->wanted;

        $this->dispatch('track-saved', id: $track->id);
        unset($this->tracks, $this->genres, $this->camelotKeys, $this->expandedTrack);
    }

    /**
     * "Merge": the expanded track is merged into the existing track, which
     * stays and opens in its place.
     */
    public function confirmMerge(LibraryService $library): void
    {
        $current = Track::query()->find($this->expandedTrackId);
        $existing = Track::query()->find($this->mergeTargetId);

        if ($current === null || $existing === null) {
            $this->cancelMerge();

            return;
        }

        $moved = $current->importItems()->count();
        $choices = collect($this->mergeChoices)->only(array_keys(LibraryService::MERGE_FIELDS))->all();
        $merged = $library->mergeTracks($current, $existing, $this->mergeOverrides(), $choices);

        $this->selected = array_values(array_diff(array_map('intval', $this->selected), [$current->id]));
        $this->reset('showMergeModal', 'mergeTargetId', 'mergeChoices', 'expandedTrackId', 'edit');
        $this->toggleTrack($merged->id);
        $this->notice = 'Merged into “'.($merged->artist ? $merged->artist.' - ' : '').$merged->title.'”. '
            .$moved.' import '.str('track')->plural($moved).' moved to it.';

        unset($this->tracks, $this->totalTracks, $this->genres, $this->camelotKeys, $this->expandedTrack);
    }

    /**
     * "Cancel" (also Escape / backdrop): the artist/title edit is not saved.
     */
    public function cancelMerge(): void
    {
        $track = $this->expandedTrackId === null ? null : Track::query()->find($this->expandedTrackId);

        if ($track !== null) {
            $this->edit['artist'] = (string) $track->artist;
            $this->edit['title'] = $track->title;
        }

        $this->reset('showMergeModal', 'mergeTargetId', 'mergeChoices');
    }

    public function updatedShowMergeModal(bool $value): void
    {
        if (! $value) {
            $this->cancelMerge();
        }
    }

    /**
     * Side-by-side values of the expanded track and the merge target.
     *
     * @return array<string, array{current: string|int|null, existing: string|int|null, conflict: bool}>
     */
    #[Computed]
    public function mergeComparison(): array
    {
        $current = $this->expandedTrackId === null ? null : Track::query()->find($this->expandedTrackId);
        $existing = $this->mergeTargetId === null ? null : Track::query()->find($this->mergeTargetId);

        return $current === null || $existing === null
            ? []
            : app(LibraryService::class)->mergeComparison($current, $existing, $this->mergeOverrides());
    }

    #[Computed]
    public function mergeTarget(): ?Track
    {
        return $this->mergeTargetId === null ? null : Track::query()->withCount('importItems')->find($this->mergeTargetId);
    }

    /**
     * Conflicting fields default to the existing track, except artist and
     * title, which default to what was just typed.
     */
    private function openMergeModal(Track $current, Track $existing): void
    {
        $this->mergeTargetId = $existing->id;
        unset($this->mergeComparison, $this->mergeTarget);

        $this->mergeChoices = collect($this->mergeComparison)
            ->filter(fn (array $values): bool => $values['conflict'])
            ->map(fn (array $values, string $field): string => in_array($field, ['artist', 'title'], true) ? 'current' : 'existing')
            ->all();
        $this->showMergeModal = true;
    }

    /**
     * @return array{artist: ?string, title: string}
     */
    private function mergeOverrides(): array
    {
        return ['artist' => $this->edit['artist'] ?? null, 'title' => $this->edit['title'] ?? ''];
    }

    /**
     * The expanded track with its sources (import items and their imports).
     */
    #[Computed]
    public function expandedTrack(): ?Track
    {
        return $this->expandedTrackId === null
            ? null
            : Track::query()
                ->with(['tags', 'importItems' => fn ($query) => $query->with('importBatch')->orderBy('import_batch_id')->orderBy('line_number')])
                ->find($this->expandedTrackId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'edit.artist' => ['nullable', 'string', 'max:255'],
            'edit.title' => ['required', 'string', 'max:255'],
            'edit.genre' => ['nullable', 'string', 'max:255'],
            'edit.bpm' => ['nullable', 'integer', 'between:'.AiTrackListParserService::MIN_BPM.','.AiTrackListParserService::MAX_BPM],
            'edit.musical_key' => ['nullable', 'string', 'max:20'],
            'edit.label' => ['nullable', 'string', 'max:255'],
            'edit.release_year' => ['nullable', 'integer', 'between:1900,'.(now()->year + 1)],
            'edit.notes' => ['nullable', 'string', 'max:5000'],
            'edit.youtube_url' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (app(YoutubeUrlNormalizer::class)->normalize((string) $value) === null) {
                    $fail('Paste a YouTube video link (youtube.com/watch?v=…, youtu.be/… or youtube.com/shorts/…).');
                }
            }],
            'edit.playable_offline' => ['boolean'],
            'edit.wanted' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'edit.artist' => 'artist',
            'edit.title' => 'title',
            'edit.genre' => 'genre',
            'edit.bpm' => 'BPM',
            'edit.musical_key' => 'key',
            'edit.label' => 'label',
            'edit.release_year' => 'year',
            'edit.notes' => 'notes',
            'edit.youtube_url' => 'YouTube link',
        ];
    }

    /**
     * Click on a star (row or panel): set the rating, or clear it when it is the same.
     */
    public function rate(int $trackId, int $stars, LibraryService $library): void
    {
        if ($stars < 1 || $stars > 5) {
            return;
        }

        $library->rate(Track::query()->findOrFail($trackId), $stars);

        unset($this->tracks, $this->expandedTrack);
    }

    /**
     * Add the typed tag to the expanded track.
     */
    public function addTag(TagService $tags): void
    {
        $this->validate(['newTag' => ['required', 'string', 'max:'.TagService::MAX_LENGTH]], attributes: ['newTag' => 'tag']);

        if ($this->expandedTrackId !== null) {
            $tags->tagTracks([$this->expandedTrackId], $this->newTag);
        }

        $this->reset('newTag');
        unset($this->tracks, $this->expandedTrack, $this->allTags);
    }

    public function removeTag(int $tagId, TagService $tags): void
    {
        if ($this->expandedTrackId !== null) {
            $tags->untagTracks([$this->expandedTrackId], Tag::query()->findOrFail($tagId));
        }

        $this->afterTagsRemoved();
    }

    /**
     * Add the typed tag to the ticked tracks.
     */
    public function tagSelected(TagService $tags): void
    {
        $this->validate(['bulkTag' => ['required', 'string', 'max:'.TagService::MAX_LENGTH]], attributes: ['bulkTag' => 'tag']);

        $tag = $tags->tagTracks(array_map('intval', $this->selected), $this->bulkTag);
        $count = count($this->selected);

        $this->notice = "Tagged {$count} ".str('track')->plural($count)." with “{$tag->name}”.";
        $this->reset('bulkTag');
        unset($this->tracks, $this->expandedTrack, $this->allTags);
    }

    /**
     * Remove the typed tag from the ticked tracks.
     */
    public function untagSelected(TagService $tags): void
    {
        $this->validate(['bulkTag' => ['required', 'string', 'max:'.TagService::MAX_LENGTH]], attributes: ['bulkTag' => 'tag']);

        $tag = $tags->find($this->bulkTag);

        if ($tag === null) {
            $this->addError('bulkTag', 'No such tag.');

            return;
        }

        $name = $tag->name;
        $removed = $tags->untagTracks(array_map('intval', $this->selected), $tag);

        $this->notice = "Removed “{$name}” from {$removed} ".str('track')->plural($removed).'.';
        $this->reset('bulkTag');
        $this->afterTagsRemoved();
    }

    public function addTagFilter(int|string $tagId): void
    {
        $tagId = (int) $tagId;

        if ($tagId > 0 && ! in_array($tagId, array_map('intval', $this->tagFilter), true)) {
            $this->tagFilter = [...array_map('intval', $this->tagFilter), $tagId];
            $this->resetPage();
        }
    }

    public function removeTagFilter(int $tagId): void
    {
        $this->tagFilter = array_values(array_diff(array_map('intval', $this->tagFilter), [$tagId]));
        $this->resetPage();
    }

    /**
     * @return array<int, string> tag id => name
     */
    #[Computed]
    public function allTags(): array
    {
        return app(TagService::class)->all();
    }

    public function togglePlayableOffline(int $trackId, LibraryService $library): void
    {
        $track = Track::query()->findOrFail($trackId);

        $library->setPlayableOffline([$track->id], ! $track->playable_offline);

        $this->syncExpandedFlags();
        unset($this->tracks);
    }

    /**
     * Mark (or unmark) the ticked tracks as playable offline.
     */
    public function setSelectedPlayableOffline(bool $playableOffline, LibraryService $library): void
    {
        $updated = $library->setPlayableOffline(array_map('intval', $this->selected), $playableOffline);

        $this->notice = $playableOffline
            ? "Marked {$updated} ".str('track')->plural($updated).' as playable offline (POF).'
            : "Removed POF from {$updated} ".str('track')->plural($updated).'.';
        $this->selected = [];

        $this->syncExpandedFlags();
        unset($this->tracks);
    }

    /**
     * Open the remove modal for one track, or for the ticked ones when no id is given.
     */
    public function confirmDelete(?int $trackId = null): void
    {
        $this->pendingDeleteIds = $trackId !== null ? [$trackId] : array_map('intval', $this->selected);

        unset($this->deleteSummary);
        $this->showDeleteModal = $this->pendingDeleteIds !== [];
    }

    public function delete(LibraryService $library): void
    {
        $deleted = $library->deleteTracks($this->pendingDeleteIds);

        $this->selected = array_values(array_diff(array_map('intval', $this->selected), $this->pendingDeleteIds));

        if (in_array($this->expandedTrackId, $this->pendingDeleteIds, true)) {
            $this->reset('expandedTrackId', 'edit');
        }

        $this->pendingDeleteIds = [];
        $this->showDeleteModal = false;
        $this->notice = "Removed {$deleted} ".str('track')->plural($deleted).' from the library.';

        unset($this->tracks, $this->totalTracks, $this->genres, $this->camelotKeys, $this->deleteSummary, $this->allTags);
        $this->tagFilter = array_values(array_intersect(array_map('intval', $this->tagFilter), array_keys($this->allTags)));

        if ($this->tracks->isEmpty() && $this->getPage() > 1) {
            $this->setPage($this->tracks->lastPage());
        }
    }

    /**
     * @return array{tracks: int, linkedItems: int, name: ?string}
     */
    #[Computed]
    public function deleteSummary(): array
    {
        $track = count($this->pendingDeleteIds) === 1 ? Track::query()->find($this->pendingDeleteIds[0]) : null;

        return [
            ...app(LibraryService::class)->deletionSummary($this->pendingDeleteIds),
            'name' => $track === null ? null : ($track->artist ? $track->artist.' - ' : '').$track->title,
        ];
    }

    /**
     * Tick every track on this page, or untick them when all are ticked already.
     */
    public function toggleAllOnPage(): void
    {
        $pageIds = $this->tracks->pluck('id')->map(fn (int $id): string => (string) $id)->all();
        $selected = array_map('strval', $this->selected);

        $this->selected = array_diff($pageIds, $selected) === []
            ? array_values(array_diff($selected, $pageIds))
            : array_values(array_unique([...$selected, ...$pageIds]));
    }

    public function clearFilters(): void
    {
        $this->reset(self::FILTERS);
        $this->resetPage();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return collect(self::FILTERS)->contains(fn (string $filter): bool => $this->{$filter} !== '' && $this->{$filter} !== []);
    }

    /**
     * @return LengthAwarePaginator<int, Track>
     */
    #[Computed]
    public function tracks(): LengthAwarePaginator
    {
        $query = Track::query()->with('tags');

        $this->applySearch($query);
        $this->applyAvailability($query);

        $query
            ->when($this->genre !== '', fn (Builder $query) => $query->where('genre', $this->genre))
            ->when($this->camelot !== '', fn (Builder $query) => $query->where('camelot_key', $this->camelot))
            ->when($this->number($this->bpmFrom) !== null, fn (Builder $query) => $query->where('bpm', '>=', $this->number($this->bpmFrom)))
            ->when($this->number($this->bpmTo) !== null, fn (Builder $query) => $query->where('bpm', '<=', $this->number($this->bpmTo)))
            ->when($this->number($this->yearFrom) !== null, fn (Builder $query) => $query->where('release_year', '>=', $this->number($this->yearFrom)))
            ->when($this->number($this->yearTo) !== null, fn (Builder $query) => $query->where('release_year', '<=', $this->number($this->yearTo)))
            ->when($this->number($this->minRating) !== null, fn (Builder $query) => $query->where('rating', '>=', $this->number($this->minRating)));

        foreach (array_map('intval', $this->tagFilter) as $tagId) {
            $query->whereHas('tags', fn (Builder $query) => $query->whereKey($tagId));
        }

        $sort = array_key_exists($this->sort, Track::SORTS) ? $this->sort : 'artist';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw(Track::SORTS[$sort]." {$direction} nulls last")
            ->orderByRaw('lower(artist) asc nulls last')
            ->orderByRaw('lower(title) asc')
            ->orderBy('id')
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function totalTracks(): int
    {
        return Track::query()->count();
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function genres(): array
    {
        return Track::query()->whereNotNull('genre')->distinct()->orderBy('genre')->pluck('genre')->all();
    }

    /**
     * Camelot codes in the library in wheel order (1A, 1B, 2A … 12B).
     *
     * @return list<string>
     */
    #[Computed]
    public function camelotKeys(): array
    {
        return Track::query()
            ->whereNotNull('camelot_key')
            ->distinct()
            ->pluck('camelot_key')
            ->sortBy(fn (string $key): array => [(int) $key, substr($key, -1)])
            ->values()
            ->all();
    }

    /**
     * Artist, title or label containing the text (case-insensitive), or the
     * normalized key containing it without accents, so "niz" finds "niż".
     */
    private function applySearch(Builder $query): void
    {
        $search = Str::squish($this->search);

        if ($search === '') {
            return;
        }

        $normalized = Str::lower(Str::ascii($search));

        $query->where(fn (Builder $query) => $query
            ->whereAny(['artist', 'title', 'label'], 'ilike', '%'.$this->escapeLike($search).'%')
            ->orWhere('normalized_key', 'like', '%'.$this->escapeLike($normalized).'%'));
    }

    private function applyAvailability(Builder $query): void
    {
        foreach (array_intersect($this->availability, self::AVAILABILITY) as $availability) {
            match ($availability) {
                'pof' => $query->where('playable_offline', true),
                'pon' => $query->whereNotNull('youtube_url'),
                'wanted' => $query->where('wanted', true),
                'none' => $query->where('playable_offline', false)->whereNull('youtube_url')->where('wanted', false),
            };
        }
    }

    /**
     * Keep the POF / Wanted checkboxes of the open row in step after a change from the list.
     */
    private function syncExpandedFlags(): void
    {
        $track = $this->expandedTrackId === null ? null : Track::query()->find($this->expandedTrackId);

        if ($track !== null) {
            $this->edit['playable_offline'] = $track->playable_offline;
            $this->edit['wanted'] = $track->wanted;
        }
    }

    /**
     * Unused tags are gone: drop them from the filter too.
     */
    private function afterTagsRemoved(): void
    {
        unset($this->tracks, $this->expandedTrack, $this->allTags);

        $this->tagFilter = array_values(array_intersect(array_map('intval', $this->tagFilter), array_keys($this->allTags)));
    }

    private function number(string $value): ?int
    {
        return ctype_digit(trim($value)) ? (int) trim($value) : null;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    public function render(): View
    {
        return view('livewire.library.index');
    }
}
