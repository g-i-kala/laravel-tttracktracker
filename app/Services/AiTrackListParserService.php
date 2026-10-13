<?php

namespace App\Services;

use App\Ai\Agents\TrackListParser;
use Illuminate\Support\Str;

/**
 * Parses a pasted track list with an AI model (Laravel AI SDK). Enabled when
 * an API key is set (Settings → AI or .env) and the monthly budget is not
 * reached; otherwise the line parser is used.
 */
class AiTrackListParserService
{
    public function __construct(private ImportParserService $lineParser, private AiSettingsService $aiSettings) {}

    public function isEnabled(): bool
    {
        return filled($this->aiSettings->apiKey()) && ! $this->aiSettings->isBudgetReached();
    }

    /**
     * @return list<array{line_number: int, raw_line: string, artist: ?string, title: string, genre: ?string, normalized_key: string, error_message: ?string}>
     */
    public function parse(string $rawInput): array
    {
        $response = $this->aiSettings->withApiKey(fn () => TrackListParser::make()->prompt(
            $rawInput,
            provider: $this->aiSettings->provider(),
            model: $this->aiSettings->model(),
            timeout: config('services.track_list_ai.timeout'),
        ));

        $tracks = collect($response['tracks'] ?? [])
            ->map(fn (array $track): array => [
                'artist' => $this->nullIfBlank($track['artist'] ?? null),
                'title' => Str::squish((string) ($track['title'] ?? '')),
                'genre' => $this->nullIfBlank($track['genre'] ?? null),
                'source' => Str::squish((string) ($track['source'] ?? '')),
            ])
            ->filter(fn (array $track): bool => $track['title'] !== '')
            ->values();

        return $tracks->map(fn (array $track, int $index): array => [
            'line_number' => $index + 1,
            'raw_line' => $track['source'] !== '' ? $track['source'] : trim(($track['artist'] ?? '').' - '.$track['title'], ' -'),
            'artist' => $track['artist'],
            'title' => $track['title'],
            'genre' => $track['genre'],
            'normalized_key' => $this->lineParser->normalizedKey($track['artist'], $track['title']),
            'error_message' => $track['artist'] === null ? ImportParserService::ARTIST_NOT_DETECTED : null,
        ])->all();
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }
}
