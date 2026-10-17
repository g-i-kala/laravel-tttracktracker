{{-- "Add tracks" modal for components using App\Livewire\Concerns\AddsTracks. --}}
<x-modal wire:model="showAddTracksModal" title="Add tracks" max-width="2xl">
    <p>
        @if ($this->addTracksUsesAi)
            Paste more tracks in any format, e.g. one per line or copied from a store like Beatport (parsed with AI).
        @else
            Paste more tracks, one per line (<span class="font-mono text-xs">Artist - Title</span>).
        @endif
        They are added at the end; tracks already in this import are skipped.
    </p>
    <textarea
        wire:model="addTracksInput"
        rows="8"
        aria-label="Tracks to add"
        placeholder="Artist - Title"
        @class([
            'mt-3 w-full rounded-md border border-zinc-300 bg-white px-3 py-2 font-mono text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900',
            'border-red-400 dark:border-red-600' => $errors->has('addTracksInput'),
        ])
    ></textarea>
    @error('addTracksInput') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
    <p wire:loading wire:target="addTracks" class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
        {{ $this->addTracksUsesAi ? 'Parsing with AI… this can take a while for long lists.' : 'Adding…' }}
    </p>

    <x-slot:footer>
        <button type="button" x-on:click="$wire.showAddTracksModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
        @if ($addTracksAiFailed)
            <button type="button" wire:click="addTracks(true)" wire:loading.attr="disabled" wire:target="addTracks" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100 disabled:opacity-50 dark:border-zinc-700 dark:hover:bg-zinc-800">Use simple parser</button>
        @endif
        <button type="button" wire:click="addTracks" wire:loading.attr="disabled" wire:target="addTracks" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500 disabled:opacity-50">Add tracks</button>
    </x-slot:footer>
</x-modal>
