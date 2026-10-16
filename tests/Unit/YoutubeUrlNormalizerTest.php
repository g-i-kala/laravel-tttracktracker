<?php

namespace Tests\Unit;

use App\Services\YoutubeUrlNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YoutubeUrlNormalizerTest extends TestCase
{
    private YoutubeUrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = new YoutubeUrlNormalizer;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validLinkProvider(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch with extra params' => ['https://www.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ&t=42s'],
            'watch without www' => ['https://youtube.com/watch?v=dQw4w9WgXcQ'],
            'mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtube music' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ&si=abc'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ'],
            'short link with share id' => ['https://youtu.be/dQw4w9WgXcQ?si=Xy12ab&t=10'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ?feature=share'],
            'http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'no scheme' => ['youtu.be/dQw4w9WgXcQ'],
            'surrounding spaces' => ['  https://youtu.be/dQw4w9WgXcQ  '],
            'uppercase host' => ['https://WWW.YOUTUBE.COM/watch?v=dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('validLinkProvider')]
    public function test_valid_links_are_normalized_to_a_watch_url(string $url): void
    {
        $this->assertSame('dQw4w9WgXcQ', $this->normalizer->videoId($url));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $this->normalizer->normalize($url));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidLinkProvider(): array
    {
        return [
            'empty' => [''],
            'other site' => ['https://vimeo.com/123456789'],
            'look-alike host' => ['https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ'],
            'search results page' => ['https://www.youtube.com/results?search_query=Bicep+Glue'],
            'channel' => ['https://www.youtube.com/@bicep'],
            'playlist only' => ['https://www.youtube.com/playlist?list=PL123'],
            'watch without id' => ['https://www.youtube.com/watch?t=10'],
            'id too short' => ['https://youtu.be/abc123'],
            'id with invalid characters' => ['https://youtu.be/dQw4w9WgX!Q'],
            'not a url' => ['Bicep - Glue'],
            'javascript scheme' => ['javascript:alert(1)'],
        ];
    }

    #[DataProvider('invalidLinkProvider')]
    public function test_invalid_links_are_rejected(string $url): void
    {
        $this->assertNull($this->normalizer->normalize($url));
    }
}
