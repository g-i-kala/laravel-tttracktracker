{{-- "Add to library" modal for components using App\Livewire\Concerns\AddsToLibrary. --}}
@php
    $availableIds = $this->rows->whereNull('track_id')->pluck('id')->values();
@endphp
<x-modal wire:model="showLibraryModal" title="Add to library" max-width="2xl">
    @if (! $libraryChoosingSimilar)
        <p>Choose the tracks to add. A track already in the library is linked to it, and its empty fields are filled in.</p>

        <div class="mt-3 flex items-center gap-3 text-xs">
            <button type="button" x-on:click="$wire.librarySelection = @js($availableIds)" class="font-medium text-accent-600 hover:underline dark:text-accent-400">Select all not in library</button>
            <button type="button" x-on:click="$wire.librarySelection = []" class="font-medium text-zinc-500 hover:underline dark:text-zinc-400">Select none</button>
        </div>

        <ul class="mt-2 flex max-h-80 flex-col overflow-y-auto rounded-md border border-zinc-200 dark:border-zinc-800">
            @foreach ($this->rows as $item)
                <li wire:key="library-item-{{ $item->id }}" class="border-t border-zinc-200 first:border-t-0 dark:border-zinc-800">
                    <label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                        <input type="checkbox" value="{{ $item->id }}" wire:model="librarySelection" class="size-4 rounded border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700">
                        <span class="w-8 shrink-0 text-zinc-500 tabular-nums">{{ $item->line_number }}</span>
                        <span class="min-w-0 flex-1 truncate">{{ $item->artist ? $item->artist.' - ' : '' }}{{ $item->title }}</span>
                        @if ($item->track_id)
                            <x-in-library-badge />
                        @endif
                    </label>
                </li>
            @endforeach
        </ul>
        @error('librarySelection') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror

        <label class="mt-4 flex items-start gap-2">
            <input type="checkbox" wire:model="markAsWanted" class="mt-0.5 size-4 rounded border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700">
            <span>
                <span class="font-medium">Mark as wanted</span>
                <span class="block text-xs text-zinc-500 dark:text-zinc-400">You want a file of these tracks. Tracks you already have offline (POF) stay as they are.</span>
            </span>
        </label>
    @else
        <p>These tracks look like tracks already in the library (other version or artist order). Choose <span class="font-medium">Same track</span> only when it is really the same version.</p>

        <ul class="mt-3 flex max-h-96 flex-col gap-3 overflow-y-auto">
            @foreach ($this->librarySimilarMatches as $itemId => $tracks)
                @php $item = $this->rows->firstWhere('id', $itemId); @endphp
                <li wire:key="similar-{{ $itemId }}" class="rounded-md border border-zinc-200 px-3 py-2 dark:border-zinc-800">
                    <p class="font-medium">#{{ $item->line_number }} {{ $item->artist ? $item->artist.' - ' : '' }}{{ $item->title }}</p>
                    <div class="mt-2 flex flex-col gap-1.5">
                        <label class="flex items-center gap-2">
                            <input type="radio" value="new" wire:model="similarChoices.{{ $itemId }}" class="size-4 border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700">
                            <span>New track</span>
                        </label>
                        @foreach ($tracks as $track)
                            <label wire:key="similar-{{ $itemId }}-{{ $track->id }}" class="flex items-center gap-2">
                                <input type="radio" value="{{ $track->id }}" wire:model="similarChoices.{{ $itemId }}" class="size-4 border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700">
                                <span>Same track as <span class="font-medium">{{ $track->artist ? $track->artist.' - ' : '' }}{{ $track->title }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <x-slot:footer>
        @if ($libraryChoosingSimilar)
            <button type="button" wire:click="backToLibrarySelection" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Back</button>
        @else
            <button type="button" x-on:click="$wire.showLibraryModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
        @endif
        <button type="button" wire:click="addToLibrary" wire:loading.attr="disabled" wire:target="addToLibrary" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500 disabled:opacity-50">
            @if ($libraryChoosingSimilar)
                Add to library
            @else
                Add <span x-text="$wire.librarySelection.length"></span> <span x-text="$wire.librarySelection.length === 1 ? 'track' : 'tracks'"></span>
            @endif
        </button>
    </x-slot:footer>
</x-modal>
