<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use Parallax\Rozmova\Archive\Contracts\SpeechToText;
use RuntimeException;

/**
 * Canned transcripts keyed by the hash of the audio.
 *
 * Not an engine. It exists so the pipeline can be exercised end to end without
 * credentials, and so tests assert pipeline behaviour rather than ASR accuracy.
 * Real drivers implement the same interface and nothing above them changes.
 */
final class FixtureSpeechToText implements SpeechToText
{
    /** @param array<string, string> $byHash sha256 of audio => transcript */
    public function __construct(private readonly array $byHash)
    {
    }

    public function engine(): string
    {
        return 'fixture';
    }

    public function transcribe(string $audio, string $language): string
    {
        $hash = hash('sha256', $audio);

        return $this->byHash[$hash]
            ?? throw new RuntimeException(sprintf('No fixture transcript for audio %s.', substr($hash, 0, 12)));
    }
}
