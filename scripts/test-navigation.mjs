import assert from 'node:assert/strict';
import { test } from 'node:test';
import vm from 'node:vm';
import { build } from 'esbuild';
import { fileURLToPath } from 'node:url';

const { outputFiles } = await build({
  entryPoints: [fileURLToPath(new URL('../src/scripts/modules/navigation.ts', import.meta.url))],
  bundle: true, write: false, format: 'iife', globalName: 'navigation', platform: 'browser',
});

function setup(initial = '/a.html') {
  const requests = [], timers = new Map(), handlers = new Map(), assigned = [];
  const location = new URL(initial, 'https://theme.test');
  location.assign = href => assigned.push(href);
  let timerId = 0, mounted = 0, positioned = null;
  class Element {
    attrs = new Map();
    classes = new Set();
    classList = {
      add: (...names) => names.forEach(name => this.classes.add(name)),
      remove: (...names) => names.forEach(name => this.classes.delete(name)),
      contains: name => this.classes.has(name),
      toggle: (name, force = !this.classes.has(name)) => { if (force) this.classes.add(name); else this.classes.delete(name); return force; },
    };
    constructor(owner, selector) { this.owner = owner; this.selector = selector; }
    getAttribute(name) { return this.attrs.get(name) ?? null; }
    setAttribute(name, value) { this.attrs.set(name, value); }
    removeAttribute(name) { this.attrs.delete(name); }
    hasAttribute(name) { return this.attrs.has(name); }
    replaceWith(next) { this.owner.nodes.set(this.selector, next); next.owner = this.owner; }
    focus() {}
    scrollIntoView() { positioned = this.selector; }
    querySelector() { return null; }
    closest(selector) { return selector === 'a[href]' ? this : null; }
  }
  function page(path) {
    const doc = {
      title: path, nodes: new Map(),
      querySelector(selector) { return this.nodes.get(selector) ?? null; },
      querySelectorAll(selector) { return this.nodes.has(selector) ? [this.nodes.get(selector)] : []; },
      getElementById(id) { return this.nodes.get(`#${id}`) ?? null; },
      addEventListener(type, handler) { handlers.set(`document:${type}`, handler); },
      dispatchEvent() {}, head: { append() {} },
    };
    for (const selector of ['#sg-main', '[data-sg-config]', '[data-sg-style]', '[data-sg-bg]', '[data-sg-nav]', '[data-sg-sheet]', '[data-sg-sheet-open]', '[data-sg-rightside]', '#section']) {
      doc.nodes.set(selector, new Element(doc, selector));
    }
    doc.documentElement = new Element(doc, 'html');
    doc.querySelector('#sg-main').path = path;
    doc.querySelector('[data-sg-sheet]').path = path;
    doc.querySelector('[data-sg-rightside]').path = path;
    doc.querySelector('[data-sg-sheet]').setAttribute('aria-hidden', 'true');
    doc.querySelector('[data-sg-config]').textContent = '{}';
    doc.querySelector('[data-sg-style]').setAttribute('href', '/theme.css');
    return doc;
  }
  const document = page(initial);
  const entries = [{ href: location.href, state: null }];
  let index = 0;
  const history = {
    state: null,
    replaceState(state, _, href) { this.state = state; location.href = new URL(href, location).href; entries[index] = { href: location.href, state }; },
    pushState(state, _, href) { this.state = state; location.href = new URL(href, location).href; entries.splice(++index); entries.push({ href: location.href, state }); },
  };
  const context = vm.createContext({
    URL, AbortController, Element, HTMLElement: Element, HTMLAnchorElement: Element,
    CustomEvent: class { constructor(type) { this.type = type; } },
    document, location, history, scrollY: 0,
    setTimeout(fn, ms) { const id = ++timerId; timers.set(id, { fn, ms }); return id; },
    clearTimeout(id) { timers.delete(id); },
    scrollTo() {}, requestAnimationFrame(fn) { fn(); return 1; },
    addEventListener(type, handler) { handlers.set(`window:${type}`, handler); },
    DOMParser: class { parseFromString(text) { return page(text); } },
    fetch(url, options) {
      return new Promise((resolve, reject) => {
        options.signal.addEventListener('abort', () => reject(new Error('Aborted')), { once: true });
        requests.push({ url: new URL(url), signal: options.signal, resolve, reject });
      });
    },
  });
  context.window = context;
  vm.runInContext(outputFiles[0].text, context);
  context.navigation.initNavigation(() => mounted++);
  const settle = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
  return {
    document, location, entries, requests, assigned, timers,
    get mounted() { return mounted; }, get positioned() { return positioned; },
    navigate: context.navigation.navigate, settle,
    back() { const entry = entries[--index]; location.href = entry.href; history.state = entry.state; handlers.get('window:popstate')({ state: entry.state }); },
    click(href, extra = {}) {
      const link = new Element(document, 'a'); link.href = new URL(href, location).href;
      const event = { button: 0, target: link, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...extra };
      handlers.get('document:click')(event); return event;
    },
    async respond(request = requests.at(-1), { status = 200, type = 'text/html', redirect } = {}) {
      const url = redirect ? new URL(redirect, location) : new URL(request.url);
      url.hash = '';
      request.resolve({ ok: status === 200, url: url.href, headers: { get: () => type }, text: async () => url.pathname });
      await settle();
    },
  };
}

