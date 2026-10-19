@php
    $inputClasses = 'rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $tracks = $this->tracks;
    $availabilityLabels = ['pof' => 'POF', 'pon' => 'PON', 'wanted' => 'Wanted', 'none' => 'None'];
    $availabilityTitles = [
        'pof' => 'Playable offline: you have a file',
        'pon' => 'Playable online: has a link',
        'wanted' => 'You want a file',
        'none' => 'Not offline, not online, not wanted',
    ];
    $columns = ['artist' => 'Artist', 'title' => 'Title', 'genre' => 'Genre', 'bpm' => 'BPM', 'camelot' => 'Key', 'year' => 'Year', 'availability' => '', 'added' => 'Added'];
@endphp

<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">Library</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            @if ($this->hasFilters)
                {{ $tracks->total() }} of {{ $this->totalTracks }} {{ Str::plural('track', $this->totalTracks) }} match the filters.
            @else
                {{ $this->totalTracks }} {{ Str::plural('track', $this->totalTracks) }}. Add more from an import with <span class="font-medium">Add to library</span>.
            @endif
        </p>
    </div>

    @if ($this->totalTracks === 0)
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No tracks in the library yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Open an import and use <span class="font-medium">Add to library</span> on Parse Review or YouTube Results.</p>
            <a href="{{ route('imports.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">Go to imports &rarr;</a>
        </div>
    @else
        <div class="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex min-w-56 flex-1 flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Search
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Artist, title or label" class="{{ $inputClasses }} text-zinc-900 dark:text-zinc-100">
                </label>
                <label class="flex w-44 flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Genre
                    <select wire:model.live="genre" class="{{ $inputClasses }} text-zinc-900 dark:text-zinc-100">
                        <option value="">Any</option>
                        @foreach ($this->genres as $genreOption)
                            <option value="{{ $genreOption }}">{{ $genreOption }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex w-24 flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    Key
                    <select wire:model.live="camelot" class="{{ $inputClasses }} text-zinc-900 dark:text-zinc-100">
                        <option value="">Any</option>
                        @foreach ($this->camelotKeys as $camelotOption)
                            <option value="{{ $camelotOption }}">{{ $camelotOption }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <legend class="mb-1">BPM</legend>
                    <div class="flex items-center gap-1">
                        <input type="number" min="0" wire:model.live.debounce.400ms="bpmFrom" placeholder="from" aria-label="BPM from" class="{{ $inputClasses }} w-20 text-zinc-900 dark:text-zinc-100">
                        <span>–</span>
                        <input type="number" min="0" wire:model.live.debounce.400ms="bpmTo" placeholder="to" aria-label="BPM to" class="{{ $inputClasses }} w-20 text-zinc-900 dark:text-zinc-100">
                    </div>
                </fieldset>
                <fieldset class="flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <legend class="mb-1">Year</legend>
                    <div class="flex items-center gap-1">
                        <input type="number" min="0" wire:model.live.debounce.400ms="yearFrom" placeholder="from" aria-label="Year from" class="{{ $inputClasses }} w-24 text-zinc-900 dark:text-zinc-100">
                        <span>–</span>
                        <input type="number" min="0" wire:model.live.debounce.400ms="yearTo" placeholder="to" aria-label="Year to" class="{{ $inputClasses }} w-24 text-zinc-900 dark:text-zinc-100">
                    </div>
                </fieldset>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Availability:</span>
                @foreach ($availabilityLabels as $value => $label)
                    <button
                        type="button"
                        wire:click="toggleAvailability('{{ $value }}')"
                        title="{{ $availabilityTitles[$value] }}"
                        aria-pressed="{{ in_array($value, $availability, true) ? 'true' : 'false' }}"
                        @class([
                            'rounded-full border px-3 py-1 text-xs font-medium',
                            'border-accent-600 bg-accent-600 text-white' => in_array($value, $availability, true),
                            'border-zinc-300 text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800' => ! in_array($value, $availability, true),
                        ])
                    >{{ $label }}</button>
                @endforeach

                @if ($this->hasFilters)
                    <button type="button" wire:click="clearFilters" class="ml-auto text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">Clear filters</button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        @foreach ($columns as $column => $label)
                            <th @class(['px-3 py-2', 'text-right' => in_array($column, ['bpm', 'year'], true)])>
                                @if (in_array($column, ['artist', 'title', 'bpm', 'camelot', 'year', 'added'], true))
                                    <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase hover:text-zinc-900 dark:hover:text-zinc-100">
                                        {{ $label }}
                                        @if ($sort === $column)
                                            <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                            <span class="sr-only">sorted {{ $direction === 'asc' ? 'ascending' : 'descending' }}</span>
                                        @endif
                                    </button>
                                @elseif ($label !== '')
                                    {{ $label }}
                                @else
                                    <span class="sr-only">Availability</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($tracks as $track)
                        <tr wire:key="track-{{ $track->id }}" class="align-top">
                            <td class="px-3 py-2 font-medium">{{ $track->artist ?? '—' }}</td>
                            <td class="px-3 py-2">
                                {{ $track->title }}
                                @if ($track->label)
                                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $track->label }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-zinc-600 dark:text-zinc-400">{{ $track->genre }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $track->bpm }}</td>
                            <td class="px-3 py-2 whitespace-nowrap" title="{{ $track->musical_key }}">{{ $track->camelot_key ?? $track->musical_key }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $track->release_year }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="flex gap-1">
                                    @if ($track->playable_offline)
                                        <span title="Playable offline" class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">POF</span>
                                    @endif
                                    @if ($track->isPlayableOnline())
                                        <a href="{{ $track->youtube_url }}" target="_blank" rel="noopener" title="Playable online: open on YouTube" class="rounded bg-accent-100 px-1.5 py-0.5 text-xs font-medium text-accent-800 hover:bg-accent-200 dark:bg-accent-900/60 dark:text-accent-200">PON</a>
                                    @endif
                                    @if ($track->wanted)
                                        <span title="You want a file" class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">Wanted</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $track->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}" class="px-3 py-8 text-center text-zinc-500">
                                No tracks match the filters.
                                <button type="button" wire:click="clearFilters" class="ml-1 font-medium text-accent-600 hover:underline dark:text-accent-400">Clear filters</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $tracks->links() }}
    @endif
</div>
