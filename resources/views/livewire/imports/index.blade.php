<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Imports</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Your track lists. Continue any of them where you left off.</p>
        </div>

        <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>
    </div>

    @if ($notice)
        <p class="rounded-lg border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">{{ $notice }}</p>
    @endif

    @if ($this->batches->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No imports yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Paste a track list to create your first import.</p>
        </div>
    @else
        @php
            $selectedIds = array_map('intval', $selected);
            $allOnPageSelected = $this->batches->pluck('id')->diff($selectedIds)->isEmpty();
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
                            <input type="checkbox" wire:click="toggleAllOnPage" @checked($allOnPageSelected) aria-label="Select all imports on this page" class="{{ $checkboxClasses }}">
                        </th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Created</th>
                        <th class="px-3 py-2 text-right">Tracks</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-64 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->batches as $batch)
                        <tr wire:key="batch-{{ $batch->id }}" @class(['bg-accent-50/50 dark:bg-accent-950/30' => in_array($batch->id, $selectedIds, true)])>
                            <td class="px-3 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $batch->id }}" aria-label="Select {{ $batch->name }}" class="{{ $checkboxClasses }}">
                            </td>
                            <td class="px-3 py-2 font-medium">
                                <a href="{{ route($batch->status->screenRouteName(), $batch) }}" wire:navigate class="hover:text-accent-600 dark:hover:text-accent-400">{{ $batch->name }}</a>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-zinc-600 dark:text-zinc-400">{{ $batch->created_at->format('Y-m-d H:i') }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $batch->items_count }}</td>
                            <td class="px-3 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' => $batch->status === \App\Enums\ImportBatchStatus::Failed,
                                    'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' => $batch->status === \App\Enums\ImportBatchStatus::Parsed,
                                    'bg-accent-100 text-accent-700 dark:bg-accent-950 dark:text-accent-300' => ! in_array($batch->status, [\App\Enums\ImportBatchStatus::Failed, \App\Enums\ImportBatchStatus::Parsed], true),
                                ])>{{ $batch->status->label() }}</span>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ route('imports.review', $batch) }}" wire:navigate class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Review</a>
                                @if ($batch->status->hasSearchResults())
                                    <a href="{{ route('imports.show', $batch) }}" wire:navigate class="ml-3 text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">YouTube links</a>
                                @endif
                                <button type="button" wire:click="confirmDelete({{ $batch->id }})" class="ml-3 text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->batches->links() }}
    @endif
    <x-modal wire:model="showDeleteModal" title="Delete {{ Str::plural('import', count($pendingDeleteIds)) }}">
        @php $summary = $this->deleteSummary; @endphp
        <p>
            @if ($summary['name'])
                Delete <span class="font-medium">“{{ $summary['name'] }}”</span>?
            @else
                Delete {{ $summary['imports'] }} imports?
            @endif
            This removes {{ $summary['tracks'] }} {{ Str::plural('track', $summary['tracks']) }} and {{ $summary['exports'] }} export {{ Str::plural('file', $summary['exports']) }} (TXT).
        </p>
        <p class="mt-2 font-medium text-red-600 dark:text-red-400">This cannot be undone.</p>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showDeleteModal = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
            <button type="button" wire:click="delete" class="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-500">Delete</button>
        </x-slot:footer>
    </x-modal>
</div>
