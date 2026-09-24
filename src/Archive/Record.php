<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use RuntimeException;

/**
 * An archive item and everything derived from it.
 *
 * Append-only: adding a rendition returns a new Record rather than mutating
 * this one, so a pipeline cannot quietly overwrite an earlier pass. The digest
 * covers provenance and every rendition, which makes a published record
 * checkable — an archive that can be silently edited is not evidence of
 * anything.
 */
final class Record
{
    /** @param array<string, Rendition> $renditions */
    private function __construct(
        public readonly string $id,
        public readonly Provenance $provenance,
        private readonly array $renditions,
        public readonly ?string $sourceBytes = null,
    ) {
    }

    public static function open(string $id, Provenance $provenance, ?string $sourceBytes = null): self
    {
        return new self($id, $provenance, [], $sourceBytes);
    }

    public function with(Rendition $rendition): self
    {
        return new self(
            $this->id,
            $this->provenance,
            [...$this->renditions, $rendition->kind => $rendition],
            $this->sourceBytes,
        );
    }

    public function has(string $kind): bool
    {
        return isset($this->renditions[$kind]);
    }

    public function get(string $kind): Rendition
    {
        return $this->renditions[$kind]
            ?? throw new RuntimeException(sprintf('Record %s has no "%s" rendition.', $this->id, $kind));
    }

    /** @return array<string, Rendition> */
    public function renditions(): array
    {
        return $this->renditions;
    }

    /**
     * Stable digest over provenance and renditions. Recomputable by anyone
     * holding the published record.
     */
    public function digest(): string
    {
        return hash('sha256', json_encode($this->toArray(false), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function toArray(bool $withDigest = true): array
    {
        $out = [
            'id'         => $this->id,
            'provenance' => $this->provenance->toArray(),
            'renditions' => array_map(
                static fn (Rendition $r): array => $r->toArray(),
                $this->renditions,
            ),
        ];

        if ($withDigest) {
            $out['digest'] = $this->digest();
        }

        return $out;
    }
}
