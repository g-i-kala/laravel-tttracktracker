{{--
    Reusable modal (Blade + Alpine). Bind it to a boolean Livewire property:

        <x-modal wire:model="showDuplicateModal" title="Duplicates found">
            ...
            <x-slot:footer>...buttons...</x-slot:footer>
        </x-modal>

    Closes on Escape, backdrop click or the close button. Works with x-model too.
--}}
@props(['title' => null, 'maxWidth' => 'lg'])

@php
    $maxWidthClass = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ][$maxWidth] ?? 'sm:max-w-lg';
@endphp

<div
    x-data="{ open: false }"
    x-modelable="open"
    {{ $attributes->whereStartsWith(['wire:model', 'x-model']) }}
    x-on:keydown.escape.window="open = false"
>
    <div
        x-show="open"
        x-cloak
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-end justify-center bg-zinc-950/60 p-4 sm:items-center"
        x-on:click.self="open = false"
    >
        <div
            x-show="open"
            x-trap.noscroll="open"
            x-transition
            role="dialog"
            aria-modal="true"
            @if ($title) aria-label="{{ $title }}" @endif
            {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class(['w-full rounded-lg bg-white shadow-xl dark:bg-zinc-900', $maxWidthClass]) }}
        >
            <div class="flex items-start justify-between gap-4 border-b border-zinc-200 px-5 py-4 dark:border-zinc-800">
                <h2 class="text-base font-semibold">{{ $title }}</h2>
                <button type="button" x-on:click="open = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200" aria-label="Close">&times;</button>
            </div>

            <div class="px-5 py-4 text-sm text-zinc-700 dark:text-zinc-300">
                {{ $slot }}
            </div>

            @isset($footer)
                <div class="flex flex-wrap justify-end gap-2 border-t border-zinc-200 px-5 py-4 dark:border-zinc-800">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>
