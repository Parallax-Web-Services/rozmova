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

## Where this sits

Rozmova is the middle of three. It does not fetch and it does not store.

```
Relay  ──▶  Intake ──▶ transcribe ──▶ translate ──▶ romanize ──┬──▶  Holo   (archive)
(ingest)                                                       └──▶  Relay  (publish)
```

Each boundary is an interface with a local implementation, so the chain runs
end to end before Relay or Holo exist. `php examples/pipeline.php` runs it.

### Intake — what Relay hands over

Transport is deliberately unspecified; webhook, queue and file drop all produce
the same envelope.

```json
{
  "id":           "tryzantha/2026-09-24/address",
  "source_url":   "https://www.president.gov.ua/news/example",
  "source_label": "Office of the President of Ukraine",
  "captured_at":  "2026-09-24T15:00:00Z",
  "media_type":   "audio/wav",
  "sha256":       "optional, but VERIFIED when present",
  "text":         "optional, for items published as text rather than speech"
}
```

`captured_at` is parsed strictly. `new DateTimeImmutable($value)` would accept
`"last tuesday"` and record a capture time the item never had.

A declared `sha256` is checked against the bytes, not believed. An archive that
records a digest it never verified is recording a claim. This is the one moment
it can still be checked cheaply, before anything derived exists.

Items that arrive as text are already transcribed, and their transcript is
attributed to the publisher rather than flagged as machine output — because it
is the publisher's own words.

### Holo — archival

`ArchiveStore` is content-addressed and write-once. `FilesystemArchiveStore`
stands in for Holo and shows the two properties the real thing needs: source
bytes addressed by their own hash, so identical payloads are stored once and a
reference verifies itself; and existing objects never rewritten, so an entry
cannot be silently replaced.

A correction is a new digest, so the original and the corrected record both
survive. There is a test for that.

### Relay — publishing

`Publisher` overwrites, on purpose: publishing the corrected version is the
point. Correction history lives in the archive, where each revision has its own
digest. `HttpPublisher` POSTs the record to Relay when it exists;
`FilesystemPublisher` writes it to disk until then.

## Archive pipeline

Romanization is one stage of the job. `src/Archive` is the skeleton around it.

```php
$pipeline = new Pipeline(
    new TranscribeStage($speechToText, 'uk'),
    new TranslateStage($translator, 'en'),
    new RomanizeStage(Romanizer::ukrainian(...)),
);

$record = $pipeline->run(Record::open($id, $provenance, $audio));
```

`php examples/pipeline.php` runs it end to end with fixture engines.

Three properties it is built to hold:

**Provenance.** Every record carries its source URL, capture time, media type
and a SHA-256 of the bytes as fetched, before any processing. An item without a
chain of custody is an anecdote.

**Machine output is labelled.** Every rendition names what produced it and
whether that was a person or an engine. A machine translation of a wartime
speech that is not visibly marked as machine output is a fabricated quote
waiting to be screenshotted, so the flag is required rather than inferred. The
romanization stage additionally records which editorial overrides fired, so a
reader who queries a spelling can be shown the rule.

**Append-only, and checkable.** Adding a rendition returns a new record rather
than mutating the old one, so a later pass cannot quietly overwrite an earlier
one. `digest()` hashes provenance and every rendition together, recomputable by
anyone holding the published record — an archive that can be silently edited is
not evidence of anything.

A stage that throws stops the run, returns the record as far as it got, and puts
the reason in the log. A partial record is worth more than a dropped one. The
fixture translator refuses unknown input rather than echoing it, so a missing
translation fails loudly instead of publishing Ukrainian as English.

### Engines

`SpeechToText` and `Translator` are two methods each. The engines behind them
will turn over repeatedly — Whisper is a 2023 model and the Ukrainian
leaderboard is already led by Conformers — and nothing above the interface
should have to notice. `FixtureSpeechToText` and `FixtureTranslator` are for
tests and the demo, not for production.

`HttpSpeechToText` and `HttpTranslator` talk to self-hosted services over a
two-endpoint contract:

```
POST /transcribe   multipart: audio, language
                -> {"text": "...", "engine": "..."}

POST /translate    {"text": "...", "from": "uk", "to": "en"}
                -> {"text": "...", "engine": "..."}
```

The `engine` field is not decoration: it is recorded against every rendition,
so a record published today still names the model that produced it after that
model has been replaced twice.

The transport retries connection failures and 5xx, and never retries 4xx — a
malformed request fails identically three times and the pipeline should hear
about it once. Every failure raises rather than returning a default, because a
silently empty transcript is a published empty transcript.

`deploy/` has a working reference: two containers, a compose file, and sizing
notes. An archive is batch work, so it runs on CPU.

## Tests

No dependencies. The scheme suite asserts every example in the official KMU 2010
table, so a pass means the output matches what the Ukrainian state applies to
its own passports.

```
php tests/scheme.php        # 82 cases -- every example in the KMU 2010 table
php tests/preferences.php   # 24 cases -- Ukrainian editorial layer
php tests/russian.php       # 33 cases -- Russian schemes and editorial layer
php tests/pipeline.php      # 20 cases -- archive pipeline guarantees
php tests/http.php          # 16 cases -- HTTP drivers against a live fixture server
php tests/boundaries.php    # 28 cases -- intake, archival and publishing
```

Or `composer test`.

## License

MIT. See [LICENSE](LICENSE).
