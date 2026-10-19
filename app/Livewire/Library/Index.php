<?php

namespace App\Livewire\Library;

use App\Models\Track;
use App\Services\AiTrackListParserService;
use App\Services\LibraryService;
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
 * filters (availability, genre, Camelot, BPM and year ranges) and sortable
 * columns. Filters and sorting live in the URL. POF can be switched per row
 * or for the ticked tracks. Clicking a row expands it to edit the track
 * (autosaved on blur) and shows the imports it came from.
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

    /**
     * Sortable columns => SQL used for ordering (nulls always last).
     *
     * @var array<string, string>
     */
    private const array SORTS = [
        'artist' => 'lower(artist)',
        'title' => 'lower(title)',
        'bpm' => 'bpm',
        'camelot' => "cast(substring(camelot_key from '^[0-9]+') as integer)",
        'year' => 'release_year',
        'added' => 'created_at',
    ];

    private const array FILTERS = ['search', 'availability', 'genre', 'camelot', 'bpmFrom', 'bpmTo', 'yearFrom', 'yearTo'];

    /**
     * Ticked track ids (kept across pages and filters).
     *
     * @var list<int|string>
     */
    public array $selected = [];

    public ?string $notice = null;

    public ?int $expandedTrackId = null;

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
        if (! array_key_exists($column, self::SORTS)) {
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
                $this->addError("edit.{$key}", 'Already in the library as “'.($conflict->artist ? $conflict->artist.' - ' : '').$conflict->title.'”. Merging tracks comes in a later step.');

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
     * The expanded track with its sources (import items and their imports).
     */
    #[Computed]
    public function expandedTrack(): ?Track
    {
        return $this->expandedTrackId === null
            ? null
            : Track::query()
                ->with(['importItems' => fn ($query) => $query->with('importBatch')->orderBy('import_batch_id')->orderBy('line_number')])
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
        $query = Track::query();

        $this->applySearch($query);
        $this->applyAvailability($query);

        $query
            ->when($this->genre !== '', fn (Builder $query) => $query->where('genre', $this->genre))
            ->when($this->camelot !== '', fn (Builder $query) => $query->where('camelot_key', $this->camelot))
            ->when($this->number($this->bpmFrom) !== null, fn (Builder $query) => $query->where('bpm', '>=', $this->number($this->bpmFrom)))
            ->when($this->number($this->bpmTo) !== null, fn (Builder $query) => $query->where('bpm', '<=', $this->number($this->bpmTo)))
            ->when($this->number($this->yearFrom) !== null, fn (Builder $query) => $query->where('release_year', '>=', $this->number($this->yearFrom)))
            ->when($this->number($this->yearTo) !== null, fn (Builder $query) => $query->where('release_year', '<=', $this->number($this->yearTo)));

        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'artist';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        return $query
            ->orderByRaw(self::SORTS[$sort]." {$direction} nulls last")
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
