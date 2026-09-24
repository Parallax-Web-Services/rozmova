<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

/**
 * Preference list first, transliteration scheme for everything else.
 *
 * Kept separate from UkrainianTransliterator so the scheme stays a pure
 * function of the standard. Editorial policy changes often; KMU 2010 does not.
 */
final class Romanizer
{
    /** @var list<array{cyrillic: string, latin: string, gloss: ?string}> */
    private array $applied = [];

    public function __construct(
        private readonly UkrainianTransliterator $transliterator,
        private readonly PreferenceList $preferences,
    ) {
    }

    public static function make(
        string $scheme = UkrainianTransliterator::NATIONAL,
        ?PreferenceList $preferences = null,
    ): self {
        return new self(
            new UkrainianTransliterator($scheme),
            $preferences ?? new PreferenceList(),
        );
    }

    /**
     * Overrides matched during the last romanize() call, in order.
     *
     * The archive shows its working: a reader seeing "Zelenskyy" can be told the
     * source said Зеленського and that an editorial rule produced the rest.
     *
     * @return list<array{cyrillic: string, latin: string, gloss: ?string}>
     */
    public function appliedOverrides(): array
    {
        return $this->applied;
    }

    public function romanize(string $text): string
    {
        $this->applied = [];

        $tokens = $this->tokenize($text);
        $out    = '';
        $i      = 0;
        $n      = count($tokens);

        while ($i < $n) {
            [$isWord, $chunk] = $tokens[$i];

            if (!$isWord) {
                $out .= $chunk;
                $i++;
                continue;
            }

            $match = $this->longestMatch($tokens, $i);

            if ($match !== null) {
                [$consumed, $entry, $source] = $match;

                $out .= $this->applyCase($entry['latin'], $source);

                $this->applied[] = [
                    'cyrillic' => $source,
                    'latin'    => $entry['latin'],
                    'gloss'    => $entry['gloss'],
                ];

                $i += $consumed;
                continue;
            }

            $out .= $this->transliterator->transliterate($chunk);
            $i++;
        }

        return $out;
    }

    /**
     * Longest preference match starting at $start, counted in words.
     *
     * Multi-word entries may only span whitespace, so "Слава Україні" matches
     * but "Слава, Україні" does not.
     *
     * @return array{int, array, string}|null  [tokens consumed, entry, source text]
     */
    private function longestMatch(array $tokens, int $start): ?array
    {
        $n         = count($tokens);
        $maxWords  = $this->preferences->maxWords();
        $best      = null;
        $words     = 0;
        $source    = '';
        $consumed  = 0;

        for ($i = $start; $i < $n && $words < $maxWords; $i++) {
            [$isWord, $chunk] = $tokens[$i];

            if (!$isWord) {
                if (trim($chunk) !== '') {
                    break; // punctuation ends a candidate phrase
                }
                $source   .= $chunk;
                $consumed  = $i - $start + 1;
                continue;
            }

            $source   .= $chunk;
            $words++;
            $consumed  = $i - $start + 1;

            $entry = $this->preferences->lookup($source);

            if ($entry !== null) {
                $best = [$consumed, $entry, $source];
            }
        }

        return $best;
    }

    /** Preferred forms are authored in their normal casing; only SHOUTING is propagated. */
    private function applyCase(string $latin, string $source): string
    {
        $letters = preg_replace('/[^\p{L}]/u', '', $source) ?? '';

        if (mb_strlen($letters) > 1 && mb_strtoupper($letters) === $letters) {
            return mb_strtoupper($latin);
        }

        return $latin;
    }

    /** @return list<array{bool, string}> */
    private function tokenize(string $text): array
    {
        $chunks = [];
        $buffer = '';
        $inWord = false;

        foreach (mb_str_split($text) as $char) {
            $isWordChar = preg_match('/[\p{L}]/u', $char) === 1
                || in_array($char, ["'", "\u{2019}", "\u{2018}", "\u{02BC}", "\u{00B4}", '`'], true);

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
}
