<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\RussianTransliterator;

$schemeCases = [
    RussianTransliterator::BGN => [
        // Yotting: е and ё become ye/yë word-initially and after a vowel, й, ъ or ь.
        'Достоевский' => 'Dostoyevskiy',
        'Чайковский'  => 'Chaykovskiy',
        'Сергей'      => 'Sergey',
        'Юлия'        => 'Yuliya',
        'Ельцин'      => "Yel\u{2019}tsin",
        'Подъезд'     => "Pod\u{201D}yezd",
        'объявление'  => "ob\u{201D}yavleniye",
        // ё not in yotting position keeps the diaeresis.
        'Хрущёв'      => "Khrushch\u{EB}v",
        'Горбачёв'    => "Gorbach\u{EB}v",
        'Москва'      => 'Moskva',
        'Чехов'       => 'Chekhov',
        'МОСКВА'      => 'MOSKVA',
    ],
    RussianTransliterator::PASSPORT => [
        'Дмитрий'  => 'Dmitrii',
        'Юлия'     => 'Iuliia',
        'Пётр'     => 'Petr',      // ё collapses to e
        'Ельцин'   => 'Eltsin',    // soft sign dropped
        'Подъезд'  => 'Podieezd',  // ъ becomes ie
        'Сергей'   => 'Sergei',
        'Россия'   => 'Rossiia',
    ],
    RussianTransliterator::SCIENTIFIC => [
        'Хрущёв'  => "Hru\u{15D}\u{EB}v",
        'Чехов'   => "\u{10C}ehov",
        'Юлия'    => "\u{DB}li\u{E2}",
        'Щёлково' => "\u{15C}\u{EB}lkovo",
    ],
];

$pass = $fail = 0;
$failures = [];

foreach ($schemeCases as $scheme => $cases) {
    $t = new RussianTransliterator($scheme);
    foreach ($cases as $in => $want) {
        $got = $t->transliterate($in);
        if ($got === $want) {
            $pass++;
        } else {
            $fail++;
            $failures[] = sprintf('  [%s] %s expected %s got %s', $scheme, $in, $want, $got);
        }
    }
}

echo "russian schemes\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

// The editorial position: Russian source, Ukrainian place, Ukrainian-derived output.
$prefs     = PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.ru.json');
$romanizer = Romanizer::russian(RussianTransliterator::BGN, $prefs);

$editorial = [
    'Киев'    => 'Kyiv',        // not Kiyev, not Kiev
    'Киева'   => 'Kyiv',
    'Харьков' => 'Kharkiv',
    'Одесса'  => 'Odesa',
    'Львов'   => 'Lviv',
    'Чернобыль' => 'Chornobyl',
    'Москва'  => 'Moscow',      // Russian city keeps its English name
    'Белгород' => 'Belgorod',   // Russian city, Russian-derived form
    'Вагнер'  => 'Wagner',
    'Путин наступает на Киев.' => 'Putin nastupayet na Kyiv.',

    // Oblast capitals and frontline towns as Russian sources name them.
    'Северодонецк' => 'Sievierodonetsk',
    'Краматорске'  => 'Kramatorsk',      // declined
    'Бучи'         => 'Bucha',           // declined
    'Белая Церковь' => 'Bila Tserkva',   // multi-word
    'Кривой Рог'   => 'Kryvyi Rih',      // multi-word
    'Черновцы'     => 'Chernivtsi',
    'Ужгород'      => 'Uzhhorod',
    'Изюм'         => 'Izium',
];

$p2 = $f2 = 0;
$fail2 = [];

foreach ($editorial as $in => $want) {
    $got = $romanizer->romanize($in);
    if ($got === $want) {
        $p2++;
    } else {
        $f2++;
        $fail2[] = sprintf('  %-28s expected %-16s got %s', $in, $want, $got);
    }
}

echo "\nrussian editorial layer\n", str_repeat('-', 70), "\n";
printf("  %d passed, %d failed  (%d surface forms indexed)\n", $p2, $f2, $prefs->count());
if ($fail2) {
    echo "\n", implode("\n", $fail2), "\n";
}

exit(($fail + $f2) === 0 ? 0 : 1);
