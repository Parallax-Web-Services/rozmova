<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\UkrainianTransliterator;

$prefs     = PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json');
$romanizer = Romanizer::ukrainian(UkrainianTransliterator::NATIONAL, $prefs);

$cases = [
    // The case that started this: standard says Zelenskyi, usage says Zelenskyy.
    'Зеленський'   => 'Zelenskyy',
    'Зеленського'  => 'Zelenskyy',
    'Зеленському'  => 'Zelenskyy',
    'Зеленським'   => 'Zelenskyy',
    'ЗЕЛЕНСЬКИЙ'   => 'ZELENSKYY',

    // Feminine surname is a different person, not a case of the masculine.
    'Зеленська'    => 'Zelenska',
    'Зеленської'   => 'Zelenska',
    'Зеленською'   => 'Zelenska',

    // Declined place names collapse to one invariant English form.
    'Київ'         => 'Kyiv',
    'Києва'        => 'Kyiv',
    'Києві'        => 'Kyiv',
    'Харкові'      => 'Kharkiv',
    'Львова'       => 'Lviv',

    // Translated rather than romanized.
    'Крим'         => 'Crimea',
    'Криму'        => 'Crimea',

    // Multi-word entries, longest match first.
    'Слава Україні' => 'Slava Ukraini',
    'Верховна Рада' => 'Verkhovna Rada',

    // Acronyms.
    'ЗСУ'          => 'ZSU',
    'СБУ'          => 'SBU',

    // Not in the list: falls through to KMU 2010 untouched.
    'Ярошенко'     => 'Yaroshenko',
    'Розмова'      => 'Rozmova',

    // Mixed sentence: overrides and fallthrough in one pass, punctuation intact.
    'Зеленський прибув до Києва.'      => 'Zelenskyy prybuv do Kyiv.',
    'Слава Україні! Героям слава!'     => 'Slava Ukraini! Heroiam slava!',
    'Бої тривають за Бахмут і Авдіївку.' => 'Boi tryvaiut za Bakhmut i Avdiivka.',
];

$pass = $fail = 0;
$failures = [];

foreach ($cases as $in => $want) {
    $got = $romanizer->romanize($in);
    if ($got === $want) {
        $pass++;
    } else {
        $fail++;
        $failures[] = sprintf('  %-34s expected %-24s got %s', $in, $want, $got);
    }
}

echo "preference layer\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed  (%d surface forms indexed)\n", $pass, $fail, $prefs->count());
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

// The archive has to be able to show its working.
echo "\naudit trail\n", str_repeat('-', 70), "\n";
$sentence = 'Зеленський подякував ЗСУ за оборону Бахмута.';
echo "  source   $sentence\n";
echo "  output   ", $romanizer->romanize($sentence), "\n";
foreach ($romanizer->appliedOverrides() as $o) {
    printf("  applied  %-14s -> %-12s %s\n", $o['cyrillic'], $o['latin'], $o['gloss'] ? "({$o['gloss']})" : '');
}

exit($fail === 0 ? 0 : 1);
