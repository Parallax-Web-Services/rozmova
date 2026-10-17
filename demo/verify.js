/*
 * Holds the JavaScript port to the PHP engine.
 *
 * Two implementations of one standard drift apart quietly. This runs both over
 * the same corpus -- the official KMU 2010 examples, every surface form in the
 * Ukrainian preference list, and sentences carrying punctuation and case -- and
 * requires byte-identical output. Regenerate the PHP side with:
 *
 *   php demo/dump-php.php > demo/php-output.json
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import {
  transliterate, romanize, transliterateRu, romanizeRu,
  PreferenceList, SCHEMES, RU_SCHEMES,
} from './rozmova.js';

const here = dirname(fileURLToPath(import.meta.url));
const read = (p) => JSON.parse(readFileSync(join(here, p), 'utf8'));

const corpus = read('corpus.json');
const corpusRu = read('corpus.ru.json');
const expected = read('php-output.json');
const prefs = new PreferenceList(read('../data/preferences.uk.json').entries);
const prefsRu = new PreferenceList(read('../data/preferences.ru.json').entries);

let checked = 0;
const failures = [];

for (const scheme of Object.values(SCHEMES)) {
  for (const item of corpus) {
    const got = transliterate(item, scheme);
    const want = expected.schemes[scheme][item];
    checked++;
    if (got !== want) failures.push(`[${scheme}] ${item}\n    php: ${want}\n    js:  ${got}`);
  }
}

for (const item of corpus) {
  const got = romanize(item, SCHEMES.NATIONAL, prefs).text;
  const want = expected.preferred[item];
  checked++;
  if (got !== want) failures.push(`[preferred] ${item}\n    php: ${want}\n    js:  ${got}`);
}

for (const scheme of Object.values(RU_SCHEMES)) {
  for (const item of corpusRu) {
    const got = transliterateRu(item, scheme);
    const want = expected.ruSchemes[scheme][item];
    checked++;
    if (got !== want) failures.push(`[ru/${scheme}] ${item}\n    php: ${want}\n    js:  ${got}`);
  }
}

for (const item of corpusRu) {
  const got = romanizeRu(item, RU_SCHEMES.BGN, prefsRu).text;
  const want = expected.ruPreferred[item];
  checked++;
  if (got !== want) failures.push(`[ru/preferred] ${item}\n    php: ${want}\n    js:  ${got}`);
}

console.log('js port vs php engine');
console.log('-'.repeat(70));
console.log(`  ${checked - failures.length} identical, ${failures.length} divergent  (${corpus.length} uk + ${corpusRu.length} ru corpus items)`);

if (failures.length) {
  console.log();
  console.log(failures.slice(0, 20).join('\n'));
  if (failures.length > 20) console.log(`  ...and ${failures.length - 20} more`);
  process.exit(1);
}
console.log(`  preference lists: ${prefs.size} uk forms, ${prefsRu.size} ru forms`);
