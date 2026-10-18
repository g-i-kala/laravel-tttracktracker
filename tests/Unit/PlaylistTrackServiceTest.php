<?php

namespace Tests\Unit;

use App\Services\ImportParserService;
use App\Services\PlaylistTrackService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaylistTrackServiceTest extends TestCase
{
    private PlaylistTrackService $playlistTracks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->playlistTracks = new PlaylistTrackService(new ImportParserService);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function titles(): array
    {
        return [
            'official video' => ['Daft Punk - Around The World (Official Music Video)', 'Daft Punk - Around The World'],
            'official audio in brackets' => ['Bicep - Glue [Official Audio]', 'Bicep - Glue'],
            'lyrics video' => ['Kombi - Słodkiego miłego życia (Lyric Video)', 'Kombi - Słodkiego miłego życia'],
            'visualiser' => ['Fred again.. - Delilah (Official Visualiser)', 'Fred again.. - Delilah'],
            'hd' => ['Lady Pank - Mniej niż zero [HD]', 'Lady Pank - Mniej niż zero'],
            'remastered year' => ['a-ha - Take On Me (Remastered 2015)', 'a-ha - Take On Me'],
            'premiere prefix' => ['Premiere: Midas Field - What We Got', 'Midas Field - What We Got'],
            'premiere pipe' => ['PREMIERE | Bicep - Glue (Extended Mix)', 'Bicep - Glue (Extended Mix)'],
            'version kept' => ['Bicep - Glue (Extended Mix)', 'Bicep - Glue (Extended Mix)'],
            'remix kept' => ['Bicep - Glue (Hammer Remix) (Official Video)', 'Bicep - Glue (Hammer Remix)'],
            'unknown brackets kept' => ['Brunello, Vargas - Ghost Dance (Club Edit)', 'Brunello, Vargas - Ghost Dance (Club Edit)'],
        ];
    }

    #[DataProvider('titles')]
    public function test_clean_title_removes_upload_noise(string $title, string $expected): void
    {
        $this->assertSame($expected, $this->playlistTracks->cleanTitle($title));
    }

    public function test_topic_channel_gives_the_artist(): void
    {
        $this->assertTrue($this->playlistTracks->isTopicChannel('Bicep - Topic'));
        $this->assertFalse($this->playlistTracks->isTopicChannel('Bicep'));
        $this->assertFalse($this->playlistTracks->isTopicChannel('Topic'));
        $this->assertFalse($this->playlistTracks->isTopicChannel(null));

        $lines = $this->playlistTracks->parsedLines([$this->video('Glue', 'Bicep - Topic')]);

        $this->assertSame(['Bicep', 'Glue', null], [$lines[0]['artist'], $lines[0]['title'], $lines[0]['error_message']]);
    }

    public function test_videos_become_items_with_their_link(): void
    {
        $lines = $this->playlistTracks->parsedLines([
            $this->video('Daft Punk - Around The World (Official Music Video)', 'Daft Punk', 'K0HSD_i2DvA'),
            $this->video('Glue', 'Ninja Tune', 'dwDns8x3Jb4'),
        ]);

        $this->assertSame([
            'line_number' => 1,
            'raw_line' => 'Daft Punk - Around The World (Official Music Video)',
            'artist' => 'Daft Punk',
            'title' => 'Around The World',
            'normalized_key' => 'daft punk|around the world',
            'error_message' => null,
            'selected_youtube_url' => 'https://www.youtube.com/watch?v=K0HSD_i2DvA',
            'selected_youtube_video_id' => 'K0HSD_i2DvA',
            'selected_youtube_title' => 'Daft Punk - Around The World (Official Music Video)',
            'selected_youtube_channel' => 'Daft Punk',
            'include_in_export' => true,
        ], $lines[0]);

        $this->assertSame(2, $lines[1]['line_number']);
        $this->assertNull($lines[1]['artist']);
        $this->assertSame('Glue', $lines[1]['title']);
        $this->assertSame(ImportParserService::ARTIST_NOT_DETECTED, $lines[1]['error_message']);
    }

    public function test_title_made_only_of_noise_is_kept(): void
    {
        $lines = $this->playlistTracks->parsedLines([$this->video('(Official Video)', 'Somebody')]);

        $this->assertSame('(Official Video)', $lines[0]['title']);
    }

    /**
     * @return array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, position: int}
     */
    private function video(string $title, ?string $channel, string $videoId = 'aaaaaaaaaaa'): array
    {
        return [
            'video_id' => $videoId,
            'url' => "https://www.youtube.com/watch?v={$videoId}",
            'title' => $title,
            'channel_title' => $channel,
            'description' => null,
            'position' => 0,
        ];
    }
}
