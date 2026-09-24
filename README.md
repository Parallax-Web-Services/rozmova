# Rozmova

Ukrainian and Russian romanization with an editorial preference layer.

*Розмова* — conversation.

Rozmova turns Cyrillic into Latin script and lets an editorial team
override the result where a standard and real usage disagree. It is a pure text
transformation: no model, no network, no state. Whatever happens to the speech
or translation stack around it, this layer is unaffected.

## Why a preference layer

The Ukrainian national romanization system (Cabinet of Ministers Resolution
No. 55, 27 January 2010) is what appears on passports and road signs. Applied to
`Зеленський` it yields **Zelenskyi**. The Office of the President writes
**Zelenskyy**, and that is the form in circulation.

Both are defensible. A publication has to pick one and apply it consistently —
including in the middle of a declined sentence, where the source reads
`Зеленського` or `Зеленським`. Ukrainian declines; English does not. So every
inflected form has to land on one invariant English output.

That is what the preference list does. Everything it does not claim falls
through to the scheme untouched.

## Usage

```php
use Parallax\Rozmova\PreferenceList;
use Parallax\Rozmova\Romanizer;
use Parallax\Rozmova\UkrainianTransliterator;

$romanizer = Romanizer::make(
    UkrainianTransliterator::NATIONAL,
    PreferenceList::fromJsonFile(__DIR__ . '/data/preferences.uk.json'),
);

$romanizer->romanize('Зеленський подякував ЗСУ за оборону Бахмута.');
// Zelenskyy podiakuvav ZSU za oboronu Bakhmut.
```

The scheme alone, without editorial overrides:

```php
(new UkrainianTransliterator())->transliterate('Київ');  // Kyiv
```

## Schemes

| Scheme | Notes |
|---|---|
| `NATIONAL` | KMU 2010. Passports and road signs, so it is what a learner sees in the wild. Lossy by design — drops soft signs and apostrophes. |
| `BGN` | BGN/PCGN. Common in English-language press. |
| `LEARNER` | Pedagogical. Preserves palatalization and the apostrophe, and drops positional rules so each letter has one stable value. A proposal, not a standard. |

```
cyrillic   Слава Україні! Знам'янка, Львів, Згорани, Костянтин.
national   Slava Ukraini! Znamianka, Lviv, Zghorany, Kostiantyn.
bgn        Slava Ukrayini! Znam”yanka, L’viv, Z·horany, Kostyantyn.
learner    Slava Ukrayini! Znamʺyanka, Lʹviv, Z-horany, Kostyantyn.
```

## The preference list

`data/preferences.uk.json`. Each entry records what it claims, what it
publishes, and why:

```json
{
  "forms": ["Зеленський"],
  "declension": "adj",
  "latin": "Zelenskyy",
  "note": "KMU 2010 yields Zelenskyi. The Office of the President writes Zelenskyy in English and that is the form in circulation; the archive follows usage over the standard here."
}
```

`declension: "adj"` expands a `-ський`/`-ний` stem across its masculine cases,
so the list stays short. `"adj-f"` does the same for the feminine form. They are
deliberately separate — `Зеленський` and `Зеленська` are two different people,
and collapsing them would publish Olena Zelenska as "Zelenskyy".

Entries may also carry a `gloss`, for terms that usage translates rather than
romanizes (`Крим` → Crimea, not Krym).

Every override applied during a pass is retrievable:

```php
$romanizer->appliedOverrides();
// [['cyrillic' => 'Зеленський', 'latin' => 'Zelenskyy', 'gloss' => null], ...]
```

An archive should be able to show its working. A reader who sees "Zelenskyy"
can be told the source said `Зеленського` and which editorial rule produced the
rest.

### Place names are an editorial position

Spellings in this list are Ukrainian-derived: Kyiv not Kiev, Kharkiv not
Kharkov, Odesa not Odessa, Chornobyl not Chernobyl. That is a deliberate
choice recorded in the `note` field, not a transliteration artefact.

## Russian

Three schemes, and the same preference layer.

| Scheme | Notes |
|---|---|
| `BGN` | BGN/PCGN 1947. Usual choice in English-language press. Renders `е`/`ё` as `ye`/`yë` word-initially and after a vowel, `й`, `ъ` or `ь` — which is why `Достоевский` is `Dostoyevskiy`. |
| `PASSPORT` | ICAO Doc 9303 / GOST R 52535.1-2006, in Russian passports since 2013. Collapses `ё` to `e` and drops the soft sign, so it loses information the others keep. |
| `SCIENTIFIC` | ISO 9:1995. Strictly one-to-one with diacritics, and so the only scheme here that round-trips back to Cyrillic. |

```
cyrillic      bgn            passport     scientific
Достоевский   Dostoyevskiy   Dostoevskii  Dostoevskij
Хрущёв        Khrushchëv     Khrushchev   Hruŝëv
Подъезд       Pod”yezd       Podieezd     Podʺezd
Юлия          Yuliya         Iuliia       Ûliâ
```

Note against the Ukrainian tables: `г` is `g` here, not `h`, and there is no
`зг` rule — with `г` = `g` nothing collides with `ж`.

### Whose place is it

`data/preferences.ru.json` carries one editorial position that matters more
than the schemes do. When a Russian source names a Ukrainian place, the
romanized Russian name is not what gets published:

```php
$romanizer = Romanizer::russian(
    RussianTransliterator::BGN,
    PreferenceList::fromJsonFile(__DIR__ . '/data/preferences.ru.json'),
);

$romanizer->romanize('Путин наступает на Киев.');
// Putin nastupayet na Kyiv.
```

`Киев` becomes **Kyiv** — not `Kiyev` (romanized Russian) and not `Kiev`
(Russian-derived English). Russian places keep their Russian-derived forms:
`Белгород` stays `Belgorod`. The rule is about whose place it is, not which
language the sentence was written in.

The source text is preserved verbatim either way. Only the English rendering
takes a position, and the position is recorded in the entry's `note`.

## Tests

No dependencies. The scheme suite asserts every example in the official KMU 2010
table, so a pass means the output matches what the Ukrainian state applies to
its own passports.

```
php tests/scheme.php        # 82 cases -- every example in the KMU 2010 table
php tests/preferences.php   # 24 cases -- Ukrainian editorial layer
php tests/russian.php       # 33 cases -- Russian schemes and editorial layer
```

Or `composer test`.

## License

MIT. See [LICENSE](LICENSE).
