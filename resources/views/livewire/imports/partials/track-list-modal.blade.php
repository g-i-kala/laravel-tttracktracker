{{--
    Export track list modal (Alpine only, the file is a plain download link).
    Needs `trackList` ({name, count, txt, csv}) and `trackListOpen` in a surrounding x-data;
    open it with <x-track-list-button>.
--}}
@php
    $optionClasses = 'flex flex-col gap-1 rounded-lg border border-zinc-200 p-4 hover:border-accent-500 hover:bg-accent-50 dark:border-zinc-800 dark:hover:border-accent-400 dark:hover:bg-accent-950';
@endphp

<x-modal x-model="trackListOpen" title="Export track list">
    <p>
        Download all <span class="font-medium" x-text="trackList?.count"></span> tracks of
        <span class="font-medium">“<span x-text="trackList?.name"></span>”</span>, with or without YouTube links.
    </p>

    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <a x-bind:href="trackList?.txt" download x-on:click="trackListOpen = false" class="{{ $optionClasses }}">
            <span class="font-semibold text-zinc-900 dark:text-zinc-100">TXT</span>
            <span class="text-xs text-zinc-600 dark:text-zinc-400"><code>Artist - Title</code>, one per line, like a paste.</span>
        </a>
        <a x-bind:href="trackList?.csv" download x-on:click="trackListOpen = false" class="{{ $optionClasses }}">
            <span class="font-semibold text-zinc-900 dark:text-zinc-100">CSV</span>
            <span class="text-xs text-zinc-600 dark:text-zinc-400">Artist, Title, Genre, BPM, Key, Camelot, Label, YouTube URL. Opens in Numbers, Excel or Google Sheets.</span>
        </a>
    </div>

    <x-slot:footer>
        <button type="button" x-on:click="trackListOpen = false" class="rounded-md px-3 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">Cancel</button>
    </x-slot:footer>
</x-modal>