test('normal navigation updates content, navigation and page-specific controls together', async () => {
  const h = setup(); h.click('/b.html'); await h.respond();
  assert.equal(h.document.querySelector('#sg-main').path, '/b.html');
  assert.equal(h.document.querySelector('[data-sg-sheet]').path, '/b.html');
  assert.equal(h.document.querySelector('[data-sg-rightside]').path, '/b.html');
  assert.equal(h.mounted, 1);
  assert.equal(h.assigned.length, 0);
});

test('clicking the destination while back navigation loads must not leave old content', async () => {
  const h = setup(); h.click('/b.html'); await h.respond();
  h.back(); h.click('/a.html'); await h.respond();
  assert.equal(h.location.pathname, '/a.html');
  assert.equal(h.document.querySelector('#sg-main').path, '/a.html');
});

test('a repeated current-page click closes the drawer without adding history', async () => {
  const h = setup();
  const sheet = h.document.querySelector('[data-sg-sheet]');
  sheet.classList.add('is-open'); sheet.setAttribute('aria-hidden', 'false');
  h.document.documentElement.classList.add('sg-lock');
  h.document.querySelector('[data-sg-sheet-open]').setAttribute('aria-expanded', 'true');
  h.click('/a.html');
  assert.equal(sheet.classList.contains('is-open'), false);
  assert.equal(sheet.getAttribute('aria-hidden'), 'true');
  assert.equal(h.document.documentElement.classList.contains('sg-lock'), false);
  assert.equal(h.document.querySelector('[data-sg-sheet-open]').getAttribute('aria-expanded'), 'false');
  assert.equal(h.entries.length, 1);
});

test('cross-page fragment survives fetch and scrolls to its target', async () => {
  const h = setup(); h.click('/b.html#section'); await h.respond();
  assert.equal(h.location.hash, '#section');
  assert.equal(h.positioned, '#section');
});

test('repeated clicks reuse the pending request instead of restarting it', async () => {
  const h = setup(); h.click('/b.html'); h.click('/b.html'); h.click('/b.html');
  assert.equal(h.requests.length, 1);
  assert.equal(h.requests[0].signal.aborted, false);
  await h.respond();
  assert.equal(h.document.querySelector('#sg-main').path, '/b.html');
});

test('only the most recent navigation can replace the page', async () => {
  const h = setup(); h.click('/b.html'); const first = h.requests[0];
  h.click('/c.html'); await h.respond(); await h.respond(first);
  assert.equal(first.signal.aborted, true);
  assert.equal(h.document.querySelector('#sg-main').path, '/c.html');
  assert.equal(h.assigned.length, 0);
});

test('selecting the displayed page cancels a pending destination', async () => {
  const h = setup(); h.click('/b.html'); h.click('/a.html'); await h.respond();
  assert.equal(h.document.querySelector('#sg-main').path, '/a.html');
  assert.equal(h.document.documentElement.classList.contains('sg-navigating'), false);
  assert.equal(h.assigned.length, 0);
});

test('failed requests fall back to full navigation and release busy state', async () => {
  const h = setup(); h.click('/b.html'); await h.respond(undefined, { status: 500 });
  assert.deepEqual(h.assigned, ['https://theme.test/b.html']);
  assert.equal(h.document.querySelector('#sg-main').hasAttribute('aria-busy'), false);
  assert.equal(h.timers.size, 0);
});

test('timeout falls back to full navigation', async () => {
  const h = setup(); h.click('/b.html'); [...h.timers.values()][0].fn(); await h.settle();
  assert.deepEqual(h.assigned, ['https://theme.test/b.html']);
  assert.equal(h.timers.size, 0);
});

test('modified clicks and external links retain native navigation', () => {
  const h = setup();
  assert.equal(h.click('/b.html', { ctrlKey: true }).defaultPrevented, false);
  assert.equal(h.click('/b.html', { button: 1 }).defaultPrevented, false);
  assert.equal(h.click('https://other.test/').defaultPrevented, false);
  assert.equal(h.requests.length, 0);
});
