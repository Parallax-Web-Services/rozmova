<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use DateTimeImmutable;

/**
 * One derived version of an item: a transcript, a translation, a romanization.
 *
 * Every rendition names what produced it. A machine translation of a wartime
 * speech that is not visibly marked as machine output is a fabricated quote
 * waiting to be screenshotted, so `machine` is required rather than inferred.
 */
final class Rendition
{
    public const TRANSCRIPT   = 'transcript';
    public const TRANSLATION  = 'translation';
    public const ROMANIZATION = 'romanization';

    public function __construct(
        public readonly string $kind,
        public readonly string $language,
        public readonly string $text,
        public readonly string $generator,
        public readonly bool $machine,
        public readonly DateTimeImmutable $generatedAt,
        public readonly array $notes = [],
    ) {
    }

    public static function machineMade(
        string $kind,
        string $language,
        string $text,
        string $generator,
        array $notes = [],
    ): self {
        return new self($kind, $language, $text, $generator, true, new DateTimeImmutable(), $notes);
    }

    public static function humanMade(string $kind, string $language, string $text, string $by): self
    {
        return new self($kind, $language, $text, $by, false, new DateTimeImmutable());
    }

    public function toArray(): array
    {
        return array_filter([
            'kind'         => $this->kind,
            'language'     => $this->language,
            'text'         => $this->text,
            'generator'    => $this->generator,
            'machine'      => $this->machine,
            'generated_at' => $this->generatedAt->format(DATE_ATOM),
            'notes'        => $this->notes,
        ], static fn ($v) => $v !== [] );
    }
}
