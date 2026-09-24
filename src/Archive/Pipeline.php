<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive;

use Throwable;

/**
 * Runs stages in order, skipping any that have nothing to do.
 *
 * A stage that throws does not lose the work already done: the run stops, the
 * record is returned as far as it got, and the failure is in the log. A partial
 * archive record is worth more than a dropped one.
 */
final class Pipeline
{
    /** @var list<Stage> */
    private array $stages;

    /** @var list<array{stage: string, status: string, detail?: string}> */
    private array $log = [];

    public function __construct(Stage ...$stages)
    {
        $this->stages = array_values($stages);
    }

    /** @return list<array{stage: string, status: string, detail?: string}> */
    public function log(): array
    {
        return $this->log;
    }

    public function run(Record $record): Record
    {
        $this->log = [];

        foreach ($this->stages as $stage) {
            if (!$stage->supports($record)) {
                $this->log[] = ['stage' => $stage->name(), 'status' => 'skipped'];
                continue;
            }

            try {
                $record      = $stage->process($record);
                $this->log[] = ['stage' => $stage->name(), 'status' => 'ok'];
            } catch (Throwable $e) {
                $this->log[] = [
                    'stage'  => $stage->name(),
                    'status' => 'failed',
                    'detail' => $e->getMessage(),
                ];

                return $record;
            }
        }

        return $record;
    }
}
