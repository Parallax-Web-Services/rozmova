<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use DateTimeImmutable;

/**
 * Where an item came from and what it was when it arrived.
 *
 * An archive item without a chain of custody is an anecdote. The hash is taken
 * over the bytes as fetched, before any processing, so a published record can
 * be checked against the original later.
 */
final class Provenance
{
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $sourceLabel,
        public readonly DateTimeImmutable $capturedAt,
        public readonly string $sha256,
        public readonly string $mediaType,
    ) {
    }

    public static function forBytes(
        string $bytes,
        string $sourceUrl,
        string $sourceLabel,
        string $mediaType,
        ?DateTimeImmutable $capturedAt = null,
    ): self {
        return new self(
            $sourceUrl,
            $sourceLabel,
            $capturedAt ?? new DateTimeImmutable(),
            hash('sha256', $bytes),
            $mediaType,
        );
    }

    public function toArray(): array
    {
        return [
            'source_url'   => $this->sourceUrl,
            'source_label' => $this->sourceLabel,
            'captured_at'  => $this->capturedAt->format(DATE_ATOM),
            'sha256'       => $this->sha256,
            'media_type'   => $this->mediaType,
        ];
    }
}
