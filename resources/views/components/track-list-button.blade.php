{{--
    Opens the Export track list modal (`imports.partials.track-list-modal`) for one import.
    Needs `trackList` and `trackListOpen` in a surrounding x-data. Disabled without tracks.
--}}
@props(['batch', 'count'])

<button
    type="button"
    data-name="{{ $batch->name }}"
    data-count="{{ $count }}"
    data-txt-url="{{ route('imports.track-list', ['batch' => $batch, 'format' => 'txt']) }}"
    data-csv-url="{{ route('imports.track-list', ['batch' => $batch, 'format' => 'csv']) }}"
    x-on:click="trackList = { name: $el.dataset.name, count: $el.dataset.count, txt: $el.dataset.txtUrl, csv: $el.dataset.csvUrl }; trackListOpen = true"
    @disabled($count === 0)
    {{ $attributes->class('disabled:cursor-not-allowed disabled:opacity-50') }}
>
    {{ $slot->isEmpty() ? 'Export track list' : $slot }}
</button>
