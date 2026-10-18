<?php

namespace App\Livewire\Imports;

use App\Services\AiSettingsService;
use App\Services\AiTrackListParserService;
use App\Services\ImportBatchService;
use App\Services\ImportParserService;
use App\Services\YoutubePlaylistException;
use App\Services\YoutubePlaylistService;
use App\Services\YoutubeSearchService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * New YouTube Export: paste a track list or import a YouTube playlist, parse it into a batch.
 */
#[Title('New YouTube Export')]
class Create extends Component
{
    public const int MAX_TRACKS = 200;

    /**
     * Maximum pasted text length in AI mode (store copy-pastes use many lines per track).
     */
    public const int MAX_AI_INPUT_LENGTH = 100_000;

    public string $name = '';

    public string $year = '';

    public string $month = '';

    public string $defaultGenre = '';

    public string $rawInput = '';

    /**
     * Active tab: `paste` (track list) or `playlist` (YouTube playlist link).
     */
    #[Url]
    public string $source = 'paste';

    public string $playlistUrl = '';

    public function mount(): void
    {
        $this->name = 'YouTube Export '.now()->format('Y-m-d H:i');

        if (! in_array($this->source, ['paste', 'playlist'], true)) {
            $this->source = 'paste';
        }
    }

    public function showSource(string $source): void
    {
        if (in_array($source, ['paste', 'playlist'], true)) {
            $this->source = $source;
            $this->resetErrorBag(['rawInput', 'playlistUrl']);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'defaultGenre' => ['nullable', 'string', 'max:255'],
        ];

        if ($this->source === 'playlist') {
            return [...$rules, 'playlistUrl' => ['required', 'string', 'max:2048', function (string $attribute, mixed $value, Closure $fail): void {
                if (app(YoutubePlaylistService::class)->playlistId($value) === null) {
                    $fail('This is not a YouTube playlist link.');
                }
            }]];
        }

        return [
            ...$rules,
            'rawInput' => $this->aiEnabled ? ['required', 'string', 'max:'.self::MAX_AI_INPUT_LENGTH] : ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $count = count(app(ImportParserService::class)->parse($value));

                if ($count === 0) {
                    $fail('No tracks detected. Paste one track per line.');
                } elseif ($count > self::MAX_TRACKS) {
                    $fail('Too many tracks ('.$count.'). The maximum is '.self::MAX_TRACKS.'.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'defaultGenre' => 'default genre',
            'rawInput' => 'track list',
            'playlistUrl' => 'playlist link',
        ];
    }

    /**
     * Whether the track list is parsed with AI (an API key is configured).
     */
    #[Computed]
    public function aiEnabled(): bool
    {
        return app(AiTrackListParserService::class)->isEnabled();
    }

    /**
     * Playlists are read with the YouTube Data API, so the tab needs the API key.
     */
    #[Computed]
    public function playlistEnabled(): bool
    {
        return app(YoutubeSearchService::class)->isEnabled();
    }

    /**
     * AI would be used (a key is set), but this month's AI budget is spent.
     */
    #[Computed]
    public function aiBudgetReached(): bool
    {
        return filled(app(AiSettingsService::class)->apiKey()) && app(AiSettingsService::class)->isBudgetReached();
    }

    #[Computed]
    public function aiBudgetNearlyReached(): bool
    {
        return filled(app(AiSettingsService::class)->apiKey()) && app(AiSettingsService::class)->isBudgetNearlyReached();
    }

    /**
     * Live preview of how many tracks the line parser detects (plain mode only).
     *
     * @return array{tracks: int, withoutArtist: int}
     */
    #[Computed]
    public function detected(): array
    {
        $lines = app(ImportParserService::class)->parse($this->rawInput);

        return [
            'tracks' => count($lines),
            'withoutArtist' => count(array_filter($lines, fn (array $line): bool => $line['artist'] === null)),
        ];
    }

    public function save(ImportBatchService $importBatches): void
    {
        if ($this->source === 'playlist') {
            $this->importPlaylist($importBatches);

            return;
        }

        $validated = $this->validate();

        $attributes = [
            ...$this->batchAttributes($validated),
            'raw_input' => $validated['rawInput'],
        ];

        if ($this->aiEnabled) {
            $batch = $importBatches->createForAiParsing($attributes);
        } else {
            $batch = $importBatches->createFromRawInput($attributes);
            session()->flash('status', "Imported {$batch->items()->count()} tracks. Review them before searching YouTube.");
            session()->flash('check_duplicates', true);
        }

        $this->redirectRoute('imports.review', ['batch' => $batch], navigate: true);
    }

    /**
     * Read the playlist (first `MAX_TRACKS` playable videos) and create a batch with one track per video.
     */
    private function importPlaylist(ImportBatchService $importBatches): void
    {
        if (! $this->playlistEnabled) {
            $this->addError('playlistUrl', 'Importing a playlist needs a YouTube Data API key.');

            return;
        }

        $validated = $this->validate();

        try {
            $playlist = app(YoutubePlaylistService::class)->videos($validated['playlistUrl'], self::MAX_TRACKS);
        } catch (YoutubePlaylistException $exception) {
            $this->addError('playlistUrl', $exception->userMessage());

            return;
        }

        if ($playlist['videos'] === []) {
            $this->addError('playlistUrl', 'This playlist has no playable videos.');

            return;
        }

        $batch = $importBatches->createFromPlaylist($this->batchAttributes($validated), $playlist['videos']);

        session()->flash('status', $this->playlistNotice(count($playlist['videos']), $playlist['unavailable'], $playlist['notImported']));
        session()->flash('check_duplicates', true);

        $this->redirectRoute('imports.review', ['batch' => $batch], navigate: true);
    }

    private function playlistNotice(int $imported, int $unavailable, int $notImported): string
    {
        $notice = "Imported {$imported} ".Str::plural('track', $imported).' from the playlist, each with its video as the link.';

        if ($unavailable > 0) {
            $notice .= " Skipped {$unavailable} deleted or private ".Str::plural('video', $unavailable).'.';
        }

        if ($notImported > 0) {
            $notice .= ' Only the first '.self::MAX_TRACKS." videos are imported; {$notImported} more were left out.";
        }

        return $notice;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{name: string, year: ?int, month: ?int, default_genre: ?string}
     */
    private function batchAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'year' => filled($validated['year']) ? (int) $validated['year'] : null,
            'month' => filled($validated['month']) ? (int) $validated['month'] : null,
            'default_genre' => filled($validated['defaultGenre']) ? $validated['defaultGenre'] : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function months(): array
    {
        return collect(range(1, 12))
            ->mapWithKeys(fn (int $month): array => [$month => now()->startOfYear()->month($month)->format('F')])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.imports.create');
    }
}
