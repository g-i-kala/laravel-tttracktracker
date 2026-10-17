<?php

namespace Tests\Unit;

use App\Services\YoutubeScoringService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YoutubeScoringServiceTest extends TestCase
{
    private YoutubeScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoring = new YoutubeScoringService;
    }

    /**
     * Track (artist, title, label), video (title, channel, description), expected score.
     *
     * @return array<string, array{?string, string, ?string, string, ?string, ?string, int}>
     */
    public static function scoreProvider(): array
    {
        return [
            'official audio on the artist channel' => ['Daft Punk', 'Around the World', null, 'Daft Punk - Around the World (Official Audio)', 'Daft Punk', null, 90],
            'topic channel, provided to youtube, edit not penalized' => ['Daft Punk', 'Around the World', null, 'Around the World (Radio Edit)', 'Daft Punk - Topic', 'Provided to YouTube by Parlophone UK', 90],
            're-upload on another channel' => ['Daft Punk', 'Around the World', null, 'Daft Punk - Around The World [Remastered]', 'RicDiOlive', null, 70],
            'official live video' => ['Daft Punk', 'Around the World', null, 'Daft Punk - Around the World (Official Live Video 2007)', 'Daft Punk', null, 55],
            'each unwanted keyword costs once' => ['Daft Punk', 'Around the World', null, 'Daft Punk - Around the World (Sped Up + Lyrics)', 'some fan', null, 10],
            'keyword in the track title is not penalized' => ['Oasis', 'Live Forever', null, 'Oasis - Live Forever (Official Video)', 'Oasis', null, 90],
            'glued vevo channel' => ['Oasis', 'Wonderwall', null, 'Oasis - Wonderwall', 'OasisVEVO', null, 85],
            'requested remix found' => ['Bicep', 'Glue (Hammer Remix)', null, 'Bicep - Glue (Hammer Remix)', 'Hammer', null, 80],
            'requested remix, video is the original' => ['Bicep', 'Glue (Hammer Remix)', null, 'Bicep - Glue', 'Bicep', null, 60],
            'requested remix, video is another remix' => ['Bicep', 'Glue (Hammer Remix)', null, 'Bicep - Glue (Bonobo Remix)', 'Bonobo', null, 45],
            'original requested, video is a remix' => ['Bicep', 'Glue (Original Mix)', null, 'Bicep - Glue (Bonobo Remix)', 'Bonobo', null, 45],
            'extended mix on the label channel' => ['Midas Field, Marijn Jansen', 'What We Got (Extended Mix)', 'Armada Music', 'Midas Field & Marijn Jansen - What We Got (Extended Mix)', 'Armada Music', null, 95],
            'one of several artists owns the channel' => ['Midas Field, Marijn Jansen', 'What We Got', null, 'Midas Field & Marijn Jansen - What We Got', 'Marijn Jansen', null, 85],
            'featured artist not required in the title' => ['Bonobo', 'Cirrus (feat. Erykah Badu)', null, 'Bonobo - Cirrus', 'Bonobo', null, 85],
            'diacritics and case ignored' => ['Röyksopp', 'Eple', null, 'ROYKSOPP - EPLE', 'Röyksopp', null, 85],
            'artist only in the channel name' => ['Bicep', 'Glue', null, 'Glue', 'Bicep', null, 85],
            'no artist on the track' => [null, 'Teach Me', null, 'Teach Me', 'random', null, 40],
            'half of the title' => ['Bicep', 'Apricots Forever', null, 'Bicep - Apricots', 'someone', null, 50],
            'unrelated video' => ['Bicep', 'Glue', null, 'Cooking pasta at home', 'Chef', null, 0],
            'never below zero' => ['Bicep', 'Glue', null, 'Glue reaction live set cover karaoke', 'x', null, 0],
        ];
    }

    #[DataProvider('scoreProvider')]
    public function test_score(?string $artist, string $title, ?string $label, string $videoTitle, ?string $channel, ?string $description, int $expectedScore): void
    {
        $result = ['title' => $videoTitle, 'channel_title' => $channel, 'description' => $description];

        $this->assertSame($expectedScore, $this->scoring->score($artist, $title, $label, $result));
    }

    public function test_rank_adds_scores_sorts_and_limits(): void
    {
        $results = [
            $this->searchResult('reupload', 'Daft Punk - Around The World', 'fan'),
            $this->searchResult('live', 'Daft Punk - Around the World (Live)', 'Daft Punk'),
            $this->searchResult('official', 'Daft Punk - Around the World (Official Audio)', 'Daft Punk'),
            $this->searchResult('tie', 'Daft Punk - Around the World', 'another fan'),
        ];

        $ranked = $this->scoring->rank('Daft Punk', 'Around the World', null, $results, 3);

        $this->assertSame(['official', 'reupload', 'tie'], array_column($ranked, 'video_id'));
        $this->assertSame([90, 70, 70], array_column($ranked, 'score'));
        $this->assertSame('https://www.youtube.com/watch?v=official', $ranked[0]['url']);
    }

    public function test_rank_without_results(): void
    {
        $this->assertSame([], $this->scoring->rank('Bicep', 'Glue', null, [], 3));
    }

    /**
     * @return array{video_id: string, url: string, title: string, channel_title: ?string, description: ?string, published_at: ?string}
     */
    private function searchResult(string $videoId, string $title, string $channel): array
    {
        return [
            'video_id' => $videoId,
            'url' => 'https://www.youtube.com/watch?v='.$videoId,
            'title' => $title,
            'channel_title' => $channel,
            'description' => null,
            'published_at' => null,
        ];
    }
}
