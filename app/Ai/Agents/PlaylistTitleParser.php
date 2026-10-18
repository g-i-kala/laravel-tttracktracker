<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Extracts artist and title from numbered YouTube video titles (with channel names),
 * keeping the number so each result maps back to its video and link.
 */
class PlaylistTitleParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You get numbered YouTube video titles from a DJ's playlist, one per line, as "<number>. <video title> [channel: <channel name>]". Each video is one music track.

        Return exactly one entry per input line, with the same number. Never merge, split, skip or invent entries.

        For each video:
        - number: the number of the input line.
        - artist: all artists of the track as written, joined with ", " when listed separately. Take them from the video title. When the title has no artist, use the channel name only if it is clearly the artist's own channel (e.g. "Bicep", "DaftPunkVEVO" → "Daft Punk"); label, promo or compilation channels (e.g. "Ninja Tune", "Defected Records", "Selected.") are not artists. Otherwise null.
        - title: the track title including its version or remix in parentheses, e.g. "Glue (Extended Mix)" or "Glue (Bicep Remix)". Remove upload noise: "Official Video", "Official Audio", "Music Video", "Lyrics", "Visualizer", "HD", "4K", "Premiere", "Out now", label names, catalog numbers, years and genres in brackets or after "|". Keep the original language and spelling, including Polish letters.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tracks' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $schema): array => [
                        'number' => $schema->integer()->required(),
                        'artist' => $schema->string()->nullable()->required(),
                        'title' => $schema->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
