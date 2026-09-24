<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

interface Stage
{
    public function name(): string;

    /** Whether this stage has anything to do for the record as it stands. */
    public function supports(Record $record): bool;

    public function process(Record $record): Record;
}
