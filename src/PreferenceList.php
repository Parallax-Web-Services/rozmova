<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

use InvalidArgumentException;
use RuntimeException;

/**
 * Editorial overrides applied before transliteration.
 *
 * Romanization standards and editorial practice disagree, and in a Ukrainian
 * archive the disagreements are rarely neutral. KMU 2010 yields "Zelenskyi";
 * his own office writes "Zelenskyy". Both are defensible; the archive has to
 * pick one and apply it everywhere, including in the middle of a declined
 * sentence.
 *
 * Each entry carries:
 *
 *   forms       Cyrillic surface forms this entry claims. Ukrainian declines
 *               and English does not, so every inflected form maps to one
 *               invariant output.
 *   declension  Optional. "adj" expands a -ський/-ний stem across its
 *               masculine cases; "adj-f" does the same for the feminine form.
 *               They are deliberately separate: Зеленський and Зеленська are
 *               two different people, and collapsing them would publish Olena
 *               Zelenska as "Zelenskyy".
 *   latin       What the archive publishes.
 *   gloss       Optional English meaning, for terms that are translated rather
 *               than romanized in normal usage.
 *   note        Why this entry exists. The list is an editorial record, so the
 *               reasoning belongs next to the decision.
 */
final class PreferenceList
{
    /** @var array<string, array{latin: string, gloss: ?string, note: ?string, key: string}> */
    private array $index = [];

    private int $maxWords = 1;

    public function __construct(array $entries = [])
    {
        foreach ($entries as $entry) {
            $this->add($entry);
        }
    }

    public static function fromJsonFile(string $path): self
    {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('Preference list not readable: %s', $path));
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded) || !isset($decoded['entries']) || !is_array($decoded['entries'])) {
            throw new RuntimeException(sprintf('Preference list malformed (expected {"entries": [...]}): %s', $path));
        }

        return new self($decoded['entries']);
    }

    public function add(array $entry): void
    {
        if (!isset($entry['latin']) || !is_string($entry['latin']) || $entry['latin'] === '') {
            throw new InvalidArgumentException('Preference entry needs a non-empty "latin".');
        }

        $forms = $entry['forms'] ?? [];

        if (!is_array($forms) || $forms === []) {
            throw new InvalidArgumentException(sprintf('Preference entry "%s" needs at least one form.', $entry['latin']));
        }

        $declension = $entry['declension'] ?? null;

        if ($declension === 'adj' || $declension === 'adj-f') {
            $forms = self::expandAdjectival($forms, $declension === 'adj-f');
        }

        foreach ($forms as $form) {
            $key = self::normalise($form);

            if ($key === '') {
                continue;
            }

            $existing = $this->index[$key] ?? null;

            // Two entries claiming the same surface form is an editorial
            // contradiction, not a precedence question. Silently letting the
            // later one win would flip the archive's spelling of a name with
            // nothing to show for it -- and `adj` expansion generates forms
            // automatically, so the collision may be one nobody typed.
            if ($existing !== null && $existing['latin'] !== $entry['latin']) {
                throw new InvalidArgumentException(sprintf(
                    'Preference conflict: "%s" is claimed by both "%s" and "%s". '
                    . 'Remove one, or narrow the forms they expand to.',
                    $form,
                    $existing['latin'],
                    $entry['latin'],
                ));
            }

            $this->index[$key] = [
                'latin' => $entry['latin'],
                'gloss' => $entry['gloss'] ?? null,
                'note'  => $entry['note'] ?? null,
                'key'   => $key,
            ];

            $this->maxWords = max($this->maxWords, count(explode(' ', $key)));
        }
    }

    /**
     * Expand adjectival surnames (-ський, -цький, -ний) across their cases.
     * Surnames of this shape are very common, and hand-listing five cases each
     * is how a list like this rots.
     *
     * Masculine and feminine are never expanded together: the feminine surname
     * belongs to a different person and usually takes a different English form
     * (Зеленський -> Zelenskyy, but Зеленська -> Zelenska).
     */
    private static function expandAdjectival(array $stems, bool $feminine = false): array
    {
        $endings = $feminine
            ? ['а', 'ої', 'ій', 'у', 'ою']
            : ['ий', 'ого', 'ому', 'им', 'ім'];

        $out = [];

        foreach ($stems as $stem) {
            $out[] = $stem;

            $base = preg_replace('/(ий|ій|а)$/u', '', $stem);

            if ($base === null || $base === $stem) {
                continue;
            }

            foreach ($endings as $ending) {
                $out[] = $base . $ending;
            }
        }

        return array_values(array_unique($out));
    }

    public function maxWords(): int
    {
        return $this->maxWords;
    }

    public function count(): int
    {
        return count($this->index);
    }

    /** @return array{latin: string, gloss: ?string, note: ?string, key: string}|null */
    public function lookup(string $phrase): ?array
    {
        return $this->index[self::normalise($phrase)] ?? null;
    }

    /** Lowercase, unify apostrophes, collapse whitespace. */
    public static function normalise(string $text): string
    {
        $text = str_replace(["\u{2019}", "\u{2018}", "\u{02BC}", "\u{00B4}", '`'], "'", $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }
}
