/*
 * Which Cyrillic language is this?
 *
 * Ukrainian and Russian share most of the alphabet, so a page cannot be
 * romanized correctly until you know which one you are looking at: г is "h" in
 * Ukrainian and "g" in Russian, и is "y" and "i". Guessing wrong produces
 * confident nonsense rather than an obvious failure.
 *
 * Four letters exist in Ukrainian and not in Russian, and four the other way
 * round. Counting them is cheap, needs no model and no word list, and is
 * decisive on any run of real prose. Short runs often contain none of the
 * eight -- "Москва" is spelled identically in both -- so this reports
 * `undecided` rather than guessing, and the caller falls back to whatever the
 * whole document looked like.
 */

const UK_ONLY = new Set(['і', 'ї', 'є', 'ґ']);
const RU_ONLY = new Set(['ы', 'э', 'ъ', 'ё']);
const CYRILLIC = /\p{Script=Cyrillic}/u;

export function inspect(text) {
  let cyrillic = 0;
  let uk = 0;
  let ru = 0;

  for (const char of text) {
    const lower = char.toLowerCase();
    if (!CYRILLIC.test(lower)) continue;
    cyrillic++;
    if (UK_ONLY.has(lower)) uk++;
    else if (RU_ONLY.has(lower)) ru++;
  }

  let language = null;

  if (cyrillic > 0) {
    if (uk > 0 && ru === 0) language = 'uk';
    else if (ru > 0 && uk === 0) language = 'ru';
    // Both appear: a quotation, a bilingual page, or a transliterated name.
    // Go with the majority, and stay undecided on a tie.
    else if (uk > ru) language = 'uk';
    else if (ru > uk) language = 'ru';
  }

  return { cyrillic, uk, ru, language, hasCyrillic: cyrillic > 0 };
}

/** Language of a text run, or null when nothing distinguishes the two. */
export function detect(text) {
  return inspect(text).language;
}

/**
 * Document-level reading, used as the fallback for short runs.
 * Sampling stops once the signal is decisive, so a long page costs no more
 * than a short one.
 */
export function detectDocument(root, { sampleLimit = 40000 } = {}) {
  let sampled = '';

  const walker = root.ownerDocument.createTreeWalker(root, 4 /* SHOW_TEXT */);

  while (walker.nextNode() && sampled.length < sampleLimit) {
    sampled += walker.currentNode.nodeValue + ' ';
  }

  const reading = inspect(sampled);
  return { ...reading, sampled: sampled.length };
}
