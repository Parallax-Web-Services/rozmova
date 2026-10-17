<?php
declare(strict_types=1);
require __DIR__ . '/../tests/bootstrap.php';

use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\RussianTransliterator;
use Parallax\Rozmova\UkrainianTransliterator;

/* Emits the PHP engine's answer for every corpus item, for demo/verify.js. */

$corpus   = json_decode((string) file_get_contents(__DIR__ . '/corpus.json'), true, 512, JSON_THROW_ON_ERROR);
$corpusRu = json_decode((string) file_get_contents(__DIR__ . '/corpus.ru.json'), true, 512, JSON_THROW_ON_ERROR);
$prefs    = PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.uk.json');
$prefsRu  = PreferenceList::fromJsonFile(__DIR__ . '/../data/preferences.ru.json');

$out = ['schemes' => [], 'preferred' => [], 'ruSchemes' => [], 'ruPreferred' => []];

foreach (UkrainianTransliterator::schemes() as $scheme) {
    $t = new UkrainianTransliterator($scheme);
    foreach ($corpus as $item) {
        $out['schemes'][$scheme][$item] = $t->transliterate($item);
    }
}

$r = Romanizer::ukrainian(UkrainianTransliterator::NATIONAL, $prefs);
foreach ($corpus as $item) {
    $out['preferred'][$item] = $r->romanize($item);
}

foreach (RussianTransliterator::schemes() as $scheme) {
    $t = new RussianTransliterator($scheme);
    foreach ($corpusRu as $item) {
        $out['ruSchemes'][$scheme][$item] = $t->transliterate($item);
    }
}

$rRu = Romanizer::russian(RussianTransliterator::BGN, $prefsRu);
foreach ($corpusRu as $item) {
    $out['ruPreferred'][$item] = $rRu->romanize($item);
}

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
