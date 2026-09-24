<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Contracts;

/**
 * Whatever turns audio into text.
 *
 * Deliberately narrow. The engine behind this will be replaced several times --
 * Whisper is a 2023 model and the Ukrainian leaderboard is already led by
 * Conformers -- and nothing above this interface should have to notice.
 */
interface SpeechToText
{
    /** Identifier recorded against every transcript this engine produces. */
    public function engine(): string;

    public function transcribe(string $audio, string $language): string;
}
