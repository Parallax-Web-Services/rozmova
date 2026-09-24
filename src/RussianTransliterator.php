<?php

declare(strict_types=1);

namespace Parallax\Rozmova;

/**
 * Russian Cyrillic -> Latin.
 *
 *  - BGN         BGN/PCGN 1947. The usual choice in English-language press.
 *                Renders е and ё as "ye"/"yë" word-initially and after a vowel,
 *                й, ъ or ь -- which is why Достоевский is Dostoyevskiy.
 *  - PASSPORT    ICAO Doc 9303 / GOST R 52535.1-2006, used in Russian
 *                international passports since 2013. Drops ё to e and the soft
 *                sign entirely, so it loses information the others keep.
 *  - SCIENTIFIC  ISO 9:1995. Strictly one-to-one with diacritics, and therefore
 *                the only scheme here that round-trips back to Cyrillic.
 *
 * Note for anyone comparing with the Ukrainian tables: г is g here, not h, and
 * there is no зg digraph rule, because with г = g nothing collides with ж.
 */
final class RussianTransliterator extends CyrillicTransliterator
{
    public const BGN        = 'bgn';
    public const PASSPORT   = 'passport';
    public const SCIENTIFIC = 'scientific';

    /** Letters after which BGN yots а following е or ё. */
    private const YOTTING = ['а', 'е', 'ё', 'и', 'о', 'у', 'ы', 'э', 'ю', 'я', 'й', 'ъ', 'ь'];

    private const TABLES = [
        self::BGN => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
            'е' => ['initial' => 'ye', 'after' => ['set' => self::YOTTING, 'value' => 'ye'], 'default' => 'e'],
            'ё' => ['initial' => 'yë', 'after' => ['set' => self::YOTTING, 'value' => 'yë'], 'default' => 'ë'],
            'ж' => 'zh', 'з' => 'z',  'и' => 'i',  'й' => 'y',  'к' => 'k',
            'л' => 'l',  'м' => 'm',  'н' => 'n',  'о' => 'o',  'п' => 'p',
            'р' => 'r',  'с' => 's',  'т' => 't',  'у' => 'u',  'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ъ' => "\u{201D}", 'ы' => 'y', 'ь' => "\u{2019}", 'э' => 'e',
            'ю' => 'yu', 'я' => 'ya',
        ],
        self::PASSPORT => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
            'е' => 'e',  'ё' => 'e',  'ж' => 'zh', 'з' => 'z',  'и' => 'i',
            'й' => 'i',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',
            'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
            'у' => 'u',  'ф' => 'f',  'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'shch', 'ъ' => 'ie', 'ы' => 'y', 'ь' => '',
            'э' => 'e',  'ю' => 'iu', 'я' => 'ia',
        ],
        self::SCIENTIFIC => [
            'а' => 'a',  'б' => 'b',  'в' => 'v',  'г' => 'g',  'д' => 'd',
            'е' => 'e',  'ё' => 'ë',  'ж' => 'ž',  'з' => 'z',  'и' => 'i',
            'й' => 'j',  'к' => 'k',  'л' => 'l',  'м' => 'm',  'н' => 'n',
            'о' => 'o',  'п' => 'p',  'р' => 'r',  'с' => 's',  'т' => 't',
            'у' => 'u',  'ф' => 'f',  'х' => 'h',  'ц' => 'c',  'ч' => 'č',
            'ш' => 'š',  'щ' => 'ŝ',  'ъ' => "\u{02BA}", 'ы' => 'y',
            'ь' => "\u{02B9}", 'э' => 'è', 'ю' => 'û', 'я' => 'â',
        ],
    ];

    /**
     * BGN uses a middle dot to break sequences that would otherwise read as a
     * single digraph. тс would render "ts" and collide with ц.
     */
    private const DIGRAPHS = [
        self::BGN        => ['тс' => 't·s'],
        self::PASSPORT   => [],
        self::SCIENTIFIC => [],
    ];

    public function __construct(string $scheme = self::BGN)
    {
        parent::__construct($scheme);
    }

    public static function schemes(): array
    {
        return [self::BGN, self::PASSPORT, self::SCIENTIFIC];
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
        return '';
    }
}
