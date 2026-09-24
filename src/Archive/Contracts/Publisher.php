<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Contracts;

use Parallax\Rozmova\Archive\Record;

/**
 * Where a finished record goes to be seen. Relay's half of the handover.
 *
 * Publication is not storage. A publisher may reformat, paginate or drop
 * fields to suit a surface; what it must not do is become the only copy.
 * Durability is ArchiveStore's job.
 */
interface Publisher
{
    /** @return string Reference to the published item, e.g. a URL or path. */
    public function publish(Record $record): string;
}
