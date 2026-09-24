<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Drivers;

use Parallax\Rozmova\Archive\Contracts\Translator;
use RuntimeException;

/**
 * Canned translations keyed by source text.
 *
 * Same purpose as FixtureSpeechToText: exercise the pipeline without a vendor.
 * It refuses unknown input rather than echoing it, so a missing fixture fails
 * loudly instead of publishing untranslated Ukrainian as English.
 */
final class FixtureTranslator implements Translator
{
    /** @param array<string, string> $pairs source text => translation */
    public function __construct(private readonly array $pairs)
    {
    }

    public function engine(): string
    {
        return 'fixture';
    }

    public function translate(string $text, string $from, string $to): string
    {
        return $this->pairs[trim($text)]
            ?? throw new RuntimeException(sprintf('No fixture translation for %s -> %s.', $from, $to));
    }
}
