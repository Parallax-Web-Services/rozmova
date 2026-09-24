<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

use InvalidArgumentException;

/**
 * Ukrainian Cyrillic -> Latin transliteration.
 *
 * Pure text transformation: no model, no network, no state. Whatever happens to
 * the speech stack underneath, this layer is unaffected.
 *
 * Schemes:
 *
 *  - NATIONAL  Ukrainian national system, Cabinet of Ministers Resolution
 *              No. 55 of 27 January 2010. This is what appears on passports and
 *              road signs, so it is what a learner sees in the wild. It is lossy
 *              by design: soft signs and apostrophes are dropped.
 *
 *  - BGN       BGN/PCGN romanization, common in English-language press.
 *
 *  - LEARNER   Pedagogical variant. Unlike the two standards it preserves
 *              palatalization and the apostrophe, and drops positional rules so
 *              each Cyrillic letter has one stable Latin value. Proposal, not a
 *              standard: tune to taste.
 */
final class UkrainianTransliterator
{
    public const NATIONAL = 'national';
    public const BGN      = 'bgn';
    public const LEARNER  = 'learner';

    /** Characters accepted as the Ukrainian apostrophe. */
    private const APOSTROPHES = ["'", "\u{2019}", "\u{2018}", "\u{02BC}", "\u{00B4}", '`'];

    /**
     * A value is either a plain string, or [word-initial, elsewhere].
     */
    private const TABLES = [
        self::NATIONAL => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'h',  'ґ' => 'g',
            'д' => 'd',  'е' => 'e',  'є' => ['ye', 'ie'],      'ж' => 'zh',
            'з' => 'z',  'и' => 'y',  'і' => 'i',  'ї' => ['yi', 'i'],
            'й' => ['y', 'i'],        'к' => 'k',  'л' => 'l',  'м' => 'm',
            'н' => 'n',  'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',
            'т' => 't',  'у' => 'u',  'ф' => 'f',  'х' => 'kh', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ю' => ['yu', 'iu'],      'я' => ['ya', 'ia'],
            'ь' => '',
        ],
        self::BGN => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'h',  'ґ' => 'g',
            'д' => 'd',  'е' => 'e',  'є' => 'ye', 'ж' => 'zh', 'з' => 'z',
            'и' => 'y',  'і' => 'i',  'ї' => 'yi', 'й' => 'y',  'к' => 'k',
            'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',  'п' => 'p',
            'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ю' => 'yu', 'я' => 'ya', 'ь' => "\u{2019}",
        ],
        self::LEARNER => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'h',  'ґ' => 'g',
            'д' => 'd',  'е' => 'e',  'є' => 'ye', 'ж' => 'zh', 'з' => 'z',
            'и' => 'y',  'і' => 'i',  'ї' => 'yi', 'й' => 'j',  'к' => 'k',
            'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',  'п' => 'p',
            'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ю' => 'yu', 'я' => 'ya', 'ь' => "\u{02B9}",
        ],
    ];

    /**
     * Digraphs applied before single letters.
     *
     * "зг" is the one genuinely load-bearing rule in the national scheme: without
     * it "зг" and "ж" both romanize to "zh" and Згорани/Жорани collide.
     */
    private const DIGRAPHS = [
        self::NATIONAL => ['зг' => 'zgh'],
        self::BGN      => ['зг' => 'z·h'],
        self::LEARNER  => ['зг' => 'z-h'],
    ];

    /** What the apostrophe becomes. */
    private const APOSTROPHE_OUT = [
        self::NATIONAL => '',
        self::BGN      => "\u{201D}",
        self::LEARNER  => "\u{02BA}",
    ];

    public function __construct(private readonly string $scheme = self::NATIONAL)
    {
        if (!isset(self::TABLES[$scheme])) {
            throw new InvalidArgumentException(sprintf(
                'Unknown scheme "%s". Expected one of: %s.',
                $scheme,
                implode(', ', array_keys(self::TABLES))
            ));
        }
    }

    public static function schemes(): array
    {
        return array_keys(self::TABLES);
    }

    public function transliterate(string $text): string
    {
        $out = '';

        foreach ($this->splitWords($text) as [$isWord, $chunk]) {
            $out .= $isWord ? $this->word($chunk) : $chunk;
        }

        return $out;
    }

    /**
     * Split into alternating word / non-word chunks.
     *
     * A word is a run of Ukrainian letters, optionally containing apostrophes --
     * the apostrophe binds (Знам'янка is one word, so я is not word-initial).
     */
    private function splitWords(string $text): array
    {
        $chunks  = [];
        $buffer  = '';
        $inWord  = false;

        foreach (mb_str_split($text) as $char) {
            $isWordChar = $this->isUkrainianLetter($char) || $this->isApostrophe($char);

            if ($isWordChar !== $inWord && $buffer !== '') {
                $chunks[] = [$inWord, $buffer];
                $buffer   = '';
            }

            $inWord  = $isWordChar;
            $buffer .= $char;
        }

        if ($buffer !== '') {
            $chunks[] = [$inWord, $buffer];
        }

        return $chunks;
    }

    private function word(string $word): string
    {
        $chars   = mb_str_split($word);
        $allCaps = $this->isAllCaps($chars);
        $table   = self::TABLES[$this->scheme];
        $out     = '';
        $atStart = true;

        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $char = $chars[$i];

            if ($this->isApostrophe($char)) {
                $out    .= self::APOSTROPHE_OUT[$this->scheme];
                $atStart = false;
                continue;
            }

            $lower = mb_strtolower($char);

            // Digraphs first.
            $matched = null;
            if ($i + 1 < $n) {
                $pair = $lower . mb_strtolower($chars[$i + 1]);
                if (isset(self::DIGRAPHS[$this->scheme][$pair])) {
                    $matched = self::DIGRAPHS[$this->scheme][$pair];
                    $i++;
                }
            }

            if ($matched === null) {
                if (!isset($table[$lower])) {
                    $out    .= $char;
                    $atStart = false;
                    continue;
                }

                $value   = $table[$lower];
                $matched = is_array($value) ? ($atStart ? $value[0] : $value[1]) : $value;
            }

            $out    .= $this->applyCase($matched, $char, $allCaps);
            $atStart = false;
        }

        return $out;
    }

    private function applyCase(string $replacement, string $source, bool $allCaps): string
    {
        if ($replacement === '') {
            return '';
        }

        if ($allCaps) {
            return mb_strtoupper($replacement);
        }

        if (mb_strtolower($source) === $source) {
            return $replacement;
        }

        return mb_strtoupper(mb_substr($replacement, 0, 1)) . mb_substr($replacement, 1);
    }

    /** All-caps only when there are at least two letters, so "Я" stays "Ya". */
    private function isAllCaps(array $chars): bool
    {
        $letters = 0;

        foreach ($chars as $char) {
            if (!$this->isUkrainianLetter($char)) {
                continue;
            }
            if (mb_strtoupper($char) !== $char) {
                return false;
            }
            $letters++;
        }

        return $letters > 1;
    }

    private function isUkrainianLetter(string $char): bool
    {
        return isset(self::TABLES[self::NATIONAL][mb_strtolower($char)])
            || in_array(mb_strtolower($char), ['ъ', 'ы', 'э', 'ё'], true);
    }

    private function isApostrophe(string $char): bool
    {
        return in_array($char, self::APOSTROPHES, true);
    }
}
