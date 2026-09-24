<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Parallax\Rozmova\UkrainianTransliterator;

/*
 * Every case below is taken from the official example table in Cabinet of
 * Ministers Resolution No. 55 of 27 January 2010. If these pass, the national
 * scheme matches the standard the Ukrainian state itself applies to passports.
 */
$official = [
    'Алушта' => 'Alushta',        'Андрій' => 'Andrii',
    'Борщагівка' => 'Borshchahivka', 'Борисенко' => 'Borysenko',
    'Вінниця' => 'Vinnytsia',     'Володимир' => 'Volodymyr',
    'Гадяч' => 'Hadiach',         'Богдан' => 'Bohdan',
    'Ґалаґан' => 'Galagan',       'Ґорґани' => 'Gorgany',
    'Донецьк' => 'Donetsk',       'Дмитро' => 'Dmytro',
    'Рівне' => 'Rivne',           'Олег' => 'Oleh',
    'Есмань' => 'Esman',          'Єнакієве' => 'Yenakiieve',
    'Гаєвич' => 'Haievych',       'Короп\'є' => 'Koropie',
    'Житомир' => 'Zhytomyr',      'Жанна' => 'Zhanna',
    'Жежелів' => 'Zhezheliv',     'Закарпаття' => 'Zakarpattia',
    'Казимирчук' => 'Kazymyrchuk','Знам\'янка' => 'Znamianka',
    'Феодосія' => 'Feodosiia',    'Медвин' => 'Medvyn',
    'Михайленко' => 'Mykhailenko','Іванків' => 'Ivankiv',
    'Іващенко' => 'Ivashchenko',  'Їжакевич' => 'Yizhakevych',
    'Кадиївка' => 'Kadyivka',     'Мар\'їне' => 'Marine',
    'Йосипівка' => 'Yosypivka',   'Стрий' => 'Stryi',
    'Олексій' => 'Oleksii',       'Київ' => 'Kyiv',
    'Коваленко' => 'Kovalenko',   'Лебедин' => 'Lebedyn',
    'Леонід' => 'Leonid',         'Миколаїв' => 'Mykolaiv',
    'Маринич' => 'Marynych',      'Ніжин' => 'Nizhyn',
    'Наталія' => 'Nataliia',      'Одеса' => 'Odesa',
    'Онищенко' => 'Onyshchenko',  'Полтава' => 'Poltava',
    'Петро' => 'Petro',           'Рені' => 'Reni',
    'Рогачик' => 'Rohachyk',      'Суми' => 'Sumy',
    'Соломія' => 'Solomiia',      'Тернопіль' => 'Ternopil',
    'Троць' => 'Trots',           'Ужгород' => 'Uzhhorod',
    'Уляна' => 'Uliana',          'Фастів' => 'Fastiv',
    'Філіпчук' => 'Filipchuk',    'Харків' => 'Kharkiv',
    'Христина' => 'Khrystyna',    'Біла Церква' => 'Bila Tserkva',
    'Стеценко' => 'Stetsenko',    'Чернівці' => 'Chernivtsi',
    'Шевченко' => 'Shevchenko',   'Шостка' => 'Shostka',
    'Кишеньки' => 'Kyshenky',     'Щербухи' => 'Shcherbukhy',
    'Гоща' => 'Hoshcha',          'Гаращенко' => 'Harashchenko',
    'Юрій' => 'Yurii',            'Корюківка' => 'Koriukivka',
    'Яготин' => 'Yahotyn',        'Ярошенко' => 'Yaroshenko',
    'Костянтин' => 'Kostiantyn',  'Згорани' => 'Zghorany',
    'Розгон' => 'Rozghon',
];

// Behaviour beyond the official table.
$extra = [
    'КИЇВ'                   => 'KYIV',        // all-caps word
    'Я'                      => 'Ya',          // single letter stays title case
    'Слава Україні!'         => 'Slava Ukraini!',
    'Героям слава!'          => 'Heroiam slava!',
    'Володимир Зеленський'   => 'Volodymyr Zelenskyi',
    'ЗСУ'                    => 'ZSU',
    'Доброго вечора, ми з України' => 'Dobroho vechora, my z Ukrainy',
];

$national = new UkrainianTransliterator(UkrainianTransliterator::NATIONAL);

$pass = $fail = 0;
$failures = [];

foreach ([$official, $extra] as $group) {
    foreach ($group as $in => $want) {
        $got = $national->transliterate($in);
        if ($got === $want) {
            $pass++;
        } else {
            $fail++;
            $failures[] = sprintf('  %-28s expected %-18s got %s', $in, $want, $got);
        }
    }
}

echo "national scheme (KMU 2010)\n";
echo str_repeat('-', 60), "\n";
printf("  %d passed, %d failed\n", $pass, $fail);
if ($failures) {
    echo "\n", implode("\n", $failures), "\n";
}

// Show the three schemes side by side on a real sentence.
echo "\nscheme comparison\n", str_repeat('-', 60), "\n";
$sample = 'Слава Україні! Знам\'янка, Львів, Згорани, Костянтин.';
echo "  cyrillic   $sample\n";
foreach (UkrainianTransliterator::schemes() as $scheme) {
    printf("  %-10s %s\n", $scheme, (new UkrainianTransliterator($scheme))->transliterate($sample));
}

exit($fail === 0 ? 0 : 1);
