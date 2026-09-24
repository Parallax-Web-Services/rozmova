<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

/**
 * Ukrainian Cyrillic -> Latin.
 *
 *  - NATIONAL  Ukrainian national system, Cabinet of Ministers Resolution
 *              No. 55 of 27 January 2010. Passports and road signs, so it is
 *              what a learner meets in the wild. Lossy by design: soft signs
 *              and apostrophes are dropped.
 *  - BGN       BGN/PCGN, common in English-language press.
 *  - LEARNER   Pedagogical. Preserves palatalization and the apostrophe, and
 *              drops positional rules so each letter has one stable value.
 *              A proposal, not a standard.
 */
final class UkrainianTransliterator extends CyrillicTransliterator
{
    public const NATIONAL = 'national';
    public const BGN      = 'bgn';
    public const LEARNER  = 'learner';

    private const TABLES = [
        self::NATIONAL => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'h',  'ґ' => 'g',
            'д' => 'd',  'е' => 'e',
            'є' => ['initial' => 'ye', 'default' => 'ie'],
            'ж' => 'zh', 'з' => 'z',  'и' => 'y',  'і' => 'i',
            'ї' => ['initial' => 'yi', 'default' => 'i'],
            'й' => ['initial' => 'y',  'default' => 'i'],
            'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',
            'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',
            'ф' => 'f',  'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh',
            'щ' => 'shch',
            'ю' => ['initial' => 'yu', 'default' => 'iu'],
            'я' => ['initial' => 'ya', 'default' => 'ia'],
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
     * "зг" is the one genuinely load-bearing digraph in the national scheme.
     * Without it "зг" and "ж" both give "zh", and Згорани collides with Жорани.
     */
    private const DIGRAPHS = [
        self::NATIONAL => ['зг' => 'zgh'],
        self::BGN      => ['зг' => 'z·h'],
        self::LEARNER  => ['зг' => 'z-h'],
    ];

    private const APOSTROPHE_OUT = [
        self::NATIONAL => '',
        self::BGN      => "\u{201D}",
        self::LEARNER  => "\u{02BA}",
    ];

    public function __construct(string $scheme = self::NATIONAL)
    {
        parent::__construct($scheme);
    }

    public static function schemes(): array
    {
        return [self::NATIONAL, self::BGN, self::LEARNER];
    }

    protected function table(): array
    {
        return self::TABLES[$this->scheme];
    }

    protected function digraphs(): array
    {
        return self::DIGRAPHS[$this->scheme];
    }

    protected function apostropheOut(): string
    {
        return self::APOSTROPHE_OUT[$this->scheme];
    }
}
