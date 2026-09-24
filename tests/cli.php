<?php

declare(strict_types=1);

$bin  = escapeshellarg(__DIR__ . '/../bin/rozmova');
$prefs = escapeshellarg(__DIR__ . '/../data/preferences.uk.json');

$pass = $fail = 0;
$failures = [];

/** @return array{0:int,1:string,2:string} status, stdout, stderr */
function run(string $command, string $stdin = ''): array
{
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes);

    if (!is_resource($process)) {
        return [-1, '', 'could not start'];
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);

    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $out, $err];
}

function check(string $label, bool $ok): void
{
    global $pass, $fail, $failures;
    if ($ok) { $pass++; } else { $fail++; $failures[] = "  $label"; }
}

// Basic romanization.
[$status, $out] = run("php $bin translit " . escapeshellarg('Київ'));
check('translit romanizes', trim($out) === 'Kyiv');
check('translit exits zero', $status === 0);

// Preferences are applied when supplied.
[, $out] = run("php $bin translit --prefs=$prefs " . escapeshellarg('Зеленський'));
check('preferences are applied', trim($out) === 'Zelenskyy');

// Without them the scheme's own answer comes through.
[, $out] = run("php $bin translit " . escapeshellarg('Зеленський'));
check('without preferences the standard answer is used', trim($out) === 'Zelenskyi');

// Overrides go to stderr so stdout stays pipeable.
[, $out, $err] = run("php $bin translit --prefs=$prefs " . escapeshellarg('Зеленський'));
check('override notes go to stderr, not stdout', !str_contains($out, 'override') && str_contains($err, 'override'));

// stdin.
[, $out] = run("php $bin translit", 'Харків');
check('reads stdin when no argument given', trim($out) === 'Kharkiv');

// Russian.
[, $out] = run("php $bin translit --lang=ru " . escapeshellarg('Достоевский'));
check('romanizes Russian', trim($out) === 'Dostoyevskiy');

[, $out] = run("php $bin translit --lang=ru --scheme=passport " . escapeshellarg('Дмитрий'));
check('honours an explicit scheme', trim($out) === 'Dmitrii');

// compare shows every scheme.
[, $out] = run("php $bin compare " . escapeshellarg('Львів'));
check('compare shows all Ukrainian schemes', str_contains($out, 'national') && str_contains($out, 'bgn') && str_contains($out, 'learner'));

// JSON output.
[, $out] = run("php $bin translit --json --prefs=$prefs " . escapeshellarg('Зеленський'));
$decoded = json_decode(trim($out), true);
check('--json emits valid JSON', is_array($decoded));
check('--json carries the output', ($decoded['output'] ?? '') === 'Zelenskyy');
check('--json carries the overrides', ($decoded['overrides'][0]['latin'] ?? '') === 'Zelenskyy');

// lint.
[$status, $out] = run("php $bin lint $prefs");
check('lint passes a good list', $status === 0 && str_contains($out, 'OK'));

$broken = sys_get_temp_dir() . '/rozmova-bad-' . bin2hex(random_bytes(4)) . '.json';
file_put_contents($broken, json_encode(['entries' => [
    ['forms' => ['Київ'], 'latin' => 'Kyiv'],
    ['forms' => ['Київ'], 'latin' => 'Kiev'],
]]));
[$status, $out] = run("php $bin lint " . escapeshellarg($broken));
check('lint fails a conflicting list', $status !== 0);
check('lint names the conflict', str_contains($out, 'Preference conflict'));
unlink($broken);

// Errors.
[$status] = run("php $bin translit", '');
check('empty input is an error', $status !== 0);

[$status] = run("php $bin nonsense");
check('unknown command is an error', $status !== 0);

[$status, $out] = run("php $bin --help");
check('--help exits zero and prints usage', $status === 0 && str_contains($out, 'USAGE'));

echo "cli\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

exit($fail === 0 ? 0 : 1);
