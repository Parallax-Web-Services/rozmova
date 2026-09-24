<?php

declare(strict_types=1);

require __DIR__ . '/../tests/bootstrap.php';

use Parallax\Rozmova\Archive\Drivers\FixtureSpeechToText;
use Parallax\Rozmova\Archive\Drivers\FixtureTranslator;
use Parallax\Rozmova\Archive\Intake;
use Parallax\Rozmova\Archive\Pipeline;
use Parallax\Rozmova\Archive\Sinks\FilesystemArchiveStore;
use Parallax\Rozmova\Archive\Sinks\FilesystemPublisher;
use Parallax\Rozmova\Archive\Stages\RomanizeStage;
use Parallax\Rozmova\Archive\Stages\TranscribeStage;
use Parallax\Rozmova\Archive\Stages\TranslateStage;
use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\UkrainianTransliterator;

/*
 * The whole chain with local stand-ins:
 *
 *   Relay  -> Intake -> transcribe -> translate -> romanize -> Holo  (archive)
 *                                                           -> Relay (publish)
 *
 * Fixture engines and filesystem sinks. Swap in HttpSpeechToText,
 * HttpTranslator and HttpPublisher and nothing in this file changes shape.
 */

$audio      = "\x00FAKE-AUDIO-BYTES\x00";
$transcript = 'Зеленський подякував ЗСУ за оборону Бахмута. Слава Україні!';

// What Relay hands over.
$envelope = [
    'id'           => 'tryzantha/2026-09-24/address',
    'source_url'   => 'https://www.president.gov.ua/news/example',
    'source_label' => 'Office of the President of Ukraine',
    'captured_at'  => '2026-09-24T15:00:00Z',
    'media_type'   => 'audio/wav',
    'sha256'       => hash('sha256', $audio),   // verified, not trusted
];

$record = (new Intake())->accept($envelope, $audio);

$pipeline = new Pipeline(
    new TranscribeStage(new FixtureSpeechToText([hash('sha256', $audio) => $transcript]), 'uk'),
    new TranslateStage(new FixtureTranslator([
        $transcript => 'Zelenskyy thanked the Armed Forces of Ukraine for the defence of Bakhmut. Glory to Ukraine!',
    ]), 'en'),
    new RomanizeStage(Romanizer::ukrainian(
        UkrainianTransliterator::NATIONAL,
        PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json'),
    )),
);

$record = $pipeline->run($record);

$root      = sys_get_temp_dir() . '/rozmova-demo';
$store     = new FilesystemArchiveStore($root . '/holo');
$publisher = new FilesystemPublisher($root . '/relay');

$reference = $store->store($record);
$published = $publisher->publish($record);

echo "pipeline\n", str_repeat('-', 72), "\n";
foreach ($pipeline->log() as $entry) {
    printf("  %-12s %s%s\n", $entry['stage'], $entry['status'], isset($entry['detail']) ? "  ({$entry['detail']})" : '');
}

echo "\nrenditions\n", str_repeat('-', 72), "\n";
foreach ($record->renditions() as $r) {
    printf("  %-13s %-8s %s\n", $r->kind, $r->language, $r->machine ? "[machine: {$r->generator}]" : "[human: {$r->generator}]");
    printf("  %s\n\n", $r->text);
}

echo "handover\n", str_repeat('-', 72), "\n";
printf("  archived (Holo)   %s\n", $reference);
printf("  published (Relay) %s\n", $published);
printf("  digest            %s\n", $record->digest());
