@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Playlists</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Sets built from your library tracks, in your own order.</p>
        </div>

        <button type="button" x-on:click="$wire.showCreateModal = true" class="rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-500">+ New playlist</button>
    </div>

    @if ($notice)
        <p class="rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">{{ $notice }}</p>
    @endif

    @if ($this->playlists->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No playlists yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Create one, e.g. “80s party”, and fill it with tracks from your library.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2 text-right">Tracks</th>
                        <th class="px-3 py-2">Updated</th>
                        <th class="w-40 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->playlists as $playlist)
                        <tr wire:key="playlist-{{ $playlist->id }}">
                            <td class="px-3 py-2">
                                <a href="{{ route('playlists.show', $playlist) }}" wire:navigate class="font-medium hover:text-accent-600 dark:hover:text-accent-400">{{ $playlist->name }}</a>
                                @if ($playlist->notes)
                                    <p class="mt-0.5 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ Str::limit($playlist->notes, 120) }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $playlist->tracks_count }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $playlist->updated_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('playlists.show', $playlist) }}" wire:navigate class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Open</a>
                                <button type="button" wire:click="confirmDelete({{ $playlist->id }})" class="ml-3 text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->playlists->links() }}
    @endif

    <x-modal wire:model="showCreateModal" title="New playlist">
        <div class="flex flex-col gap-3">
            <label class="flex flex-col gap-1 text-sm font-medium">
                Name
                <input type="text" wire:model="name" maxlength="255" placeholder="80s party" x-on:keydown.enter.prevent="$wire.create()" class="{{ $inputClasses }} font-normal">
                @error('name') <span class="text-xs font-normal text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
            </label>
            <label class="flex flex-col gap-1 text-sm font-medium">
                Notes <span class="font-normal text-zinc-500 dark:text-zinc-400">(optional)</span>
                <textarea wire:model="notes" rows="3" placeholder="Date, venue, ideas…" class="{{ $inputClasses }} font-normal"></textarea>
            </label>
        </div>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showCreateModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="create" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500">Create</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showDeleteModal" title="Delete playlist">
        @if ($this->pendingDelete)
            <p>Delete <span class="font-medium">“{{ $this->pendingDelete->name }}”</span> ({{ $this->pendingDelete->tracks_count }} {{ Str::plural('track', $this->pendingDelete->tracks_count) }})?</p>
            <p class="mt-2 text-zinc-600 dark:text-zinc-400">The tracks stay in the library; only the playlist and its order are removed.</p>
        @endif

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showDeleteModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="delete" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">Delete</button>
        </x-slot:footer>
    </x-modal>
</div>
