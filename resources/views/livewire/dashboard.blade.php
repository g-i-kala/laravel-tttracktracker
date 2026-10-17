<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Turn a track list into a TXT file of YouTube links.</p>
            @if ($this->youtubeQuota)
                <x-youtube-quota :quota="$this->youtubeQuota" class="mt-1" />
            @endif
        </div>

        <div class="flex gap-2">
            <button
                type="button"
                x-on:click="$wire.showHowItWorks = true"
                class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
            >
                How it works
            </button>
            @if ($this->recentImports->isNotEmpty())
                <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>
            @endif
        </div>
    </div>

    @php
        $attentionCount = $this->toReview->count() + $this->missingLinks->count() + $this->outdatedExports->count();
        $linkClasses = 'font-medium text-accent-600 hover:text-accent-500 dark:text-accent-400';
    @endphp

    @if ($this->recentImports->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <p class="font-medium">No imports yet</p>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Recent imports and exports will show up here.</p>
            <div class="mt-6 flex justify-center">
                <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>
            </div>
        </div>
    @else
        <section class="flex flex-col gap-3">
            <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">Needs attention</h2>

            @if ($attentionCount === 0)
                <p class="rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">All caught up: nothing to review, no missing links, every TXT is up to date.</p>
            @else
                <ul class="divide-y divide-zinc-200 rounded-lg border border-amber-200 bg-amber-50/60 text-sm dark:divide-zinc-800 dark:border-amber-900 dark:bg-amber-950/30">
                    @foreach ($this->toReview as $batch)
                        <li wire:key="review-{{ $batch->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                            <span><span class="font-medium">{{ $batch->name }}</span> <span class="text-zinc-600 dark:text-zinc-400">· {{ $batch->items_count }} {{ Str::plural('track', $batch->items_count) }} to review</span></span>
                            <a href="{{ route('imports.review', $batch) }}" wire:navigate class="{{ $linkClasses }}">Review</a>
                        </li>
                    @endforeach
                    @foreach ($this->missingLinks as $batch)
                        <li wire:key="missing-{{ $batch->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                            <span><span class="font-medium">{{ $batch->name }}</span> <span class="text-zinc-600 dark:text-zinc-400">· {{ $batch->missing_links_count }} of {{ $batch->items_count }} {{ Str::plural('track', $batch->items_count) }} without a link</span></span>
                            <a href="{{ route('imports.show', $batch) }}" wire:navigate class="{{ $linkClasses }}">Add links</a>
                        </li>
                    @endforeach
                    @foreach ($this->outdatedExports as $batch)
                        <li wire:key="outdated-{{ $batch->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                            <span><span class="font-medium">{{ $batch->name }}</span> <span class="text-zinc-600 dark:text-zinc-400">· links changed since the last TXT</span></span>
                            <a href="{{ route('imports.show', $batch) }}" wire:navigate class="{{ $linkClasses }}">Export again</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="flex min-w-0 flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">Recent imports</h2>
                    <a href="{{ route('imports.index') }}" wire:navigate class="text-xs {{ $linkClasses }}">View all</a>
                </div>
                <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white text-sm dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                    @foreach ($this->recentImports as $batch)
                        <li wire:key="import-{{ $batch->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                            <a href="{{ route($batch->status->screenRouteName(), $batch) }}" wire:navigate class="min-w-0 truncate font-medium hover:text-accent-600 dark:hover:text-accent-400">{{ $batch->name }}</a>
                            <span class="flex shrink-0 items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                <span class="tabular-nums">{{ $batch->items_count }} {{ Str::plural('track', $batch->items_count) }}</span>
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $batch->status->label() }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </section>

            <section class="flex min-w-0 flex-col gap-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">Recent exports</h2>
                    <a href="{{ route('exports.index') }}" wire:navigate class="text-xs {{ $linkClasses }}">View all</a>
                </div>
                @if ($this->recentExports->isEmpty())
                    <p class="rounded-lg border border-dashed border-zinc-300 bg-white px-4 py-6 text-center text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400">No exports yet.</p>
                @else
                    <ul class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 bg-white text-sm dark:divide-zinc-800 dark:border-zinc-800 dark:bg-zinc-900">
                        @foreach ($this->recentExports as $export)
                            <li wire:key="export-{{ $export->id }}" class="flex items-center justify-between gap-3 px-4 py-2.5">
                                <span class="min-w-0">
                                    <span class="block truncate font-mono text-xs">{{ $export->filename }}</span>
                                    <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $export->importBatch->name }} · {{ $export->items_count }} {{ Str::plural('link', $export->items_count) }} · {{ $export->updated_at->format('Y-m-d H:i') }}</span>
                                </span>
                                <a href="{{ route('exports.download', $export) }}" class="shrink-0 text-xs {{ $linkClasses }}">Download</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endif

    <x-modal wire:model="showHowItWorks" title="How it works">
        <ol class="list-decimal space-y-1 pl-5">
            <li>Paste your track list (<code>Artist - Title</code>, one per line).</li>
            <li>Review and edit the parsed artists and titles.</li>
            <li>Search YouTube, or open search links and paste custom links.</li>
            <li>Pick the links to include.</li>
            <li>Download a TXT file with only the YouTube URLs.</li>
        </ol>

        <x-slot:footer>
            <button type="button" x-on:click="$wire.showHowItWorks = false" class="rounded-md bg-accent-600 px-3 py-2 text-sm font-semibold text-white hover:bg-accent-500">
                Got it
            </button>
        </x-slot:footer>
    </x-modal>
</div>
