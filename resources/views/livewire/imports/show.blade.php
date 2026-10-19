@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $withLink = $this->rows->whereNotNull('selected_youtube_url')->count();
    $included = $this->exportableCount;
    $missingLinks = $this->rows->whereNull('selected_youtube_url');
    $latestExport = $this->latestExport;
    $isSearching = $batch->status === \App\Enums\ImportBatchStatus::Searching;
    $inLibrary = $this->rows->whereNotNull('track_id')->count();
    $readyCount = $this->rows->count() - count($pendingItemIds);
    $searchNotices = [\App\Services\ImportItemService::SEARCH_FAILED_NOTICE, \App\Services\ImportItemService::QUOTA_EXCEEDED_NOTICE];
    $spinner = '<svg class="size-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>';
@endphp

<div class="flex flex-col gap-6" x-data="{ trackList: null, trackListOpen: false }" x-on:download-export.window="window.location.href = $event.detail.url">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">YouTube Results</p>
            <h1 class="text-2xl font-semibold">{{ $batch->name }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->rows->count() }} {{ Str::plural('track', $this->rows->count()) }}
                · {{ $withLink }} with a link · {{ $included }} in export
                @if ($inLibrary > 0)
                    · {{ $inLibrary }} in library
                @endif
                @if ($batch->year || $batch->month)
                    · {{ $batch->month ? now()->startOfYear()->month($batch->month)->format('F') : '' }} {{ $batch->year }}
                @endif
                @if ($batch->default_genre)
                    · {{ $batch->default_genre }}
                @endif
            </p>
            @if ($this->youtubeQuota)
                <x-youtube-quota :quota="$this->youtubeQuota" class="mt-1" />
            @endif
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                x-on:click="$wire.showAddTracksModal = true"
                @disabled($isSearching)
                title="{{ $isSearching ? 'Wait until the YouTube search is finished' : 'Paste more tracks into this import' }}"
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Add tracks
            </button>
            <button
                type="button"
                wire:click="openLibraryModal"
                @disabled($isSearching || $this->rows->isEmpty())
                title="{{ $isSearching ? 'Wait until the YouTube search is finished' : 'Add tracks with their links to the music library' }}"
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Add to library
            </button>
            <a
                href="{{ route('imports.review', $batch) }}"
                wire:navigate
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Back to review
            </a>
            <x-track-list-button :batch="$batch" :count="$this->rows->count()" class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800" />

            <button
                type="button"
                wire:click="export"
                wire:loading.attr="disabled"
                wire:target="export, exportAnyway, confirmExport"
                @disabled($included === 0)
                title="{{ $included === 0 ? 'Paste at least one link first' : 'Download a TXT with the included links' }}"
                class="rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                Generate TXT
            </button>
        </div>
    </div>

    @if ($notice)
        <p class="rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">{{ $notice }}</p>
    @endif

    @if ($isSearching)
        <div wire:poll.2s="checkSearchProgress" class="flex flex-col gap-2 rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">
            <div class="flex items-center gap-3">
                {!! $spinner !!}
                <span>Searching YouTube… <span class="font-medium tabular-nums">{{ $readyCount }} of {{ $this->rows->count() }}</span> tracks ready. Links appear in the rows as they are found.</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-accent-200 dark:bg-accent-900" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $this->rows->count() }}" aria-valuenow="{{ $readyCount }}">
                <div class="h-full rounded-full bg-accent-600 transition-all dark:bg-accent-400" style="width: {{ $this->rows->count() > 0 ? round($readyCount / $this->rows->count() * 100) : 0 }}%"></div>
            </div>
        </div>
    @endif

    @if ($searchSummary)
        <div class="flex items-start justify-between gap-4 rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">
            <p>
                <span class="font-medium">YouTube search finished:</span>
                {{ $searchSummary['linked'] }} linked and included
                · {{ $searchSummary['to_check'] }} to check (link found, not included)
                · {{ $searchSummary['not_found'] }} not found
                @if ($searchSummary['failed'] > 0)
                    · <span class="text-amber-700 dark:text-amber-300">{{ $searchSummary['failed'] }} failed (use the search link)</span>
                @endif
            </p>
            <button type="button" wire:click="dismissSearchSummary" class="shrink-0 rounded px-1 text-accent-700 hover:bg-accent-100 dark:text-accent-300 dark:hover:bg-accent-900" aria-label="Dismiss">✕</button>
        </div>
    @endif

    @if ($latestExport)
        <div class="flex flex-col gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 sm:flex-row sm:items-center sm:justify-between dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            <span>
                Exported {{ $latestExport->items_count }} {{ Str::plural('link', $latestExport->items_count) }}
                · <span class="font-mono text-xs">{{ $latestExport->filename }}</span>
                · {{ $latestExport->updated_at->format('Y-m-d H:i') }}
            </span>
            <a href="{{ route('exports.download', $latestExport) }}" class="self-start font-medium underline hover:no-underline sm:self-auto">Download again</a>
        </div>
    @endif

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        Open the YouTube search for a track (new tab), copy the link of the right video and paste it into the track's row. It is saved right away and the track is included in the export.
    </p>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="w-12 px-3 py-2">#</th>
                    <th class="px-3 py-2">Track</th>
                    <th class="w-36 px-3 py-2"><span class="sr-only">YouTube search</span></th>
                    <th class="w-80 px-3 py-2">YouTube link</th>
                    <th class="w-20 px-3 py-2 text-center">Export</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->rows as $item)
                    <tr
                        wire:key="item-{{ $item->id }}-{{ $item->status->value }}"
                        x-data="{ saved: false, savedTimer: null, candidatesOpen: @js($item->youtubeCandidates->isNotEmpty() && ! $item->include_in_export) }"
                        x-on:item-saved.window="if ($event.detail.id === {{ $item->id }}) { saved = true; clearTimeout(savedTimer); savedTimer = setTimeout(() => saved = false, 1500) }"
                        class="align-top"
                    >
                        <td class="px-3 py-2.5 text-zinc-500 tabular-nums">{{ $item->line_number }}</td>
                        <td class="px-3 py-2.5">
                            <p>
                                @if ($item->artist)
                                    <span class="font-medium">{{ $item->artist }}</span> -
                                @endif
                                {{ $item->title }}
                                @if ($item->track_id)
                                    <x-in-library-badge class="ml-1" />
                                @endif
                            </p>
                            @php
                                $metadata = array_filter([
                                    $item->bpm ? "{$item->bpm} BPM" : null,
                                    $item->camelot_key,
                                    $item->label,
                                ]);
                            @endphp
                            @if ($metadata)
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ implode(' · ', $metadata) }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <a
                                href="{{ $this->searchUrls[$item->id] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                            >
                                Search YouTube <span aria-hidden="true">↗</span>
                            </a>
                        </td>
                        <td class="px-3 py-2">
                            @if ($item->status === \App\Enums\ImportItemStatus::SearchPending)
                                <p class="flex items-center gap-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">{!! $spinner !!} Searching…</p>
                            @else
                                <input
                                    type="url"
                                    inputmode="url"
                                    placeholder="Paste YouTube link"
                                    aria-label="YouTube link"
                                    wire:model.live.blur="links.{{ $item->id }}"
                                    x-on:input="if ($event.inputType === 'insertFromPaste') $el.blur()"
                                    @class([$inputClasses, 'border-red-400 dark:border-red-600' => $errors->has("links.{$item->id}")])
                                >
                                @error("links.{$item->id}") <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
                                    @if ($item->selected_youtube_url)
                                        <a href="{{ $item->selected_youtube_url }}" target="_blank" rel="noopener noreferrer" class="font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Watch ↗</a>
                                    @endif
                                    @if ($item->youtube_score !== null)
                                        <span class="text-zinc-500 dark:text-zinc-400">score {{ $item->youtube_score }}</span>
                                    @endif
                                    @if ($item->youtubeCandidates->isNotEmpty())
                                        <button type="button" x-on:click="candidatesOpen = ! candidatesOpen" x-bind:aria-expanded="candidatesOpen" class="font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">
                                            {{ $item->youtubeCandidates->count() }} {{ Str::plural('candidate', $item->youtubeCandidates->count()) }}
                                            <svg class="inline size-3 transition-transform" x-bind:class="candidatesOpen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                                        </button>
                                    @endif
                                    <span x-show="saved" x-cloak x-transition.opacity class="text-accent-600 dark:text-accent-400">Saved</span>
                                </p>
                                @if ($item->youtubeCandidates->isNotEmpty())
                                    <ul x-show="candidatesOpen" x-cloak class="mt-2 flex flex-col gap-1.5">
                                        @foreach ($item->youtubeCandidates as $candidate)
                                            @php($isSelected = $candidate->video_id === $item->selected_youtube_video_id)
                                            <li wire:key="candidate-{{ $candidate->id }}" class="flex items-start gap-2 text-xs">
                                                <input
                                                    type="radio"
                                                    name="candidate-{{ $item->id }}"
                                                    aria-label="Use this video"
                                                    wire:click="selectCandidate({{ $item->id }}, {{ $candidate->id }})"
                                                    @checked($isSelected)
                                                    class="mt-0.5 size-3.5 shrink-0 border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700"
                                                >
                                                <span @class([
                                                    'w-8 shrink-0 rounded px-1 py-0.5 text-center font-medium tabular-nums',
                                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' => $candidate->score >= config('services.youtube.auto_include_threshold'),
                                                    'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' => $candidate->score < config('services.youtube.auto_include_threshold') && $candidate->score >= config('services.youtube.preselect_threshold'),
                                                    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => $candidate->score < config('services.youtube.preselect_threshold'),
                                                ]) title="Score">{{ $candidate->score }}</span>
                                                <span class="min-w-0">
                                                    <a href="{{ $candidate->url }}" target="_blank" rel="noopener noreferrer" @class(['break-words hover:underline', 'font-medium' => $isSelected])>{{ $candidate->title }} <span aria-hidden="true">↗</span></a>
                                                    @if ($candidate->channel_title)
                                                        <span class="block text-zinc-500 dark:text-zinc-400">{{ $candidate->channel_title }}</span>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (in_array($item->error_message, $searchNotices, true))
                                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $item->error_message }}</p>
                                @endif
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-center">
                            <input
                                type="checkbox"
                                aria-label="Include in export"
                                title="{{ $item->selected_youtube_url ? 'Include in export' : 'Paste a link first' }}"
                                wire:click="toggleExport({{ $item->id }})"
                                @checked($item->include_in_export && $item->selected_youtube_url)
                                @disabled(! $item->selected_youtube_url || $item->status === \App\Enums\ImportItemStatus::SearchPending)
                                class="size-4 rounded border-zinc-300 text-accent-600 focus:ring-accent-500 disabled:cursor-not-allowed disabled:opacity-40 dark:border-zinc-700"
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-zinc-500">No tracks in this import.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-modal wire:model="showMissingLinksModal" title="Some tracks have no link">
        <p>
            {{ $missingLinks->count() }} {{ Str::plural('track', $missingLinks->count()) }} {{ $missingLinks->count() === 1 ? 'has' : 'have' }} no YouTube link and will be skipped.
            The TXT will contain {{ $included }} {{ Str::plural('link', $included) }}.
        </p>
        <ul class="mt-3 flex max-h-60 flex-col gap-1 overflow-y-auto text-zinc-600 dark:text-zinc-400">
            @foreach ($missingLinks as $item)
                <li wire:key="missing-{{ $item->id }}">#{{ $item->line_number }} {{ $item->artist ? $item->artist.' - ' : '' }}{{ $item->title }}</li>
            @endforeach
        </ul>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showMissingLinksModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Add links first</button>
            <button type="button" wire:click="exportAnyway" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500">Export anyway</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showVersionModal" title="Export already exists">
        @if ($latestExport)
            <p>
                <span class="font-mono text-xs">{{ $latestExport->filename }}</span> was exported on
                {{ $latestExport->updated_at->format('Y-m-d H:i') }} with {{ $latestExport->items_count }} {{ Str::plural('link', $latestExport->items_count) }}.
            </p>
            <p class="mt-2">Create a new version (the old file stays) or overwrite the latest one?</p>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showVersionModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="confirmExport('overwrite')" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800">Overwrite latest</button>
            <button type="button" wire:click="confirmExport('new')" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500">Create new version</button>
        </x-slot:footer>
    </x-modal>

    @include('livewire.imports.partials.add-tracks-modal')
    @include('livewire.imports.partials.add-to-library-modal')
    @include('livewire.imports.partials.track-list-modal')
</div>
