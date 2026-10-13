<?php

namespace App\Services;

use App\Ai\Agents\TrackListParser;
use Illuminate\Support\Str;

/**
 * Parses a pasted track list with an AI model (Laravel AI SDK). Enabled when
 * the configured provider has an API key; otherwise the line parser is used.
 */
class AiTrackListParserService
{
    public function __construct(private ImportParserService $lineParser) {}

    public function isEnabled(): bool
    {
        return filled(config('ai.providers.'.config('services.track_list_ai.provider').'.key'));
    }

    /**
     * @return list<array{line_number: int, raw_line: string, artist: ?string, title: string, genre: ?string, normalized_key: string, error_message: ?string}>
     */
    public function parse(string $rawInput): array
    {
        $response = TrackListParser::make()->prompt(
            $rawInput,
            provider: config('services.track_list_ai.provider'),
            model: config('services.track_list_ai.model'),
            timeout: config('services.track_list_ai.timeout'),
        );

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
