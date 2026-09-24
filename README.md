# Rozmova

Ukrainian romanization with an editorial preference layer.

*Розмова* — conversation.

Rozmova turns Ukrainian Cyrillic into Latin script and lets an editorial team
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

## Tests

No dependencies. The scheme suite asserts every example in the official KMU 2010
table, so a pass means the output matches what the Ukrainian state applies to
its own passports.

```
php tests/scheme.php        # 82 cases
php tests/preferences.php   # 24 cases
```

## Status

Ukrainian is complete. Russian is in scope and not yet implemented.

## License

MIT. See [LICENSE](LICENSE).
