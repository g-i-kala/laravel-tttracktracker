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

        <button
            type="button"
            disabled
            title="Coming soon"
            class="cursor-not-allowed rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white opacity-50"
        >
            Search YouTube
        </button>
    </div>

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
</div>
