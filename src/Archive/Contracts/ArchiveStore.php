<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Contracts;

use Parallax\Rozmova\Archive\Record;

/**
 * Durable preservation. Holo's half of the handover.
 *
 * Distinct from publishing on purpose: a publisher serves a surface and may
 * drop whatever that surface does not need, whereas this keeps the record and
 * its source bytes so a claim made today can still be checked years from now.
 *
 * Implementations should be content-addressed and write-once. An archive whose
 * entries can be silently replaced preserves nothing.
 */
interface ArchiveStore
{
    /** @return string Reference to the stored item, e.g. "sha256:abcd...". */
    public function store(Record $record, ?string $sourceBytes = null): string;

    public function has(string $reference): bool;
}
