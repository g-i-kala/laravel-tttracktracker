<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">Exports</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Every Supersimple TXT you generated, including older versions.</p>
    </div>

    @if ($this->exports->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No exports yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Generate a TXT on the YouTube links screen of an import.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-zinc-200 text-xs font-medium tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-2">File</th>
                        <th class="px-3 py-2">Import</th>
                        <th class="px-3 py-2 text-right">Version</th>
                        <th class="px-3 py-2 text-right">Links</th>
                        <th class="px-3 py-2">Exported</th>
                        <th class="w-24 px-3 py-2"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($this->exports as $export)
                        <tr wire:key="export-{{ $export->id }}">
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $this->exports->links() }}
    @endif
</div>
