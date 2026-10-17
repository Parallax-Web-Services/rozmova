/*
 * Romanizes Cyrillic text in a live page, reversibly.
 *
 * Two modes:
 *
 *   replace   the romanization stands in for the original
 *   ruby      the romanization sits above the original, per word, as a <ruby>
 *             annotation -- the element the web already has for pronunciation
 *             guides. For a learner this is the useful one: the Cyrillic stays
 *             on the page to be learned, with the reading above it.
 *
 * Rules the walker holds to, because a reading aid that breaks pages is worse
 * than no reading aid:
 *
 *   - never touches script, style, code, pre, form controls or editable
 *     regions, so nothing the page or the user depends on is rewritten
 *   - honours translate="no" and .notranslate, the existing opt-out that
 *     publishers already use for text that must not be machine-altered
 *   - keeps every original, so revert() restores the page exactly
 *   - marks what it has done, so a second pass is a no-op rather than
 *     romanizing its own output
 */

import { detect, detectDocument } from './detect.js';

const SKIP_TAGS = new Set([
  'SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION',
  'CODE', 'PRE', 'KBD', 'SAMP', 'VAR', 'SVG', 'MATH', 'RUBY', 'RT', 'RP',
]);

const MARK = 'data-rozmova';
const ORIGINAL = 'data-rozmova-original';
const CYRILLIC_RUN = /(\p{Script=Cyrillic}[\p{Script=Cyrillic}ʼ’'’-]*)/gu;

function isSkippable(node) {
  for (let el = node.parentElement; el; el = el.parentElement) {
    if (SKIP_TAGS.has(el.tagName)) return true;
    if (el.isContentEditable) return true;
    if (el.getAttribute('translate') === 'no') return true;
    if (el.classList && el.classList.contains('notranslate')) return true;
    if (el.hasAttribute(MARK)) return true;
  }
  return false;
}

function textNodes(root) {
  const doc = root.ownerDocument || root;
  const walker = doc.createTreeWalker(root, 4 /* SHOW_TEXT */, {
    acceptNode(node) {
      if (!node.nodeValue || !node.nodeValue.trim()) return 2 /* REJECT */;
      if (!/\p{Script=Cyrillic}/u.test(node.nodeValue)) return 2;
      if (isSkippable(node)) return 2;
      return 1 /* ACCEPT */;
    },
  });

  const found = [];
  while (walker.nextNode()) found.push(walker.currentNode);
  return found;
}

/**
 * @param {Node} root
 * @param {object} options
 * @param {'replace'|'ruby'} options.mode
 * @param {(text, language) => Array<{source,reading,word,override}>} options.segment
 * @param {'uk'|'ru'|null} options.language  overrides detection entirely
 * @param {number} options.maxNodes          safety valve on very large pages
 */
export function romanizePage(root, options) {
  const { mode = 'ruby', segment, language = null, maxNodes = 5000 } = options;

  if (typeof segment !== 'function') {
    throw new TypeError('romanizePage needs a segment(text, language) function.');
  }

  const doc = root.ownerDocument || root;
  const fallback = language || detectDocument(root).language;
  const nodes = textNodes(root).slice(0, maxNodes);

  let changed = 0;

  for (const node of nodes) {
    const lang = language || detect(node.nodeValue) || fallback;
    if (!lang) continue;

    const original = node.nodeValue;
    const holder = doc.createElement('span');
    holder.setAttribute(MARK, mode);
    holder.setAttribute('lang', lang);
    holder.setAttribute(ORIGINAL, original);

    const segments = segment(original, lang);

    if (mode === 'replace') {
      holder.textContent = segments.map((part) => part.reading).join('');
    } else {
      buildRuby(doc, holder, segments);
    }

    node.parentNode.replaceChild(holder, node);
    changed++;
  }

  return { changed, language: fallback, candidates: nodes.length };
}

/*
 * One <ruby> per segment the engine reports, not per whitespace-delimited
 * word. The distinction matters: a multi-word editorial entry such as
 * Белая Церковь → Bila Tserkva arrives as a single segment, so the reading
 * annotates the whole name instead of being lost to per-word romanization.
 */
function buildRuby(doc, holder, segments) {
  for (const part of segments) {
    if (!part.word || part.reading === part.source) {
      holder.appendChild(doc.createTextNode(part.source));
      continue;
    }

    const ruby = doc.createElement('ruby');
    ruby.appendChild(doc.createTextNode(part.source));

    const rt = doc.createElement('rt');
    rt.textContent = part.reading;
    if (part.override) rt.setAttribute('data-rozmova-editorial', '');
    ruby.appendChild(rt);

    holder.appendChild(ruby);
  }
}

/** Put the page back exactly as it was. */
export function revert(root) {
  const doc = root.ownerDocument || root;
  const holders = root.querySelectorAll(`[${MARK}]`);
  let restored = 0;

  for (const holder of holders) {
    holder.parentNode.replaceChild(doc.createTextNode(holder.getAttribute(ORIGINAL)), holder);
    restored++;
  }

  return { restored };
}

export function isApplied(root) {
  return root.querySelector(`[${MARK}]`) !== null;
}

/** Default styling for ruby readings; a shell may replace it wholesale. */
export const STYLE = `
[${MARK}] ruby { ruby-position: over; }
[${MARK}] rt[data-rozmova-editorial] { font-style: italic; }
[${MARK}] rt {
  font-size: .62em;
  font-family: ui-monospace, "SF Mono", Menlo, monospace;
  font-weight: 400;
  opacity: .72;
  letter-spacing: .01em;
  user-select: text;
}
`;
