<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Extracts tracks (artist, title, genre, BPM, key, label) from any pasted track list:
 * plain `Artist - Title` lines, store copy-pastes (Beatport etc.), tracklists with timestamps.
 */
class TrackListParser implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        You extract music tracks from text that a DJ pasted. The text can be plain "Artist - Title" lines, a copy-paste from a music store or streaming service (for example Beatport, where one track spans several lines: title and mix, artists, label, genre, BPM and key, release date, price), or a tracklist with numbers and timestamps.

        Return one entry per track, in the order the tracks appear. Never invent tracks; ignore headers, column names, prices, release dates, numbering and timestamps.

        For each track:
        - artist: all artists exactly as written, joined with ", " when listed separately. Use null when no artist is given.
        - title: the track title including its version in parentheses, e.g. "What We Got (Original Mix)" or "Glue (Bicep Remix)". When the source writes the version without parentheses ("What We Got Original Mix"), add them. Remove store badges glued to titles (e.g. "Exclusive", "Hype", "Pre-order"). When a release name (EP or album) appears next to the track title, use the track title.
        - genre: the genre listed for this track in the source, or null when none is listed. Do not guess.
        - bpm: the tempo listed for this track as a number (e.g. "128 BPM" → 128), or null when none is listed. Do not guess.
        - key: the musical key listed for this track exactly as written (e.g. "E Minor", "F♯ Major", "8A"), or null when none is listed. Do not guess.
        - label: the record label listed for this track (e.g. on Beatport the line after the artists), or null when none is listed. Remixers are not the label. Do not guess.
        - source: the original text of this track condensed to one line, with the original lines joined by " | ".

        If the last track is cut off, still return it with the fields that are available.
        INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'tracks' => $schema->array()
                ->items(
                    $schema->object(fn (JsonSchema $schema): array => [
                        'artist' => $schema->string()->nullable()->required(),
                        'title' => $schema->string()->required(),
                        'genre' => $schema->string()->nullable()->required(),
                        'bpm' => $schema->number()->nullable()->required(),
                        'key' => $schema->string()->nullable()->required(),
                        'label' => $schema->string()->nullable()->required(),
                        'source' => $schema->string()->required(),
                    ])
                )
                ->required(),
        ];
    }
}
