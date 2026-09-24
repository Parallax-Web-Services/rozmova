<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

interface Transliterator
{
    /** Romanize Cyrillic text, leaving anything outside the alphabet untouched. */
    public function transliterate(string $text): string;

    /** Scheme names this transliterator accepts. */
    public static function schemes(): array;
}
