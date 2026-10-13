@php
    $inputClasses = 'w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900';
    $labelClasses = 'mb-1 block text-sm font-medium';
    $errorClasses = 'mt-1 text-sm text-red-600 dark:text-red-400';
@endphp

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold">New YouTube Export</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            @if ($this->aiEnabled)
                Paste your track list in any format.
            @else
                Paste your track list, one track per line, as <code>Artist - Title</code>.
            @endif
        </p>
    </div>

    @if ($this->aiBudgetReached)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            The monthly AI budget is reached, so the simple line parser is used.
            <a href="{{ route('settings.ai') }}" wire:navigate class="font-medium underline">Change the limit</a>
        </div>
    @elseif ($this->aiBudgetNearlyReached)
        <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
            Over 80% of the monthly AI budget is used.
            <a href="{{ route('settings.ai') }}" wire:navigate class="font-medium underline">See usage</a>
        </div>
    @endif

    <form wire:submit="save" class="flex flex-col gap-5 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900">
        <div>
            <label for="name" class="{{ $labelClasses }}">Name</label>
            <input id="name" type="text" wire:model="name" class="{{ $inputClasses }}">
            @error('name') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label for="year" class="{{ $labelClasses }}">Year <span class="font-normal text-zinc-500">(optional)</span></label>
                <input id="year" type="number" min="2000" max="2100" wire:model="year" class="{{ $inputClasses }}">
                @error('year') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="month" class="{{ $labelClasses }}">Month <span class="font-normal text-zinc-500">(optional)</span></label>
                <select id="month" wire:model="month" class="{{ $inputClasses }}">
                    <option value="">—</option>
                    @foreach ($this->months as $number => $monthName)
                        <option value="{{ $number }}" wire:key="month-{{ $number }}">{{ $monthName }}</option>
                    @endforeach
                </select>
                @error('month') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="defaultGenre" class="{{ $labelClasses }}">Default genre <span class="font-normal text-zinc-500">(optional)</span></label>
                <input id="defaultGenre" type="text" wire:model="defaultGenre" placeholder="e.g. Techno" class="{{ $inputClasses }}">
                @error('defaultGenre') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="rawInput" class="{{ $labelClasses }}">Track list</label>
            <textarea
                id="rawInput"
                rows="14"
                @if ($this->aiEnabled) wire:model="rawInput" @else wire:model.live.debounce.400ms="rawInput" @endif
                placeholder="Adam Beyer - Teach Me (Original Mix)&#10;Bicep - Glue&#10;Charlotte de Witte - Sgadi Li Mi"
                class="{{ $inputClasses }} font-mono"
            ></textarea>
            @error('rawInput') <p class="{{ $errorClasses }}">{{ $message }}</p> @enderror

            @if ($this->aiEnabled)
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400" data-testid="ai-mode">
                    <span class="font-medium text-accent-600 dark:text-accent-400">AI parsing on</span>
                    · paste any format (plain lines, Beatport, tracklists); tracks are detected after you click Parse.
                </p>
            @else
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400" data-testid="detected-tracks">
                    {{ $this->detected['tracks'] }} {{ Str::plural('track', $this->detected['tracks']) }} detected
                    @if ($this->detected['withoutArtist'] > 0)
                        · <span class="text-amber-600 dark:text-amber-400">{{ $this->detected['withoutArtist'] }} without artist (fix them in review)</span>
                    @endif
                </p>
            @endif
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="rounded-md bg-accent-600 px-4 py-2 text-sm font-semibold text-white hover:bg-accent-500 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="save">Parse</span>
                <span wire:loading wire:target="save">Parsing…</span>
            </button>
        </div>
    </form>
</div>
