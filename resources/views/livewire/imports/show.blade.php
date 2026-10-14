<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">YouTube Results</p>
            <h1 class="text-2xl font-semibold">{{ $batch->name }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->rows->count() }} {{ Str::plural('track', $this->rows->count()) }}
                @if ($batch->year || $batch->month)
                    · {{ $batch->month ? now()->startOfYear()->month($batch->month)->format('F') : '' }} {{ $batch->year }}
                @endif
                @if ($batch->default_genre)
                    · {{ $batch->default_genre }}
                @endif
            </p>
        </div>

        <div class="flex gap-2">
            <a
                href="{{ route('imports.review', $batch) }}"
                wire:navigate
                class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                Back to review
            </a>

            <button
                type="button"
                disabled
                title="Coming soon"
                class="cursor-not-allowed rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white opacity-50"
            >
                Generate TXT
            </button>
        </div>
    </div>

    <p class="text-sm text-zinc-600 dark:text-zinc-400">
        Open the YouTube search for a track (new tab) and pick the right video. Pasting its link here comes in the next step.
    </p>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="w-12 px-3 py-2">#</th>
                    <th class="px-3 py-2">Track</th>
                    <th class="w-36 px-3 py-2"><span class="sr-only">YouTube search</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->rows as $item)
                    <tr wire:key="item-{{ $item->id }}" class="align-top">
                        <td class="px-3 py-2.5 text-zinc-500 tabular-nums">{{ $item->line_number }}</td>
                        <td class="px-3 py-2.5">
                            <p>
                                @if ($item->artist)
                                    <span class="font-medium">{{ $item->artist }}</span> -
                                @endif
                                {{ $item->title }}
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
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-8 text-center text-zinc-500">No tracks in this import.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
