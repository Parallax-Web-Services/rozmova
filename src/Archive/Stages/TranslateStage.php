<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Stages;

use Parallax\Rozmova\Archive\Contracts\Translator;
use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Stage;

final class TranslateStage implements Stage
{
    public function __construct(
        private readonly Translator $engine,
        private readonly string $to = 'en',
    ) {
    }

    public function name(): string
    {
        return 'translate';
    }

    public function supports(Record $record): bool
    {
        return $record->has(Rendition::TRANSCRIPT) && !$record->has(Rendition::TRANSLATION);
    }

    public function process(Record $record): Record
    {
        $source = $record->get(Rendition::TRANSCRIPT);

        return $record->with(Rendition::machineMade(
            Rendition::TRANSLATION,
            $this->to,
            $this->engine->translate($source->text, $source->language, $this->to),
            $this->engine->engine(),
        ));
    }
}
