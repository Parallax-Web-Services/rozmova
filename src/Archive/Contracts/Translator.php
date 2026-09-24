<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Contracts;

interface Translator
{
    /** Identifier recorded against every translation this engine produces. */
    public function engine(): string;

    public function translate(string $text, string $from, string $to): string;
}
