<?php

declare(strict_types=1);

require __DIR__ . '/../tests/bootstrap.php';

use Parallax\Rozmova\Archive\Drivers\FixtureSpeechToText;
use Parallax\Rozmova\Archive\Drivers\FixtureTranslator;
use Parallax\Rozmova\Archive\Pipeline;
use Parallax\Rozmova\Archive\Provenance;
use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Stages\RomanizeStage;
use Parallax\Rozmova\Archive\Stages\TranscribeStage;
use Parallax\Rozmova\Archive\Stages\TranslateStage;
use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;

/*
 * End to end with fixture engines: ingest -> transcribe -> translate ->
 * romanize -> publishable record. Swap the two fixture drivers for real ones
 * and nothing else here changes.
 */

$audio = "\x00FAKE-AUDIO-BYTES\x00";

$transcript = 'Зеленський подякував ЗСУ за оборону Бахмута. Слава Україні!';

$record = Record::open(
    'tryzantha/2026-09-24/address',
    Provenance::forBytes(
        $audio,
        'https://www.president.gov.ua/news/example',
        'Office of the President of Ukraine',
        'audio/wav',
    ),
    $audio,
);

$pipeline = new Pipeline(
    new TranscribeStage(new FixtureSpeechToText([hash('sha256', $audio) => $transcript]), 'uk'),
    new TranslateStage(new FixtureTranslator([
        $transcript => 'Zelenskyy thanked the Armed Forces of Ukraine for the defence of Bakhmut. Glory to Ukraine!',
    ]), 'en'),
    new RomanizeStage(Romanizer::ukrainian(
        \Parallax\Rozmova\UkrainianTransliterator::NATIONAL,
        PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json'),
    )),
);

$record = $pipeline->run($record);

echo "pipeline log\n", str_repeat('-', 72), "\n";
foreach ($pipeline->log() as $entry) {
    printf("  %-12s %s%s\n", $entry['stage'], $entry['status'], isset($entry['detail']) ? "  ({$entry['detail']})" : '');
}

echo "\nrenditions\n", str_repeat('-', 72), "\n";
foreach ($record->renditions() as $r) {
    printf("  %-13s %-8s %s\n", $r->kind, $r->language, $r->machine ? '[machine: ' . $r->generator . ']' : '[human: ' . $r->generator . ']');
    printf("  %s\n\n", $r->text);
}

echo "record digest\n", str_repeat('-', 72), "\n  ", $record->digest(), "\n";

echo "\npublishable payload\n", str_repeat('-', 72), "\n";
echo json_encode($record->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
