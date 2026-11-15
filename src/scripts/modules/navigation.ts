import { disposePage } from './lifecycle';
let render: (() => void) | null = null, active: AbortController | null = null, version = 0;
const key = 'sg-navigation';
const routeKey = (url: URL): string => { const copy = new URL(url); copy.searchParams.delete('album'); return copy.pathname + copy.search; };
let currentRoute = routeKey(new URL(location.href));
const eligible = (url: URL): boolean => url.origin === location.origin && !/\/(admin|action|usr|plugin-package|theme-package)(\/|$)/.test(url.pathname);
const local = (url: URL): boolean => url.pathname === location.pathname && url.search === location.search;
function position(url: URL, y = 0): void {
  let hash = url.hash.slice(1); try { hash = decodeURIComponent(hash); } catch { /* malformed hash stays literal */ }
  const target = hash ? document.getElementById(hash) : null;
  if (target) target.scrollIntoView(); else window.scrollTo(0, y);
}
function cancelNavigation(): void {
  active?.abort(); active = null; version++;
  document.documentElement.classList.remove('sg-navigating');
  document.querySelector('#sg-main')?.removeAttribute('aria-busy');
}
export async function navigate(href: string, push = true, y = 0): Promise<void> {
  const url = new URL(href, location.href);
  if (!render || !eligible(url)) { location.assign(url.href); return; }
  if (local(url) && push) {
    cancelNavigation(); history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href);
    history.pushState({ [key]: true, y }, '', url); position(url, y); return;
  }
  active?.abort(); const controller = new AbortController(); active = controller; const id = ++version;
  const timeout = window.setTimeout(() => controller.abort(), 20000);
  document.documentElement.classList.add('sg-navigating');
  document.querySelector('#sg-main')?.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Soraglass-Navigation': '1' } });
    const html = await response.text(); if (id !== version) return;
    if (!response.ok || !response.headers.get('Content-Type')?.includes('text/html')) throw new Error('Page unavailable');
    const next = new DOMParser().parseFromString(html, 'text/html'), main = next.querySelector('#sg-main'), current = document.querySelector('#sg-main');
    const runtime = next.querySelector('[data-sg-config]'), style = next.querySelector('[data-sg-style]');
    if (!main || !current || !runtime || !eligible(new URL(response.url)) || !style || style.getAttribute('href') !== document.querySelector('[data-sg-style]')?.getAttribute('href')) throw new Error('Full navigation required');
    const cfg = JSON.parse(runtime.textContent || '{}') as Partial<SGConfig>;
    if (push) { history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href); history.pushState({ [key]: true, y: 0 }, '', response.url || url.href); }
    disposePage();
    document.querySelectorAll('.sg-lightbox').forEach(n => n.remove()); document.documentElement.classList.remove('sg-lock');
    document.querySelector('[data-sg-sheet]')?.classList.remove('is-open');
    current.replaceWith(main); window.SG = cfg; document.querySelector('[data-sg-config]')?.replaceWith(runtime); document.title = next.title; currentRoute = routeKey(new URL(location.href));
    for (const name of ['description', 'keywords', 'robots']) {
      document.querySelector(`meta[name="${name}"]`)?.remove(); const meta = next.querySelector(`meta[name="${name}"]`); if (meta) document.head.append(meta);
    }
    for (const rel of ['canonical', 'prev', 'next']) {
      document.querySelector(`link[rel="${rel}"]`)?.remove(); const link = next.querySelector(`link[rel="${rel}"]`); if (link) document.head.append(link);
    }
    for (const attr of ['data-sg-veil', 'data-sg-blur']) { const value = next.documentElement.getAttribute(attr); if (value) document.documentElement.setAttribute(attr, value); }
    const bg = document.querySelector('[data-sg-bg]'), newBg = next.querySelector('[data-sg-bg]'); if (bg && newBg) bg.replaceWith(newBg);
    const nav = document.querySelector('[data-sg-nav]'), newNav = next.querySelector('[data-sg-nav]'); if (nav && newNav) nav.replaceWith(newNav);
    render(); main.setAttribute('tabindex', '-1'); (main as HTMLElement).focus({ preventScroll: true }); position(new URL(location.href), y);
    document.dispatchEvent(new CustomEvent('sg:navigated'));
  } catch {
    if (id === version) location.assign(url.href);
  } finally {
    clearTimeout(timeout); if (id === version) { active = null; document.documentElement.classList.remove('sg-navigating'); document.querySelector('#sg-main')?.removeAttribute('aria-busy'); }
  }
}
export function initNavigation(mount: () => void): void {
  render = mount; history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href); history.scrollRestoration = 'manual';
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = (event.target as Element)?.closest<HTMLAnchorElement>('a[href]');
    if (!link || link.target && link.target !== '_self' || link.hasAttribute('download') || link.hasAttribute('data-no-navigation') || link.closest('[contenteditable]')) return;
    const url = new URL(link.href, location.href); if (!eligible(url) || (local(url) && url.hash) || /\.[a-z0-9]{1,8}$/i.test(url.pathname) && !/\.(html?|php)$/i.test(url.pathname)) return;
    event.preventDefault(); void navigate(url.href);
  });
  window.addEventListener('popstate', event => {
    // Album URLs belong to the gallery's own routing when the pathname is unchanged.
    if (currentRoute === routeKey(new URL(location.href))) { cancelNavigation(); position(new URL(location.href), typeof event.state?.y === 'number' ? event.state.y : 0); return; }
    void navigate(location.href, false, typeof event.state?.y === 'number' ? event.state.y : 0);
  });
  let scrollFrame = 0;
  window.addEventListener('scroll', () => {
    if (scrollFrame) return;
    scrollFrame = requestAnimationFrame(() => {
      scrollFrame = 0;
      if (!active && currentRoute === routeKey(new URL(location.href))) history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href);
    });
  }, { passive: true });
}
