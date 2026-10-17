<?php

namespace App\Services;

use App\Enums\ImportItemStatus;
use App\Models\ImportItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Edits import items during Parse Review and on YouTube Results, and stores
 * the results of the YouTube API search.
 */
class ImportItemService
{
    public const string SEARCH_FAILED_NOTICE = 'YouTube search failed — use the search link.';

    public const string QUOTA_EXCEEDED_NOTICE = 'YouTube API quota used up for today — use the search link.';

    public function __construct(private ImportParserService $parser, private MusicalKeyService $musicalKeys) {}

    /**
     * Update artist, title, genre, BPM, key and label. Empty optional fields become
     * null, the normalized key and Camelot key are recomputed and the
     * missing-artist notice is set or cleared.
     *
     * @param  array{artist: ?string, title: string, genre: ?string, bpm: int|string|null, musical_key: ?string, label: ?string}  $attributes
     */
    public function updateTrackDetails(ImportItem $item, array $attributes): ImportItem
    {
        $artist = $this->nullIfBlank($attributes['artist']);
        $title = Str::squish($attributes['title']);
        $musicalKey = $this->nullIfBlank($attributes['musical_key']);
        $bpm = $this->nullIfBlank((string) $attributes['bpm']);

        $item->update([
            'artist' => $artist,
            'title' => $title,
            'genre' => $this->nullIfBlank($attributes['genre']),
            'bpm' => $bpm === null ? null : (int) $bpm,
            'musical_key' => $musicalKey,
            'camelot_key' => $this->musicalKeys->toCamelot($musicalKey),
            'label' => $this->nullIfBlank($attributes['label']),
            'normalized_key' => $this->parser->normalizedKey($artist, $title),
            'error_message' => $artist === null ? ImportParserService::ARTIST_NOT_DETECTED : null,
        ]);

        return $item;
    }

    /**
     * Store a custom (pasted) YouTube link as the selected link and include the
     * item in the export. Title, channel and score belong to API candidates and are cleared.
     */
    public function selectCustomYoutubeLink(ImportItem $item, string $videoId): ImportItem
    {
        $item->update([
            'selected_youtube_url' => "https://www.youtube.com/watch?v={$videoId}",
            'selected_youtube_video_id' => $videoId,
            'selected_youtube_title' => null,
            'selected_youtube_channel' => null,
            'youtube_score' => null,
            'include_in_export' => true,
        ]);

        return $item;
    }

    /**
     * Remove the selected link; without a link the item cannot be exported.
     */
    public function clearYoutubeLink(ImportItem $item): ImportItem
    {
        $item->update([
            'selected_youtube_url' => null,
            'selected_youtube_video_id' => null,
            'selected_youtube_title' => null,
            'selected_youtube_channel' => null,
            'youtube_score' => null,
            'include_in_export' => false,
        ]);

        return $item;
    }

    /**
     * Replace the item's candidates with the ranked ones and preselect the best:
     * score >= `preselect_threshold` selects it, >= `auto_include_threshold` also
     * includes it in the export. Below that the item keeps no link (search URL only).
     *
     * @param  list<array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string, score: int}>  $candidates  best first
     */
    public function storeYoutubeCandidates(ImportItem $item, array $candidates): ImportItem
    {
        DB::transaction(function () use ($item, $candidates): void {
            $item->youtubeCandidates()->delete();

            foreach (array_values($candidates) as $index => $candidate) {
                $item->youtubeCandidates()->create([
                    'video_id' => $candidate['video_id'],
                    'url' => $candidate['url'],
                    'title' => $candidate['title'],
                    'channel_title' => $candidate['channel_title'],
                    'description' => $candidate['description'],
                    'published_at' => $candidate['published_at'],
                    'score' => $candidate['score'],
                    'rank' => $index + 1,
                ]);
            }

            $best = $candidates[0] ?? null;
            $selection = $best !== null && $best['score'] >= config('services.youtube.preselect_threshold')
                ? [
                    'selected_youtube_url' => $best['url'],
                    'selected_youtube_video_id' => $best['video_id'],
                    'selected_youtube_title' => $best['title'],
                    'selected_youtube_channel' => $best['channel_title'],
                    'youtube_score' => $best['score'],
                    'include_in_export' => $best['score'] >= config('services.youtube.auto_include_threshold'),
                ]
                : [];

            $item->update([
                ...$selection,
                'status' => ImportItemStatus::Searched,
                'error_message' => $this->missingArtistNotice($item),
            ]);
        });

        return $item;
    }

    /**
     * API search failed: the item keeps its search URL and shows the notice.
     */
    public function markYoutubeSearchFailed(ImportItem $item, string $notice): ImportItem
    {
        $item->update([
            'status' => ImportItemStatus::Searched,
            'error_message' => $notice,
        ]);

        return $item;
    }

    /**
     * Flip include-in-export. Items without a selected link stay excluded.
     */
    public function toggleExport(ImportItem $item): ImportItem
    {
        if ($item->selected_youtube_url !== null) {
            $item->update(['include_in_export' => ! $item->include_in_export]);
        }

        return $item;
    }

    private function missingArtistNotice(ImportItem $item): ?string
    {
        return $item->artist === null ? ImportParserService::ARTIST_NOT_DETECTED : null;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }
}
