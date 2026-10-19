@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $tracks = $this->tracks;
    $isManual = $sort === null;
    $columns = ['artist' => 'Artist', 'title' => 'Title', 'bpm' => 'BPM', 'camelot' => 'Key', 'year' => 'Year', 'rating' => 'Rating'];
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm text-zinc-500 dark:text-zinc-400"><a href="{{ route('playlists.index') }}" wire:navigate class="hover:underline">Playlists</a></p>
            <h1 class="text-2xl font-semibold">{{ $playlist->name }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $tracks->count() }} {{ Str::plural('track', $tracks->count()) }}</p>
            @if ($playlist->notes)
                <p class="mt-2 max-w-2xl text-sm whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $playlist->notes }}</p>
            @endif
        </div>

        <button type="button" wire:click="openEditModal" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Edit name &amp; notes</button>
    </div>

    @if ($notice)
        <p class="rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">{{ $notice }}</p>
    @endif

    @if ($tracks->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No tracks on this playlist yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Playlists hold tracks from your library.</p>
            <a href="{{ route('library.index') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-accent-600 hover:underline dark:text-accent-400">Go to library &rarr;</a>
        </div>
    @else
        @unless ($isManual)
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
                <span>Sorted by {{ strtolower($columns[$sort]) }} ({{ $direction === 'asc' ? 'ascending' : 'descending' }}). This is only a view; dragging is off.</span>
                <button type="button" wire:click="applyOrder" class="rounded-md bg-accent-600 px-3 py-1.5 font-semibold text-white hover:bg-accent-500">Apply this order</button>
                <button type="button" wire:click="manualOrder" class="font-medium underline hover:no-underline">Back to playlist order</button>
            </div>
        @else
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Drag the handle (⋮⋮) to change the order. Click a column to sort the view.</p>
        @endunless

        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="w-10 px-3 py-2"><span class="sr-only">Move</span></th>
                        <th class="w-12 px-3 py-2">
                            <button type="button" wire:click="manualOrder" class="inline-flex items-center gap-1 uppercase hover:text-zinc-900 dark:hover:text-zinc-100">
                                # @if ($isManual) <span aria-hidden="true">↑</span> @endif
                            </button>
                        </th>
                        @foreach ($columns as $column => $label)
                            <th @class(['px-3 py-2', 'text-right' => in_array($column, ['bpm', 'year'], true)])>
                                <button type="button" wire:click="sortBy('{{ $column }}')" class="inline-flex items-center gap-1 uppercase hover:text-zinc-900 dark:hover:text-zinc-100">
                                    {{ $label }}
                                    @if ($sort === $column)
                                        <span aria-hidden="true">{{ $direction === 'asc' ? '↑' : '↓' }}</span>
                                    @endif
                                </button>
                            </th>
                        @endforeach
                        <th class="px-3 py-2"><span class="sr-only">Availability</span></th>
                        <th class="w-20 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800" @if ($isManual) wire:sort="moveTrack" @endif>
                    @foreach ($tracks as $track)
                        <tr wire:key="playlist-track-{{ $track->id }}" @if ($isManual) wire:sort:item="{{ $track->id }}" @endif class="bg-white align-top dark:bg-zinc-900">
                            <td class="px-3 py-2">
                                @if ($isManual)
                                    <span wire:sort:handle class="cursor-grab px-1 text-zinc-400 select-none hover:text-zinc-700 active:cursor-grabbing dark:hover:text-zinc-200" title="Drag to move" aria-label="Drag to move">⋮⋮</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-zinc-500 tabular-nums">{{ $track->pivot->position }}</td>
                            <td class="px-3 py-2 font-medium">{{ $track->artist ?? '—' }}</td>
                            <td class="px-3 py-2">
                                {{ $track->title }}
                                @if ($track->tags->isNotEmpty())
                                    <p class="mt-1 flex flex-wrap gap-1">
                                        @foreach ($track->tags as $tag)
                                            <span wire:key="pt-{{ $track->id }}-tag-{{ $tag->id }}" class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">#{{ $tag->name }}</span>
                                        @endforeach
                                    </p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $track->bpm }}</td>
                            <td class="px-3 py-2 whitespace-nowrap" title="{{ $track->musical_key }}">{{ $track->camelot_key ?? $track->musical_key }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $track->release_year }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-amber-500" aria-label="{{ $track->rating ? $track->rating.' of 5 stars' : 'Not rated' }}">{{ $track->rating ? str_repeat('★', $track->rating) : '' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <div class="flex gap-1">
                                    @if ($track->playable_offline)
                                        <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200">POF</span>
                                    @endif
                                    @if ($track->isPlayableOnline())
                                        <a href="{{ $track->youtube_url }}" target="_blank" rel="noopener" title="Open on YouTube" class="rounded bg-accent-100 px-1.5 py-0.5 text-xs font-medium text-accent-800 hover:bg-accent-200 dark:bg-accent-900/60 dark:text-accent-200">PON</a>
                                    @endif
                                    @if ($track->wanted)
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/60 dark:text-amber-200">Wanted</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" wire:click="removeTrack({{ $track->id }})" wire:confirm="Remove this track from the playlist? It stays in the library." class="text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">Remove</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <x-modal wire:model="showEditModal" title="Edit playlist">
        <div class="flex flex-col gap-3">
            <label class="flex flex-col gap-1 text-sm font-medium">
                Name
                <input type="text" wire:model="name" maxlength="255" class="{{ $inputClasses }} font-normal">
                @error('name') <span class="text-xs font-normal text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium">
                Notes
                <textarea wire:model="notes" rows="4" class="{{ $inputClasses }} font-normal"></textarea>
                @error('notes') <span class="text-xs font-normal text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </label>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showEditModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="saveDetails" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500">Save</button>
        </x-slot:footer>
    </x-modal>
</div>
