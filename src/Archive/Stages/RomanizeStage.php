<?php

declare(strict_types=1);

namespace Parallax\Rozmova\Archive\Stages;

use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Stage;
use Parallax\Rozmova\Romanizer;

/**
 * Cyrillic transcript -> Latin script.
 *
 * Unlike the other two stages this one is deterministic and local: no model, no
 * network, no cost. It also records which editorial overrides fired, so a
 * reader who queries a spelling can be shown the rule rather than asked to
 * trust it.
 */
final class RomanizeStage implements Stage
{
    public function __construct(
        private readonly Romanizer $romanizer,
        private readonly string $language = 'uk-Latn',
    ) {
    }

    public function name(): string
    {
        return 'romanize';
    }

    public function supports(Record $record): bool
    {
        return $record->has(Rendition::TRANSCRIPT) && !$record->has(Rendition::ROMANIZATION);
    }

    public function process(Record $record): Record
    {
        $source = $record->get(Rendition::TRANSCRIPT);
        $text   = $this->romanizer->romanize($source->text);

        return $record->with(Rendition::machineMade(
            Rendition::ROMANIZATION,
            $this->language,
            $text,
            'rozmova',
            ['overrides' => $this->romanizer->appliedOverrides()],
        ));
    }
}
