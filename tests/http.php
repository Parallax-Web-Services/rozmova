<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\Archive\Drivers\HttpSpeechToText;
use Parallax\Rozmova\Archive\Drivers\HttpTransport;
use Parallax\Rozmova\Archive\Drivers\HttpTranslator;
use Parallax\Rozmova\Archive\Drivers\TransportException;
use Parallax\Rozmova\Archive\Pipeline;
use Parallax\Rozmova\Archive\Provenance;
use Parallax\Rozmova\Archive\Record;
use Parallax\Rozmova\Archive\Rendition;
use Parallax\Rozmova\Archive\Stages\RomanizeStage;
use Parallax\Rozmova\Archive\Stages\TranscribeStage;
use Parallax\Rozmova\Archive\Stages\TranslateStage;
use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;

$port = 8731;
$base = "http://127.0.0.1:$port";

$server = proc_open(
    sprintf('exec php -S 127.0.0.1:%d %s', $port, escapeshellarg(__DIR__ . '/fixtures/fake-service.php')),
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
    $pipes,
);

if (!is_resource($server)) {
    fwrite(STDERR, "could not start fixture server\n");
    exit(1);
}

// Wait for it to accept connections.
for ($i = 0; $i < 50; $i++) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if ($probe) { fclose($probe); break; }
    usleep(100_000);
}

$pass = $fail = 0;
$failures = [];

function check(string $label, bool $ok): void
{
    global $pass, $fail, $failures;
    if ($ok) { $pass++; } else { $fail++; $failures[] = "  $label"; }
}

function throws(callable $fn, string $needle = ''): bool
{
    try {
        $fn();
        return false;
    } catch (TransportException $e) {
        return $needle === '' || str_contains($e->getMessage(), $needle);
    }
}

try {
    $transport = new HttpTransport($base, 10.0, 2);

    // Happy path, both drivers.
    $stt = new HttpSpeechToText($transport);
    $text = $stt->transcribe("\x00AUDIO\x00", 'uk');
    check('ASR returns the transcript', str_starts_with($text, 'Зеленський подякував'));
    check('ASR reports the engine the service named', $stt->engine() === 'nvidia/stt_ua_fastconformer_hybrid_large_pc');

    $mt = new HttpTranslator($transport);
    $translated = $mt->translate('Слава Україні!', 'uk', 'en');
    check('MT returns the translation', str_starts_with($translated, 'Zelenskyy thanked'));
    check('MT reports the engine the service named', $mt->engine() === 'facebook/nllb-200-distilled-600M');

    // Retry: two 503s then success.
    file_get_contents("$base/flaky/reset");
    $flaky = new HttpTranslator($transport, 'fallback', '/flaky');
    check('retries through 5xx and succeeds', $flaky->translate('x', 'uk', 'en') === 'recovered');

    // A 4xx must not be retried.
    file_get_contents("$base/bad/reset");
    $bad = new HttpTranslator($transport, 'fallback', '/bad');
    check('4xx raises', throws(static fn () => $bad->translate('x', 'uk', 'en'), 'HTTP 400'));
    $count = json_decode(file_get_contents("$base/bad/count"), true)['count'] ?? 0;
    check('4xx is attempted exactly once, not retried', $count === 1);

    // Degenerate but well-formed responses.
    $empty = new HttpTranslator($transport, 'fallback', '/empty');
    check('blank text raises rather than publishing nothing', throws(static fn () => $empty->translate('x', 'uk', 'en'), 'no usable'));

    $garbage = new HttpTranslator($transport, 'fallback', '/garbage');
    check('non-JSON raises', throws(static fn () => $garbage->translate('x', 'uk', 'en')));

    $missing = new HttpTranslator($transport, 'fallback', '/nope');
    check('404 raises', throws(static fn () => $missing->translate('x', 'uk', 'en'), 'HTTP 404'));

    // Unreachable host: connection failure, not a hang.
    $offline = new HttpTranslator(new HttpTransport('http://127.0.0.1:9', 2.0, 1), 'fallback');
    check('unreachable service raises', throws(static fn () => $offline->translate('x', 'uk', 'en')));

    // Whole pipeline over HTTP.
    $audio  = "\x00AUDIO\x00";
    $record = Record::open(
        'tryzantha/http/001',
        Provenance::forBytes($audio, 'https://example.test/s', 'Test', 'audio/wav'),
        $audio,
    );

    $pipeline = new Pipeline(
        new TranscribeStage(new HttpSpeechToText($transport), 'uk'),
        new TranslateStage(new HttpTranslator($transport), 'en'),
        new RomanizeStage(Romanizer::ukrainian(
            \Parallax\Rozmova\UkrainianTransliterator::NATIONAL,
            PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json'),
        )),
    );

    $done = $pipeline->run($record);

    check('pipeline completes over HTTP', count($done->renditions()) === 3);
    check('romanization applied over HTTP transcript', str_starts_with($done->get(Rendition::ROMANIZATION)->text, 'Zelenskyy podiakuvav ZSU'));
    check('record names the real engine, not a placeholder', $done->get(Rendition::TRANSCRIPT)->generator === 'nvidia/stt_ua_fastconformer_hybrid_large_pc');

    // A dead ASR service must not produce a half-published record.
    $deadPipeline = new Pipeline(
        new TranscribeStage(new HttpSpeechToText(new HttpTransport('http://127.0.0.1:9', 2.0, 0)), 'uk'),
        new TranslateStage(new HttpTranslator($transport), 'en'),
    );
    $deadRun = $deadPipeline->run($record);
    check('dead ASR stops the run', count($deadPipeline->log()) === 1);
    check('dead ASR publishes no renditions', $deadRun->renditions() === []);
} finally {
    proc_terminate($server);
    proc_close($server);
}

echo "http drivers\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

exit($fail === 0 ? 0 : 1);
