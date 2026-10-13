<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Imports</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Your track lists. Continue any of them where you left off.</p>
        </div>

        <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>
    </div>

    @if ($this->batches->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No imports yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Paste a track list to create your first import.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Created</th>
                        <th class="px-3 py-2 text-right">Tracks</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="w-28 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->batches as $batch)
                        <tr wire:key="batch-{{ $batch->id }}">
                            <td class="px-3 py-2 font-medium">
                                <a href="{{ route('imports.review', $batch) }}" wire:navigate class="hover:text-accent-600 dark:hover:text-accent-400">{{ $batch->name }}</a>
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
                            <td class="px-3 py-2 text-right">
                                <a href="{{ route('imports.review', $batch) }}" wire:navigate class="text-xs font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400">Continue</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->batches->links() }}
    @endif
</div>
