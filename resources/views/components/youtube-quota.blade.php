{{--
    One-line YouTube Data API quota counter for today (see YoutubeQuotaService::today()).
    Amber from 80% used, red once no search is left.
--}}
@props(['quota'])

@php
    $resetsAt = $quota['resetsAt']->format('H:i');
@endphp

<p {{ $attributes->class([
    'text-sm',
    'font-medium text-red-600 dark:text-red-400' => $quota['isUsedUp'],
    'text-amber-600 dark:text-amber-400' => ! $quota['isUsedUp'] && $quota['usage'] >= 0.8,
    'text-zinc-600 dark:text-zinc-400' => ! $quota['isUsedUp'] && $quota['usage'] < 0.8,
]) }} data-test="youtube-quota">
    @if ($quota['isUsedUp'])
        YouTube quota used up — resets {{ $resetsAt }}
    @else
        YouTube quota: {{ number_format($quota['used']) }} / {{ number_format($quota['limit']) }} units used today (~{{ $quota['searchesLeft'] }} {{ Str::plural('search', $quota['searchesLeft']) }} left) · resets {{ $resetsAt }}
    @endif
</p>
