@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $withoutArtist = $this->rows->whereNull('artist')->count();
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Parse Review</p>
            <h1 class="text-2xl font-semibold">{{ $batch->name }}</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                {{ $this->rows->count() }} {{ Str::plural('track', $this->rows->count()) }}
                @if ($withoutArtist > 0)
                    · <span class="text-amber-600 dark:text-amber-400">{{ $withoutArtist }} without artist</span>
                @endif
                @if ($batch->year || $batch->month)
                    · {{ $batch->month ? now()->startOfYear()->month($batch->month)->format('F') : '' }} {{ $batch->year }}
                @endif
                @if ($batch->default_genre)
                    · {{ $batch->default_genre }}
                @endif
            </p>
        </div>

        <div class="flex gap-2">
            @unless (in_array($batch->status, [\App\Enums\ImportBatchStatus::Parsing, \App\Enums\ImportBatchStatus::Failed], true))
                <button
                    type="button"
                    wire:click="saveAndClose"
                    class="rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                >
                    Save &amp; close
                </button>
            @endunless

            <button
                type="button"
                disabled
                title="Coming soon"
                class="cursor-not-allowed rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white opacity-50"
            >
                Search YouTube
            </button>
        </div>
    </div>

    @if ($batch->status === \App\Enums\ImportBatchStatus::Parsing)
        <div wire:poll.2s="checkParsing" class="flex items-center gap-3 rounded-lg border border-accent-200 bg-accent-50 px-4 py-6 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">
            <svg class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <span>Parsing your track list with AI… This can take up to a few minutes for long lists.</span>
        </div>
    @elseif ($batch->status === \App\Enums\ImportBatchStatus::Failed)
        <div class="flex flex-col gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800 sm:flex-row sm:items-center sm:justify-between dark:border-red-900 dark:bg-red-950 dark:text-red-200">
            <div>
                <span>AI parsing failed. Try again, or parse the list line by line (<code>Artist - Title</code>).</span>
                @error('retry') <p class="mt-1 font-medium">{{ $message }} <a href="{{ route('settings.ai') }}" wire:navigate class="underline">Open settings</a></p> @enderror
            </div>
            <div class="flex gap-2">
                <button type="button" wire:click="retryAiParsing" class="rounded-md bg-accent-600 px-3 py-1.5 font-semibold text-white hover:bg-accent-500">Try again with AI</button>
                <button type="button" wire:click="useLineParser" class="rounded-md border border-zinc-300 px-3 py-1.5 font-medium hover:bg-white dark:border-zinc-700 dark:hover:bg-zinc-900">Use simple parser</button>
            </div>
        </div>
    @else
    <p class="text-sm text-zinc-600 dark:text-zinc-400">Changes are saved automatically when you leave a field.</p>

    <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                <tr>
                    <th class="w-12 px-3 py-2">#</th>
                    <th class="px-3 py-2">Artist</th>
                    <th class="px-3 py-2">Title</th>
                    <th class="w-48 px-3 py-2">Genre</th>
                    <th class="w-28 px-3 py-2"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                @forelse ($this->rows as $item)
                    <tr wire:key="item-{{ $item->id }}" class="align-top">
                        <td class="px-3 py-2 pt-3.5 text-zinc-500 tabular-nums">{{ $item->line_number }}</td>
                        <td class="px-3 py-2">
                            <input type="text" wire:model.blur="items.{{ $item->id }}.artist" aria-label="Artist" @class([$inputClasses, 'border-amber-400 dark:border-amber-600' => $item->artist === null])>
                            @error("items.{$item->id}.artist") <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            @if ($item->error_message)
                                <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">{{ $item->error_message }}</p>
                            @endif
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" wire:model.blur="items.{{ $item->id }}.title" aria-label="Title" class="{{ $inputClasses }}">
                            @error("items.{$item->id}.title") <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            <p class="mt-1 truncate font-mono text-xs text-zinc-400 dark:text-zinc-500" title="{{ $item->raw_line }}">{{ $item->raw_line }}</p>
                        </td>
                        <td class="px-3 py-2">
                            <input type="text" wire:model.blur="items.{{ $item->id }}.genre" aria-label="Genre" class="{{ $inputClasses }}">
                            @error("items.{$item->id}.genre") <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </td>
                        <td class="px-3 py-2 pt-3 text-right whitespace-nowrap">
                            @if ($savedItemId === $item->id)
                                <span wire:key="saved-{{ $item->id }}-{{ $item->updated_at?->timestamp }}" x-data x-init="setTimeout(() => $el.remove(), 1500)" class="mr-2 text-xs text-accent-600 dark:text-accent-400">Saved</span>
                            @endif
                            <button
                                type="button"
                                wire:click="removeItem({{ $item->id }})"
                                wire:confirm="Remove this track?"
                                class="text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400"
                            >
                                Remove
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-3 py-8 text-center text-zinc-500">No tracks left in this import.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @endif
</div>
