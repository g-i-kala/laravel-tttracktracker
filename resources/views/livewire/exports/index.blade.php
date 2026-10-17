<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">Exports</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Every Supersimple TXT you generated, including older versions.</p>
    </div>

    @if ($notice)
        <p class="rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">{{ $notice }}</p>
    @endif

    @if ($this->exports->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No exports yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Generate a TXT on the YouTube links screen of an import.</p>
        </div>
    @else
        @php
            $selectedIds = array_map('intval', $selected);
            $allOnPageSelected = $this->exports->pluck('id')->diff($selectedIds)->isEmpty();
            $checkboxClasses = 'size-4 rounded border-zinc-300 text-accent-600 focus:ring-accent-500 dark:border-zinc-700';
        @endphp

        @if ($selectedIds !== [])
            <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-2.5 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                <span class="font-medium">{{ count($selectedIds) }} selected</span>
                <button type="button" wire:click="confirmDelete" class="rounded-md bg-red-600 px-3 py-1.5 font-semibold text-white hover:bg-red-500">Delete selected</button>
                <button type="button" wire:click="$set('selected', [])" class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">Clear selection</button>
            </div>
        @endif

        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="w-10 px-3 py-2">
                            <input type="checkbox" wire:click="toggleAllOnPage" @checked($allOnPageSelected) aria-label="Select all exports on this page" class="{{ $checkboxClasses }}">
                        </th>
                        <th class="px-3 py-2">File</th>
                        <th class="px-3 py-2">Import</th>
                        <th class="px-3 py-2 text-right">Version</th>
                        <th class="px-3 py-2 text-right">Links</th>
                        <th class="px-3 py-2">Exported</th>
                        <th class="w-36 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->exports as $export)
                        <tr wire:key="export-{{ $export->id }}" @class(['bg-accent-50/50 dark:bg-accent-950/30' => in_array($export->id, $selectedIds, true)])>
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $export->id }}" aria-label="Select {{ $export->filename }}" class="{{ $checkboxClasses }}">
                            </td>
                            <td class="px-3 py-2">
                                <span class="font-mono text-xs">{{ $export->filename }}</span>
                                @if (($this->latestVersions[$export->import_batch_id] ?? null) === $export->version)
                                    <span class="ml-2 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-medium text-accent-700 dark:bg-accent-950 dark:text-accent-300">Latest</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                <a href="{{ route('imports.show', $export->importBatch) }}" wire:navigate class="hover:text-accent-600 dark:hover:text-accent-400">{{ $export->importBatch->name }}</a>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">v{{ $export->version }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $export->items_count }}</td>
                            <td class="px-3 py-2 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $export->updated_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('exports.download', $export) }}" class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Download</a>
                                <button type="button" wire:click="confirmDelete({{ $export->id }})" class="ml-3 text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->exports->links() }}
    @endif
    <x-modal wire:model="showDeleteModal" title="Delete export {{ Str::plural('file', count($pendingDeleteIds)) }}">
        @php $pending = $this->pendingExports; @endphp
        <p>Delete {{ $pending->count() }} export {{ Str::plural('file', $pending->count()) }}? The imports and their links stay.</p>
        <ul class="mt-3 flex max-h-60 flex-col gap-1 overflow-y-auto font-mono text-xs text-zinc-600 dark:text-zinc-400">
            @foreach ($pending as $export)
                <li wire:key="pending-{{ $export->id }}">{{ $export->filename }}</li>
            @endforeach
        </ul>
        <p class="mt-3 font-medium text-red-600 dark:text-red-400">This cannot be undone.</p>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showDeleteModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="delete" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">Delete</button>
        </x-slot:footer>
    </x-modal>
</div>
