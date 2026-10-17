@php
    $cardClasses = 'rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900';
    $quota = $this->quota;
    $usagePercent = min(100, (int) round($quota['usage'] * 100));
@endphp

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Settings</p>
        <h1 class="text-2xl font-semibold">YouTube</h1>
        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
            With an API key, Search YouTube finds links for you. Each search costs {{ \App\Services\YoutubeSearchService::SEARCH_QUOTA_UNITS }} units; cached searches are free.
        </p>
    </div>

    <section class="{{ $cardClasses }} flex flex-col gap-4">
        <h2 class="font-semibold">Quota today</h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Used</p>
                <p class="text-2xl font-semibold tabular-nums">{{ number_format($quota['used']) }}</p>
                <p class="text-sm text-zinc-500 tabular-nums dark:text-zinc-400">of {{ number_format($quota['limit']) }} units</p>
            </div>
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Searches left</p>
                <p class="text-2xl font-semibold tabular-nums">~{{ $quota['isUsedUp'] ? 0 : $quota['searchesLeft'] }}</p>
            </div>
            <div>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Resets</p>
                <p class="text-2xl font-semibold tabular-nums">{{ $quota['resetsAt']->format('H:i') }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">midnight Pacific Time</p>
            </div>
        </div>

        <div>
            <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800" role="progressbar" aria-valuenow="{{ $usagePercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="Quota used">
                <div @class([
                    'h-full rounded-full',
                    'bg-red-500' => $quota['isUsedUp'],
                    'bg-amber-500' => ! $quota['isUsedUp'] && $quota['usage'] >= 0.8,
                    'bg-accent-600' => ! $quota['isUsedUp'] && $quota['usage'] < 0.8,
                ]) style="width: {{ $quota['isUsedUp'] ? 100 : $usagePercent }}%"></div>
            </div>
            <x-youtube-quota :quota="$quota" class="mt-1" />
        </div>

        <p class="text-sm text-zinc-500 dark:text-zinc-400">Estimated from the searches this app sent. Daily limit: <code>YOUTUBE_DAILY_QUOTA</code> in <code>.env</code>.</p>
    </section>

    <section class="{{ $cardClasses }} flex flex-col gap-2">
        <h2 class="font-semibold">API key</h2>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
            @if ($this->hasApiKey)
                Using the key from <code>.env</code> (<code>YOUTUBE_DATA_API_KEY</code>).
            @else
                <span class="text-amber-600 dark:text-amber-400">No key set: Search YouTube creates search links you open yourself.</span>
                Set <code>YOUTUBE_DATA_API_KEY</code> in <code>.env</code> to search automatically.
            @endif
        </p>
    </section>
</div>
