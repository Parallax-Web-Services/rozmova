/*
 * What a browser shell loads into a page.
 *
 * Shell-agnostic on purpose: this is the same module whether it is injected by
 * Electron's webContents, a Chromium content script, or an extension. It owns
 * the reading mode and nothing else — no UI, no storage, no network. The shell
 * decides when to call it and how the control looks.
 *
 *   import { Reader } from './content.js';
 *
 *   const reader = await Reader.create({ mode: 'ruby' });
 *   reader.toggle();          // on, then off
 *   reader.destroy();         // leaves the page exactly as found
 */

import { segment, segmentRu, PreferenceList } from '../demo/rozmova.js';
import { romanizePage, revert, isApplied, STYLE as STYLE_TEXT } from './annotate.js';
import { detectDocument } from './detect.js';

const STYLE_ID = 'rozmova-style';

export class Reader {
  #options;
  #observer = null;
  #active = false;

  constructor(options) {
    this.#options = options;
  }

  /**
   * @param {object} opts
   * @param {'ruby'|'replace'} opts.mode
   * @param {string} opts.ukScheme   default 'national'
   * @param {string} opts.ruScheme   default 'bgn'
   * @param {object} opts.preferences  { uk: entries[], ru: entries[] }
   * @param {Document|Element} opts.root
   * @param {boolean} opts.live      re-apply to content added after load
   */
  static async create(opts = {}) {
    const {
      mode = 'ruby',
      ukScheme = 'national',
      ruScheme = 'bgn',
      preferences = null,
      root = document.body,
      live = true,
    } = opts;

    const prefsUk = new PreferenceList(preferences?.uk ?? []);
    const prefsRu = new PreferenceList(preferences?.ru ?? []);

    return new Reader({
      mode,
      root,
      live,
      segment: (text, lang) =>
        lang === 'ru' ? segmentRu(text, ruScheme, prefsRu) : segment(text, ukScheme, prefsUk),
    });
  }

  get active() {
    return this.#active;
  }

  /** What the page looks like to the detector, before anything is changed. */
  survey() {
    return detectDocument(this.#options.root);
  }

  apply() {
    if (this.#active) return { changed: 0, alreadyOn: true };

    this.#injectStyle();
    const result = romanizePage(this.#options.root, {
      mode: this.#options.mode,
      segment: this.#options.segment,
    });

    this.#active = true;
    if (this.#options.live) this.#watch();

    return result;
  }

  remove() {
    if (!this.#active) return { restored: 0 };

    this.#unwatch();
    const result = revert(this.#options.root);
    this.#active = false;

    return result;
  }

  toggle() {
    return this.#active ? this.remove() : this.apply();
  }

  destroy() {
    this.remove();
    document.getElementById(STYLE_ID)?.remove();
  }

  /*
   * Pages add content after load, and a reading mode that only works on the
   * first paint is a reading mode that stops working the moment someone
   * scrolls a feed. Re-runs are driven by the observer and are safe because
   * romanizePage skips what it has already marked.
   */
  #watch() {
    if (this.#observer || typeof MutationObserver === 'undefined') return;

    let scheduled = false;

    this.#observer = new MutationObserver(() => {
      if (scheduled) return;
      scheduled = true;

      const run = () => {
        scheduled = false;
        if (!this.#active) return;

        this.#observer.disconnect();
        romanizePage(this.#options.root, {
          mode: this.#options.mode,
          segment: this.#options.segment,
        });
        this.#connect();
      };

      // Batch a burst of mutations into one pass.
      if (typeof requestIdleCallback === 'function') requestIdleCallback(run, { timeout: 500 });
      else setTimeout(run, 100);
    });

    this.#connect();
  }

  #connect() {
    this.#observer?.observe(this.#options.root, { childList: true, subtree: true, characterData: true });
  }

  #unwatch() {
    this.#observer?.disconnect();
    this.#observer = null;
  }

  #injectStyle() {
    if (document.getElementById(STYLE_ID)) return;

    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = STYLE_TEXT;
    document.head.appendChild(style);
  }
}


export { isApplied };
