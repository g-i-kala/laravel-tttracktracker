{{-- Expanded library row: edit the track (autosaved on blur) and see the imports it came from. --}}
@php
    $fieldClasses = 'rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-sm text-zinc-900 focus:border-accent-500 focus:ring-2 focus:ring-accent-500/30 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100';
    $labelClasses = 'flex flex-col gap-1 text-xs text-zinc-500 dark:text-zinc-400';
    $checkboxClasses = 'size-4 rounded border-zinc-300 text-accent-600 focus:ring-accent-500 disabled:opacity-50 dark:border-zinc-700';
    $error = fn (string $field) => $errors->first("edit.{$field}");
@endphp
<tr
    wire:key="track-panel-{{ $track->id }}"
    x-data="{ saved: false, savedTimer: null }"
    x-on:track-saved.window="if ($event.detail.id === {{ $track->id }}) { saved = true; clearTimeout(savedTimer); savedTimer = setTimeout(() => saved = false, 1500) }"
    class="bg-zinc-50 dark:bg-zinc-800/40"
>
    <td></td>
    <td colspan="{{ $colspan - 1 }}" class="px-3 pt-1 pb-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label class="{{ $labelClasses }} lg:col-span-2">
                Artist
                <input type="text" wire:model.live.blur="edit.artist" class="{{ $fieldClasses }}">
                @if ($error('artist')) <span class="text-red-600 dark:text-red-400">{{ $error('artist') }}</span> @endif
            </label>
            <label class="{{ $labelClasses }} lg:col-span-2">
                Title
                <input type="text" wire:model.live.blur="edit.title" class="{{ $fieldClasses }}">
                @if ($error('title')) <span class="text-red-600 dark:text-red-400">{{ $error('title') }}</span> @endif
            </label>
            <label class="{{ $labelClasses }}">
                Genre
                <input type="text" wire:model.live.blur="edit.genre" class="{{ $fieldClasses }}">
                @if ($error('genre')) <span class="text-red-600 dark:text-red-400">{{ $error('genre') }}</span> @endif
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="{{ $labelClasses }}">
                    BPM
                    <input type="number" min="{{ \App\Services\AiTrackListParserService::MIN_BPM }}" max="{{ \App\Services\AiTrackListParserService::MAX_BPM }}" wire:model.live.blur="edit.bpm" class="{{ $fieldClasses }}">
                </label>
                <label class="{{ $labelClasses }}">
                    Year
                    <input type="number" min="1900" max="{{ now()->year + 1 }}" wire:model.live.blur="edit.release_year" class="{{ $fieldClasses }}">
                </label>
                @if ($error('bpm')) <span class="col-span-2 text-xs text-red-600 dark:text-red-400">{{ $error('bpm') }}</span> @endif
                @if ($error('release_year')) <span class="col-span-2 text-xs text-red-600 dark:text-red-400">{{ $error('release_year') }}</span> @endif
            </div>
            <label class="{{ $labelClasses }}">
                <span>Key @if ($track->camelot_key) <span class="text-zinc-400 dark:text-zinc-500">· Camelot {{ $track->camelot_key }}</span> @endif</span>
                <input type="text" placeholder="E Minor" wire:model.live.blur="edit.musical_key" class="{{ $fieldClasses }}">
                @if ($track->musical_key && ! $track->camelot_key)
                    <span class="text-amber-600 dark:text-amber-400">Key not recognized, so no Camelot code. Use e.g. "E Minor", "F# Major", "Bbm" or "9A".</span>
                @endif
            </label>
            <label class="{{ $labelClasses }}">
                Label
                <input type="text" wire:model.live.blur="edit.label" class="{{ $fieldClasses }}">
            </label>
            <label class="{{ $labelClasses }} lg:col-span-2">
                <span>YouTube link (PON) @if ($track->youtube_url) <a href="{{ $track->youtube_url }}" target="_blank" rel="noopener" class="ml-1 font-medium text-accent-600 hover:underline dark:text-accent-400">Open</a> @endif</span>
                <input type="url" placeholder="https://www.youtube.com/watch?v=…" wire:model.live.blur="edit.youtube_url" class="{{ $fieldClasses }}">
                @if ($error('youtube_url')) <span class="text-red-600 dark:text-red-400">{{ $error('youtube_url') }}</span> @endif
            </label>
            <label class="{{ $labelClasses }} lg:col-span-2">
                Notes
                <textarea rows="2" wire:model.live.blur="edit.notes" class="{{ $fieldClasses }}"></textarea>
                @if ($error('notes')) <span class="text-red-600 dark:text-red-400">{{ $error('notes') }}</span> @endif
            </label>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">Tags</span>
            @foreach ($track->tags as $tag)
                <span wire:key="panel-tag-{{ $tag->id }}" class="inline-flex items-center gap-1 rounded bg-zinc-200 px-1.5 py-0.5 text-xs text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">
                    #{{ $tag->name }}
                    <button type="button" wire:click="removeTag({{ $tag->id }})" aria-label="Remove tag {{ $tag->name }}" class="text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">&times;</button>
                </span>
            @endforeach
            <input type="text" wire:model="newTag" list="library-tags" maxlength="{{ \App\Services\TagService::MAX_LENGTH }}" placeholder="Add tag + Enter" aria-label="Add tag" x-on:keydown.enter.prevent="$wire.addTag()" class="{{ $fieldClasses }} w-40 py-1 text-xs">
            @error('newTag') <span class="text-xs text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-5 text-sm">
            <span class="flex items-center gap-2"><span class="text-xs text-zinc-500 dark:text-zinc-400">Rating</span><x-rating-stars :track-id="$track->id" :rating="$track->rating" /></span>
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model.live="edit.playable_offline" class="{{ $checkboxClasses }}">
                <span><span class="font-medium">POF</span> <span class="text-zinc-500 dark:text-zinc-400">I have the file</span></span>
            </label>
            <label class="flex items-center gap-2" title="{{ $track->playable_offline ? 'You already have the file (POF)' : '' }}">
                <input type="checkbox" wire:model.live="edit.wanted" @disabled($track->playable_offline) class="{{ $checkboxClasses }}">
                <span><span class="font-medium">Wanted</span> <span class="text-zinc-500 dark:text-zinc-400">I want the file</span></span>
            </label>
            <span x-show="saved" x-cloak x-transition.opacity class="text-xs text-accent-600 dark:text-accent-400">Saved</span>
            <button type="button" wire:click="confirmDelete({{ $track->id }})" class="ml-auto text-xs font-medium text-zinc-500 hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400">Remove from library</button>
        </div>

        <div class="mt-4 text-sm">
            <p class="text-xs font-medium tracking-wide text-zinc-500 uppercase dark:text-zinc-400">Sources</p>
            @if ($track->importItems->isEmpty())
                <p class="mt-1 text-zinc-500 dark:text-zinc-400">Not linked to any import.</p>
            @else
                <ul class="mt-1 flex flex-col gap-0.5">
                    @foreach ($track->importItems as $item)
                        <li wire:key="source-{{ $item->id }}">
                            <a href="{{ route($item->importBatch->status->screenRouteName(), $item->importBatch) }}" wire:navigate class="font-medium text-accent-600 hover:underline dark:text-accent-400">{{ $item->importBatch->name }}</a>
                            <span class="text-zinc-500 dark:text-zinc-400">· #{{ $item->line_number }} {{ $item->artist ? $item->artist.' - ' : '' }}{{ $item->title }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </td>
</tr>
