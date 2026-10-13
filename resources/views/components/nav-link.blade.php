{{--
    Sidebar navigation link. Renders a disabled item while the route does not exist yet,
    so the navigation can list screens before they are built.
--}}
@props(['route', 'variant' => 'link'])

@php
    $classes = $variant === 'button'
        ? 'rounded-md bg-accent-600 px-3 py-2 text-center text-sm font-semibold text-white hover:bg-accent-500'
        : 'rounded-md px-2 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-100 data-current:bg-accent-50 data-current:text-accent-700 dark:text-zinc-300 dark:hover:bg-zinc-800 dark:data-current:bg-accent-950 dark:data-current:text-accent-300';
@endphp

@if (Route::has($route))
    <a href="{{ route($route) }}" wire:navigate {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <span {{ $attributes->class([$classes, 'cursor-not-allowed opacity-50']) }} title="Coming soon">{{ $slot }}</span>
@endif
