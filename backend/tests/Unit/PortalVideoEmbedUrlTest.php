<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PortalVideoEmbedUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function urls(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=rm45BkhIBxY', 'https://www.youtube.com/embed/rm45BkhIBxY'],
            'watch with other params first' => ['https://www.youtube.com/watch?feature=share&v=rm45BkhIBxY', 'https://www.youtube.com/embed/rm45BkhIBxY'],
            'short link' => ['https://youtu.be/rm45BkhIBxY', 'https://www.youtube.com/embed/rm45BkhIBxY'],
            'short link with start' => ['https://youtu.be/rm45BkhIBxY?t=42', 'https://www.youtube.com/embed/rm45BkhIBxY?start=42'],
            'shorts' => ['https://www.youtube.com/shorts/rm45BkhIBxY', 'https://www.youtube.com/embed/rm45BkhIBxY'],
            'already embed' => ['https://www.youtube.com/embed/t9a9ApEXCRc', 'https://www.youtube.com/embed/t9a9ApEXCRc'],
            'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'unknown host untouched' => ['https://cdn.example.com/video.mp4', 'https://cdn.example.com/video.mp4'],
            'whitespace trimmed' => ['  https://youtu.be/rm45BkhIBxY ', 'https://www.youtube.com/embed/rm45BkhIBxY'],
        ];
    }

    #[DataProvider('urls')]
    public function test_it_normalises_video_urls(string $input, string $expected): void
    {
        require_once __DIR__.'/../../app/Helpers/portal_content.php';

        $this->assertSame($expected, portal_video_embed_url($input));
    }
}
