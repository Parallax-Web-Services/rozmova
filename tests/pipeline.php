<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\Archive\Contracts\SpeechToText;
use Parallax\Rozmova\Archive\Drivers\FixtureSpeechToText;
use Parallax\Rozmova\Archive\Drivers\FixtureTranslator;
use Parallax\Rozmova\Archive\Pipeline;
use Parallax\Rozmova\Archive\Provenance;
use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Stages\RomanizeStage;
use Parallax\Rozmova\Archive\Stages\TranscribeStage;
use Parallax\Rozmova\Archive\Stages\TranslateStage;
use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\UkrainianTransliterator;

$pass = $fail = 0;
$failures = [];

function check(string $label, bool $ok): void
{
    global $pass, $fail, $failures;
    if ($ok) { $pass++; } else { $fail++; $failures[] = "  $label"; }
}

$audio      = "\x00AUDIO\x00";
$transcript = 'Зеленський подякував ЗСУ за оборону Бахмута.';
$english    = 'Zelenskyy thanked the Armed Forces of Ukraine for the defence of Bakhmut.';

$romanizer = Romanizer::ukrainian(
    UkrainianTransliterator::NATIONAL,
    PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json'),
);

$makeRecord = static fn (): Record => Record::open(
    'test/001',
    Provenance::forBytes($audio, 'https://example.test/a', 'Test source', 'audio/wav'),
    $audio,
);

$makePipeline = static fn (): Pipeline => new Pipeline(
    new TranscribeStage(new FixtureSpeechToText([hash('sha256', $audio) => $transcript]), 'uk'),
    new TranslateStage(new FixtureTranslator([$transcript => $english]), 'en'),
    new RomanizeStage($romanizer),
);

// Full run.
$pipeline = $makePipeline();
$done     = $pipeline->run($makeRecord());

check('produces all three renditions', count($done->renditions()) === 3);
check('transcript is the Ukrainian source', $done->get(Rendition::TRANSCRIPT)->text === $transcript);
check('translation is English', $done->get(Rendition::TRANSLATION)->text === $english);
check('romanization applies preferences', str_starts_with($done->get(Rendition::ROMANIZATION)->text, 'Zelenskyy podiakuvav ZSU'));
check('every rendition is marked machine-made', array_reduce($done->renditions(), static fn ($c, $r) => $c && $r->machine, true));
check('romanization records its overrides', ($done->get(Rendition::ROMANIZATION)->notes['overrides'] ?? []) !== []);
check('all stages reported ok', array_reduce($pipeline->log(), static fn ($c, $e) => $c && $e['status'] === 'ok', true));

// Idempotence: a second pass has nothing to do.
$second = $makePipeline();
$again  = $second->run($done);
check('second pass skips every stage', array_reduce($second->log(), static fn ($c, $e) => $c && $e['status'] === 'skipped', true));
check('second pass adds nothing', count($again->renditions()) === 3);

// Append-only: adding a rendition does not mutate the original.
$base    = $makeRecord();
$derived = $base->with(Rendition::humanMade(Rendition::TRANSCRIPT, 'uk', 'редаговано', 'editor'));
check('with() leaves the original untouched', !$base->has(Rendition::TRANSCRIPT));
check('with() returns a new record carrying it', $derived->has(Rendition::TRANSCRIPT));
check('human renditions are not marked machine', $derived->get(Rendition::TRANSCRIPT)->machine === false);

// Digest covers content.
check('digest is stable for the same record', $done->digest() === $done->digest());
check('digest changes when a rendition is added', $base->digest() !== $derived->digest());

// A failing stage keeps what was already done.
final class ExplodingStt implements SpeechToText
{
    public function engine(): string { return 'exploding'; }
    public function transcribe(string $audio, string $language): string { throw new RuntimeException('engine unavailable'); }
}

$broken = new Pipeline(
    new TranscribeStage(new ExplodingStt(), 'uk'),
    new TranslateStage(new FixtureTranslator([$transcript => $english]), 'en'),
);
$partial = $broken->run($makeRecord());

check('failure stops the run', count($broken->log()) === 1);
check('failure is logged with its reason', ($broken->log()[0]['detail'] ?? '') === 'engine unavailable');
check('failure returns the record, not nothing', $partial->id === 'test/001');
check('failed run publishes no half-made rendition', $partial->renditions() === []);

// A translator with no fixture must fail loudly rather than echo Ukrainian as English.
$echoing = new Pipeline(
    new TranscribeStage(new FixtureSpeechToText([hash('sha256', $audio) => $transcript]), 'uk'),
    new TranslateStage(new FixtureTranslator([]), 'en'),
);
$echoed = $echoing->run($makeRecord());
check('missing translation fails instead of passing text through', !$echoed->has(Rendition::TRANSLATION));
check('transcript survives the translation failure', $echoed->has(Rendition::TRANSCRIPT));

echo "archive pipeline\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

exit($fail === 0 ? 0 : 1);
