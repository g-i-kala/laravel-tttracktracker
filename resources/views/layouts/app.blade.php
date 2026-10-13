<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-accent="{{ config('app.accent') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-zinc-50 font-sans text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen md:flex">
            {{-- Mobile top bar --}}
            <div class="flex items-center justify-between border-b border-zinc-200 bg-white px-4 py-3 md:hidden dark:border-zinc-800 dark:bg-zinc-900">
                <a href="{{ route('dashboard') }}" wire:navigate class="font-semibold">{{ config('app.name') }}</a>
                <button type="button" x-on:click="sidebarOpen = ! sidebarOpen" class="rounded-md px-2 py-1 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    Menu
                </button>
            </div>

            {{-- Sidebar --}}
            <aside
                x-bind:class="sidebarOpen ? 'flex' : 'hidden'"
                class="w-full shrink-0 flex-col gap-6 border-b border-zinc-200 bg-white p-4 md:sticky md:top-0 md:flex md:h-screen md:w-60 md:border-r md:border-b-0 dark:border-zinc-800 dark:bg-zinc-900"
            >
                <a href="{{ route('dashboard') }}" wire:navigate class="hidden px-2 text-lg font-semibold md:block">
                    {{ config('app.name') }}
                </a>

                <nav class="flex flex-col gap-1">
                    <x-nav-link route="dashboard">Dashboard</x-nav-link>
                    <x-nav-link route="imports.index">Imports</x-nav-link>
                    <x-nav-link route="exports.index">Exports</x-nav-link>
                </nav>

                <x-nav-link route="imports.create" variant="button">+ New YouTube Export</x-nav-link>

                <nav class="flex flex-col gap-1 md:mt-auto">
                    <x-nav-link route="settings.ai">AI Settings</x-nav-link>
                </nav>
            </aside>

            {{-- Page content --}}
            <main class="min-w-0 flex-1 p-4 md:p-8">
                @if (session('status'))
                    <div class="mb-6 rounded-md border border-accent-200 bg-accent-50 px-4 py-3 text-sm text-accent-800 dark:border-accent-900 dark:bg-accent-950 dark:text-accent-200">
                        {{ session('status') }}
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
