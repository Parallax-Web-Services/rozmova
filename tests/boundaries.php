<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\Archive\Drivers\FixtureSpeechToText;
use Parallax\Rozmova\Archive\Drivers\FixtureTranslator;
use Parallax\Rozmova\Archive\Intake;
use Parallax\Rozmova\Archive\IntakeException;
use Parallax\Rozmova\Archive\Pipeline;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Sinks\FilesystemArchiveStore;
use Parallax\Rozmova\Archive\Sinks\FilesystemPublisher;
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

function throws(callable $fn, string $needle = ''): bool
{
    try { $fn(); return false; }
    catch (IntakeException $e) { return $needle === '' || str_contains($e->getMessage(), $needle); }
}

$intake = new Intake();
$audio  = "\x00SPEECH-AUDIO\x00";

$envelope = [
    'id'           => 'tryzantha/2026-09-24/address',
    'source_url'   => 'https://www.president.gov.ua/news/example',
    'source_label' => 'Office of the President of Ukraine',
    'captured_at'  => '2026-09-24T15:00:00Z',
    'media_type'   => 'audio/wav',
];

// --- Intake: the Relay boundary -----------------------------------------

$record = $intake->accept($envelope, $audio);
check('accepts a well-formed audio envelope', $record->id === 'tryzantha/2026-09-24/address');
check('hashes the payload as received', $record->provenance->sha256 === hash('sha256', $audio));
check('keeps the capture time', $record->provenance->capturedAt->format('Y-m-d') === '2026-09-24');

// The declared hash is verified, not trusted.
$declared = $envelope + ['sha256' => hash('sha256', $audio)];
check('accepts a correct declared hash', $intake->accept($declared, $audio)->id !== '');

$wrong = $envelope + ['sha256' => str_repeat('a', 64)];
check('rejects a payload that does not match its hash', throws(static fn () => $intake->accept($wrong, $audio), 'does not match'));

$malformed = $envelope + ['sha256' => 'not-a-hash'];
check('rejects a malformed hash', throws(static fn () => $intake->accept($malformed, $audio), 'sha256'));

// Required fields.
foreach (['id', 'source_url', 'source_label', 'captured_at', 'media_type'] as $field) {
    $missing = $envelope;
    unset($missing[$field]);
    check("rejects an envelope missing $field", throws(static fn () => $intake->accept($missing, $audio), $field));
}

check('rejects an unparseable timestamp', throws(
    static fn () => $intake->accept(['captured_at' => 'last tuesday'] + $envelope, $audio),
    'captured_at',
));
check('rejects a capture time in the future', throws(
    static fn () => $intake->accept(['captured_at' => '2099-01-01T00:00:00Z'] + $envelope, $audio),
    'future',
));
check('rejects an envelope with no payload at all', throws(static fn () => $intake->accept($envelope, null), 'neither'));

// A text item arrives already transcribed, and is not machine output.
$textItem = $intake->accept(
    ['media_type' => 'text/plain', 'text' => 'Слава Україні!'] + $envelope,
    null,
);
check('text items arrive with a transcript', $textItem->has(Rendition::TRANSCRIPT));
check('text transcripts are not marked machine-made', $textItem->get(Rendition::TRANSCRIPT)->machine === false);
check('text transcripts are attributed to the publisher', $textItem->get(Rendition::TRANSCRIPT)->generator === 'Office of the President of Ukraine');

// --- Full chain: intake -> transform -> archive -> publish ---------------

$transcript = 'Зеленський подякував ЗСУ за оборону Бахмута.';
$english    = 'Zelenskyy thanked the Armed Forces of Ukraine for the defence of Bakhmut.';

$pipeline = new Pipeline(
    new TranscribeStage(new FixtureSpeechToText([hash('sha256', $audio) => $transcript]), 'uk'),
    new TranslateStage(new FixtureTranslator([$transcript => $english]), 'en'),
    new RomanizeStage(Romanizer::ukrainian(
        UkrainianTransliterator::NATIONAL,
        PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json'),
    )),
);

$root  = sys_get_temp_dir() . '/rozmova-boundaries-' . bin2hex(random_bytes(4));
$store = new FilesystemArchiveStore($root . '/holo');
$relay = new FilesystemPublisher($root . '/relay');

$done      = $pipeline->run($intake->accept($envelope, $audio));
$reference = $store->store($done);
$published = $relay->publish($done);

check('chain completes', count($done->renditions()) === 3);
check('archive returns a content-addressed reference', str_starts_with($reference, 'sha256:'));
check('archive can find what it stored', $store->has($reference));
check('archived record round-trips', ($store->read($reference)['id'] ?? null) === $done->id);
check('source bytes are stored by their own hash', is_file($root . '/holo/objects/' . substr(hash('sha256',$audio),0,2) . '/' . substr(hash('sha256',$audio),2,2) . '/' . hash('sha256', $audio)));
check('published payload is on disk', is_file($published));
check('published payload carries the digest', (json_decode((string) file_get_contents($published), true)['digest'] ?? '') === $done->digest());

// Write-once: storing the same record twice does not duplicate or corrupt.
$again = $store->store($done);
check('re-storing is idempotent', $again === $reference && $store->has($again));

// A corrected record is a different digest, so both survive in the archive.
$corrected = $done->with(Rendition::humanMade(Rendition::TRANSLATION, 'en', 'Corrected translation.', 'editor'));
$correctedRef = $store->store($corrected);
check('a correction gets its own reference', $correctedRef !== $reference);
check('the original is still retrievable after a correction', $store->has($reference));

// Publishing the correction replaces the published copy -- that is the point.
$relay->publish($corrected);
check('publishing a correction overwrites the public copy', (json_decode((string) file_get_contents($published), true)['digest'] ?? '') === $corrected->digest());

exec('rm -rf ' . escapeshellarg($root));

echo "boundaries: intake, archive, publish\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

exit($fail === 0 ? 0 : 1);
