<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

use InvalidArgumentException;

/**
 * Shared machinery for Cyrillic romanization.
 *
 * Subclasses supply tables; this class handles word splitting, letter context,
 * digraphs and case. A table value is either a plain string, or an array:
 *
 *   initial   used at the start of a word
 *   after     ['set' => [letters], 'value' => string] -- used when the preceding
 *             letter is in the set. Russian needs this: BGN renders е as "ye"
 *             after a vowel or ъ/ь, not merely word-initially.
 *   default   everything else
 */
abstract class CyrillicTransliterator implements Transliterator
{
    /** Characters accepted as an apostrophe. */
    protected const APOSTROPHES = ["'", "\u{2019}", "\u{2018}", "\u{02BC}", "\u{00B4}", '`'];

    public function __construct(protected readonly string $scheme)
    {
        if (!in_array($scheme, static::schemes(), true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown scheme "%s". Expected one of: %s.',
                $scheme,
                implode(', ', static::schemes())
            ));
        }
    }

    /** letter => string|array, for the active scheme. */
    abstract protected function table(): array;

    /** Two-letter sequences resolved before single letters. */
    abstract protected function digraphs(): array;

    /** What a hard apostrophe becomes. */
    abstract protected function apostropheOut(): string;

    public function transliterate(string $text): string
    {
        $out = '';

        foreach ($this->splitWords($text) as [$isWord, $chunk]) {
            $out .= $isWord ? $this->word($chunk) : $chunk;
        }

        return $out;
    }

    protected function word(string $word): string
    {
        $chars    = mb_str_split($word);
        $allCaps  = $this->isAllCaps($chars);
        $table    = $this->table();
        $digraphs = $this->digraphs();
        $out      = '';
        $atStart  = true;
        $prev     = null;

        for ($i = 0, $n = count($chars); $i < $n; $i++) {
            $char = $chars[$i];

            if ($this->isApostrophe($char)) {
                $out    .= $this->apostropheOut();
                $atStart = false;
                $prev    = "'";
                continue;
            }

            $lower = mb_strtolower($char);

            $matched = null;

            if ($i + 1 < $n) {
                $pair = $lower . mb_strtolower($chars[$i + 1]);
                if (isset($digraphs[$pair])) {
                    $matched = $digraphs[$pair];
                    $i++;
                    $prev = mb_strtolower($chars[$i]);
                }
            }

            if ($matched === null) {
                if (!array_key_exists($lower, $table)) {
                    $out    .= $char;
                    $atStart = false;
                    $prev    = $lower;
                    continue;
                }

                $matched = $this->resolve($table[$lower], $atStart, $prev);
                $prev    = $lower;
            }

            $out    .= $this->applyCase($matched, $char, $allCaps);
            $atStart = false;
        }

        return $out;
    }

    protected function resolve(string|array $value, bool $atStart, ?string $prev): string
    {
        if (is_string($value)) {
            return $value;
        }

        if ($atStart && isset($value['initial'])) {
            return $value['initial'];
        }

        if ($prev !== null && isset($value['after']) && in_array($prev, $value['after']['set'], true)) {
            return $value['after']['value'];
        }

        return $value['default'];
    }

    protected function applyCase(string $replacement, string $source, bool $allCaps): string
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

    /** All-caps needs two letters, so a lone "Я" stays "Ya" rather than "YA". */
    protected function isAllCaps(array $chars): bool
    {
        $letters = 0;

        foreach ($chars as $char) {
            if (!$this->isLetter($char)) {
                continue;
            }
            if (mb_strtoupper($char) !== $char) {
                return false;
            }
            $letters++;
        }

        return $letters > 1;
    }

    /**
     * Split into alternating word / non-word chunks. The apostrophe binds, so
     * Знам'янка is one word and я is therefore not word-initial.
     *
     * @return list<array{bool, string}>
     */
    protected function splitWords(string $text): array
    {
        $chunks = [];
        $buffer = '';
        $inWord = false;

        foreach (mb_str_split($text) as $char) {
            $isWordChar = $this->isLetter($char) || $this->isApostrophe($char);

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

    /** Any Cyrillic letter, so a mixed-script document does not fragment mid-word. */
    protected function isLetter(string $char): bool
    {
        return preg_match('/\p{Cyrillic}/u', $char) === 1;
    }

    protected function isApostrophe(string $char): bool
    {
        return in_array($char, static::APOSTROPHES, true);
    }
}
