/*
 * Rozmova — Ukrainian romanization, JavaScript port.
 *
 * A second implementation of src/UkrainianTransliterator.php and
 * src/PreferenceList.php, so the demo can romanize in the browser.
 *
 * Two implementations of the same standard drift. This one is held to the PHP
 * by demo/verify.js, which runs both over the official KMU 2010 example table
 * and every preference form in data/preferences.uk.json and requires identical
 * output. If they diverge, that check fails.
 */

const NATIONAL = 'national';
const BGN = 'bgn';
const LEARNER = 'learner';

const TABLES = {
  [NATIONAL]: {
    а: 'a', б: 'b', в: 'v', г: 'h', ґ: 'g', д: 'd', е: 'e',
    є: { initial: 'ye', default: 'ie' },
    ж: 'zh', з: 'z', и: 'y', і: 'i',
    ї: { initial: 'yi', default: 'i' },
    й: { initial: 'y', default: 'i' },
    к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's',
    т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch',
    ю: { initial: 'yu', default: 'iu' },
    я: { initial: 'ya', default: 'ia' },
    ь: '',
  },
  [BGN]: {
    а: 'a', б: 'b', в: 'v', г: 'h', ґ: 'g', д: 'd', е: 'e', є: 'ye',
    ж: 'zh', з: 'z', и: 'y', і: 'i', ї: 'yi', й: 'y', к: 'k', л: 'l',
    м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
    ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch',
    ю: 'yu', я: 'ya', ь: '’',
  },
  [LEARNER]: {
    а: 'a', б: 'b', в: 'v', г: 'h', ґ: 'g', д: 'd', е: 'e', є: 'ye',
    ж: 'zh', з: 'z', и: 'y', і: 'i', ї: 'yi', й: 'j', к: 'k', л: 'l',
    м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u',
    ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch',
    ю: 'yu', я: 'ya', ь: 'ʹ',
  },
};

const DIGRAPHS = {
  [NATIONAL]: { зг: 'zgh' },
  [BGN]: { зг: 'z·h' },
  [LEARNER]: { зг: 'z-h' },
};

const APOSTROPHE_OUT = {
  [NATIONAL]: '',
  [BGN]: '”',
  [LEARNER]: 'ʺ',
};

const APOSTROPHES = ["'", '’', '‘', 'ʼ', '´', '`'];
const CYRILLIC = /\p{Script=Cyrillic}/u;

const isApostrophe = (c) => APOSTROPHES.includes(c);
const isLetter = (c) => CYRILLIC.test(c);

function applyCase(replacement, source, allCaps) {
  if (replacement === '') return '';
  if (allCaps) return replacement.toUpperCase();
  if (source.toLowerCase() === source) return replacement;
  return replacement.charAt(0).toUpperCase() + replacement.slice(1);
}

function isAllCaps(chars) {
  let letters = 0;
  for (const c of chars) {
    if (!isLetter(c)) continue;
    if (c.toUpperCase() !== c) return false;
    letters++;
  }
  return letters > 1;
}

function splitWords(text) {
  const chunks = [];
  let buffer = '';
  let inWord = false;

  for (const char of Array.from(text)) {
    const isWordChar = isLetter(char) || isApostrophe(char);
    if (isWordChar !== inWord && buffer !== '') {
      chunks.push([inWord, buffer]);
      buffer = '';
    }
    inWord = isWordChar;
    buffer += char;
  }
  if (buffer !== '') chunks.push([inWord, buffer]);
  return chunks;
}

function transliterateWord(word, scheme) {
  const chars = Array.from(word);
  const allCaps = isAllCaps(chars);
  const table = TABLES[scheme];
  const digraphs = DIGRAPHS[scheme];
  let out = '';
  let atStart = true;

  for (let i = 0; i < chars.length; i++) {
    const char = chars[i];

    if (isApostrophe(char)) {
      out += APOSTROPHE_OUT[scheme];
      atStart = false;
      continue;
    }

    const lower = char.toLowerCase();
    let matched = null;

    if (i + 1 < chars.length) {
      const pair = lower + chars[i + 1].toLowerCase();
      if (Object.prototype.hasOwnProperty.call(digraphs, pair)) {
        matched = digraphs[pair];
        i++;
      }
    }

    if (matched === null) {
      if (!Object.prototype.hasOwnProperty.call(table, lower)) {
        out += char;
        atStart = false;
        continue;
      }
      const value = table[lower];
      matched = typeof value === 'string' ? value : (atStart ? value.initial : value.default);
    }

    out += applyCase(matched, char, allCaps);
    atStart = false;
  }

  return out;
}

