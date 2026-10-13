<?php

namespace App\Livewire\Imports;

use App\Services\ImportBatchService;
use App\Services\ImportParserService;
use Closure;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * New YouTube Export: paste a track list, parse it into a batch.
 */
#[Title('New YouTube Export')]
class Create extends Component
{
    public const int MAX_TRACKS = 200;

    public string $name = '';

    public string $year = '';

    public string $month = '';

    public string $defaultGenre = '';

    public string $rawInput = '';

    public function mount(): void
    {
        $this->name = 'YouTube Export '.now()->format('Y-m-d H:i');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'defaultGenre' => ['nullable', 'string', 'max:255'],
            'rawInput' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
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
        ];
    }

    /**
     * Live preview of how many tracks the parser detects.
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
        $validated = $this->validate();

        $batch = $importBatches->createFromRawInput([
            'name' => $validated['name'],
            'year' => filled($validated['year']) ? (int) $validated['year'] : null,
            'month' => filled($validated['month']) ? (int) $validated['month'] : null,
            'default_genre' => filled($validated['defaultGenre']) ? $validated['defaultGenre'] : null,
            'raw_input' => $validated['rawInput'],
        ]);

        session()->flash('status', "Imported {$batch->items()->count()} tracks. Review them before searching YouTube.");

        $this->redirectRoute('imports.review', ['batch' => $batch], navigate: true);
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
