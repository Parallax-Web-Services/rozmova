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
  const applied = [];

  if (!preferences) {
    return { text: transliterate(text, scheme), applied };
  }

  const tokens = splitWords(text);
  let out = '';
  let i = 0;

  while (i < tokens.length) {
    const [isWord, chunk] = tokens[i];

    if (!isWord) {
      out += chunk;
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
      out += shouting ? best.entry.latin.toUpperCase() : best.entry.latin;
      applied.push({ cyrillic: best.source, latin: best.entry.latin, gloss: best.entry.gloss });
      i += best.consumed;
      continue;
    }

    out += transliterateWord(chunk, scheme);
    i++;
  }

  return { text: out, applied };
}

export const SCHEMES = { NATIONAL, BGN, LEARNER };
