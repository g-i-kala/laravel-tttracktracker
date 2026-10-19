<?php

namespace App\Livewire\Library;

use App\Models\Track;
use App\Services\LibraryService;
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
 * or for the ticked tracks.
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

    public function togglePlayableOffline(int $trackId, LibraryService $library): void
    {
        $track = Track::query()->findOrFail($trackId);

        $library->setPlayableOffline([$track->id], ! $track->playable_offline);

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
