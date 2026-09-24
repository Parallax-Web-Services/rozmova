<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Stages;

use Parallax\Rozmova\Archive\Contracts\SpeechToText;
use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Stage;

final class TranscribeStage implements Stage
{
    public function __construct(
        private readonly SpeechToText $engine,
        private readonly string $language = 'uk',
    ) {
    }

    public function name(): string
    {
        return 'transcribe';
    }

    public function supports(Record $record): bool
    {
        return $record->sourceBytes !== null && !$record->has(Rendition::TRANSCRIPT);
    }

    public function process(Record $record): Record
    {
        return $record->with(Rendition::machineMade(
            Rendition::TRANSCRIPT,
            $this->language,
            $this->engine->transcribe((string) $record->sourceBytes, $this->language),
            $this->engine->engine(),
        ));
    }
}