export function transliterate(text, scheme = NATIONAL) {
  return splitWords(text)
    .map(([isWord, chunk]) => (isWord ? transliterateWord(chunk, scheme) : chunk))
    .join('');
}

/* ---- preference layer ---- */

const ADJ_M = ['ий', 'ого', 'ому', 'им', 'ім'];
const ADJ_F = ['а', 'ої', 'ій', 'у', 'ою'];

function expandAdjectival(stems, feminine) {
  const endings = feminine ? ADJ_F : ADJ_M;
  const out = [];
  for (const stem of stems) {
    out.push(stem);
    const base = stem.replace(/(ий|ій|а)$/u, '');
    if (base === stem) continue;
    for (const ending of endings) out.push(base + ending);
  }
  return [...new Set(out)];
}

function normalise(text) {
  return text
    .replace(/[’‘ʼ´`]/g, "'")
    .replace(/\s+/gu, ' ')
    .trim()
    .toLowerCase();
}

export class PreferenceList {
  constructor(entries = []) {
    this.index = new Map();
    this.maxWords = 1;

    for (const entry of entries) {
      let forms = entry.forms || [];
      if (entry.declension === 'adj' || entry.declension === 'adj-f') {
        forms = expandAdjectival(forms, entry.declension === 'adj-f');
      }

      for (const form of forms) {
        const key = normalise(form);
        if (key === '') continue;

        const existing = this.index.get(key);
        if (existing && existing.latin !== entry.latin) {
          throw new Error(
            `Preference conflict: "${form}" is claimed by both "${existing.latin}" and "${entry.latin}".`
          );
        }

        this.index.set(key, { latin: entry.latin, gloss: entry.gloss ?? null, note: entry.note ?? null });
        this.maxWords = Math.max(this.maxWords, key.split(' ').length);
      }
    }
  }

  lookup(phrase) {
    return this.index.get(normalise(phrase)) ?? null;
  }

  get size() {
    return this.index.size;
  }
}

export function romanize(text, scheme = NATIONAL, preferences = null) {
  return romanizeWith(text, (w) => transliterateWord(w, scheme), preferences);
}

/*
 * Preference matching, independent of which alphabet's word transform runs
 * underneath. Ukrainian and Russian share it so the editorial layer behaves
 * identically for both.
 */
function romanizeWith(text, wordFn, preferences = null) {
  const segments = segmentWith(text, wordFn, preferences);

  return {
    text: segments.map((s) => s.reading).join(''),
    applied: segments
      .filter((s) => s.override)
      .map((s) => ({ cyrillic: s.source, latin: s.reading, gloss: s.gloss })),
  };
}

/**
 * The same walk, reported piece by piece instead of joined.
 *
 * A caller that annotates each word separately — ruby readings over the
 * original, say — cannot simply split on spaces and romanize each word: a
 * multi-word preference entry like Белая Церковь → Bila Tserkva would never
 * fire, and the output would quietly fall back to the scheme. Segments keep
 * each override whole, so the annotation lands on the span it belongs to.
 *
 * @returns {Array<{source: string, reading: string, word: boolean, override: boolean, gloss: ?string}>}
 */
export function segment(text, scheme = NATIONAL, preferences = null) {
  return segmentWith(text, (w) => transliterateWord(w, scheme), preferences);
}

export function segmentRu(text, scheme = RU_BGN, preferences = null) {
  return segmentWith(text, (w) => transliterateRuWord(w, scheme), preferences);
}

function segmentWith(text, wordFn, preferences = null) {
  const out = [];

  if (!preferences) {
    for (const [isWord, chunk] of splitWords(text)) {
      out.push({
        source: chunk,
        reading: isWord ? wordFn(chunk) : chunk,
        word: isWord,
        override: false,
        gloss: null,
      });
    }
    return out;
  }

  const tokens = splitWords(text);
  let i = 0;

  while (i < tokens.length) {
    const [isWord, chunk] = tokens[i];

    if (!isWord) {
      out.push({ source: chunk, reading: chunk, word: false, override: false, gloss: null });
      i++;
      continue;
    }

    // Longest match first, spanning whitespace only.
    let best = null;
    let words = 0;
    let source = '';
    let consumed = 0;

    for (let j = i; j < tokens.length && words < preferences.maxWords; j++) {
      const [jIsWord, jChunk] = tokens[j];

      if (!jIsWord) {
        if (jChunk.trim() !== '') break;
        source += jChunk;
        consumed = j - i + 1;
        continue;
      }

      source += jChunk;
      words++;
      consumed = j - i + 1;

      const entry = preferences.lookup(source);
      if (entry) best = { consumed, entry, source };
    }

    if (best) {
      const letters = best.source.replace(/[^\p{L}]/gu, '');
      const shouting = letters.length > 1 && letters.toUpperCase() === letters;
      out.push({
        source: best.source,
        reading: shouting ? best.entry.latin.toUpperCase() : best.entry.latin,
        word: true,
        override: true,
        gloss: best.entry.gloss,
      });
      i += best.consumed;
      continue;
    }

    out.push({ source: chunk, reading: wordFn(chunk), word: true, override: false, gloss: null });
    i++;
  }

  return out;
}

export const SCHEMES = { NATIONAL, BGN, LEARNER };

/* ---- Russian ---------------------------------------------------------- */

const RU_BGN = 'bgn';
const RU_PASSPORT = 'passport';
const RU_SCIENTIFIC = 'scientific';

/* Letters after which BGN yots a following е or ё. */
const YOTTING = ['а', 'е', 'ё', 'и', 'о', 'у', 'ы', 'э', 'ю', 'я', 'й', 'ъ', 'ь'];

const RU_TABLES = {
  [RU_BGN]: {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd',
    е: { initial: 'ye', after: { set: YOTTING, value: 'ye' }, default: 'e' },
    ё: { initial: 'yë', after: { set: YOTTING, value: 'yë' }, default: 'ë' },
    ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n',
    о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh',
    ц: 'ts', ч: 'ch', ш: 'sh', щ: 'shch',
    ъ: '”', ы: 'y', ь: '’', э: 'e', ю: 'yu', я: 'ya',
  },
  [RU_PASSPORT]: {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh',
    з: 'z', и: 'i', й: 'i', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o',
    п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts',
    ч: 'ch', ш: 'sh', щ: 'shch', ъ: 'ie', ы: 'y', ь: '', э: 'e',
    ю: 'iu', я: 'ia',
  },
  [RU_SCIENTIFIC]: {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'ë', ж: 'ž',
    з: 'z', и: 'i', й: 'j', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o',
    п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'c',
    ч: 'č', ш: 'š', щ: 'ŝ', ъ: 'ʺ', ы: 'y', ь: 'ʹ', э: 'è',
    ю: 'û', я: 'â',
  },
};

const RU_DIGRAPHS = {
  [RU_BGN]: { тс: 't·s' },
  [RU_PASSPORT]: {},
  [RU_SCIENTIFIC]: {},
};

function resolveRu(value, atStart, prev) {
  if (typeof value === 'string') return value;
  if (atStart && value.initial !== undefined) return value.initial;
  if (prev !== null && value.after && value.after.set.includes(prev)) return value.after.value;
  return value.default;
}

function transliterateRuWord(word, scheme) {
  const chars = Array.from(word);
  const allCaps = isAllCaps(chars);
  const table = RU_TABLES[scheme];
  const digraphs = RU_DIGRAPHS[scheme];
  let out = '';
  let atStart = true;
  let prev = null;

  for (let i = 0; i < chars.length; i++) {
    const char = chars[i];

    if (isApostrophe(char)) {
      atStart = false;
      prev = "'";
      continue;
    }

    const lower = char.toLowerCase();
    let matched = null;

    if (i + 1 < chars.length) {
      const pair = lower + chars[i + 1].toLowerCase();
      if (Object.prototype.hasOwnProperty.call(digraphs, pair)) {
        matched = digraphs[pair];
        i++;
        prev = chars[i].toLowerCase();
      }
    }

    if (matched === null) {
      if (!Object.prototype.hasOwnProperty.call(table, lower)) {
        out += char;
        atStart = false;
        prev = lower;
        continue;
      }
      matched = resolveRu(table[lower], atStart, prev);
      prev = lower;
    }

    out += applyCase(matched, char, allCaps);
    atStart = false;
  }

  return out;
}

export function transliterateRu(text, scheme = RU_BGN) {
  return splitWords(text)
    .map(([isWord, chunk]) => (isWord ? transliterateRuWord(chunk, scheme) : chunk))
    .join('');
}

export function romanizeRu(text, scheme = RU_BGN, preferences = null) {
  return romanizeWith(text, (w) => transliterateRuWord(w, scheme), preferences);
}

export const RU_SCHEMES = { BGN: RU_BGN, PASSPORT: RU_PASSPORT, SCIENTIFIC: RU_SCIENTIFIC };
