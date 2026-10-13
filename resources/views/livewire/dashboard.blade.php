<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">Dashboard</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Turn a track list into a TXT file of YouTube links.</p>
        </div>

        <button
            type="button"
            x-on:click="$wire.showHowItWorks = true"
            class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-medium hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
        >
            How it works
        </button>
    </div>

    <div class="rounded-lg border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-900">
        <p class="font-medium">No imports yet</p>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Recent imports and exports will show up here.</p>
        <div class="mt-6 flex justify-center">
            <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>
        </div>
    </div>

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
