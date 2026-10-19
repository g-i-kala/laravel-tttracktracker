{{--
    Clickable 1–5 stars for a library track; calls `rate(trackId, n)` on the
    Livewire component. Clicking the current rating clears it.
--}}
@props(['trackId', 'rating' => null])

<div {{ $attributes->class('inline-flex') }} role="group" aria-label="Rating">
    @foreach (range(1, 5) as $star)
        <button
            type="button"
            wire:click="rate({{ $trackId }}, {{ $star }})"
            title="{{ $rating === $star ? 'Clear rating' : $star.' '.Str::plural('star', $star) }}"
            aria-label="{{ $star }} {{ Str::plural('star', $star) }}"
            aria-pressed="{{ $rating !== null && $star <= $rating ? 'true' : 'false' }}"
            @class([
                'px-px text-base leading-none',
                'text-amber-500 hover:text-amber-400' => $rating !== null && $star <= $rating,
                'text-zinc-300 hover:text-amber-400 dark:text-zinc-600' => $rating === null || $star > $rating,
            ])
        >★</button>
    @endforeach
</div>
