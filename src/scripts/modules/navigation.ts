import { disposePage } from './lifecycle';
import { closeNavigationUI } from './navigation-ui';
let render: (() => void) | null = null, active: AbortController | null = null, version = 0;
let pendingHref: string | null = null;
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
  active?.abort(); active = null; pendingHref = null; version++;
  document.documentElement.classList.remove('sg-navigating');
  document.querySelector('#sg-main')?.removeAttribute('aria-busy');
}
export async function navigate(href: string, push = true, y = 0): Promise<void> {
  const url = new URL(href, location.href);
  if (!render || !eligible(url)) { location.assign(url.href); return; }
  closeNavigationUI();
  // popstate changes the address before the corresponding page has rendered.
  if (local(url) && currentRoute === routeKey(url) && push) {
    cancelNavigation();
    if (url.href !== location.href) {
      history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href);
      history.pushState({ [key]: true, y }, '', url);
    }
    position(url, y); return;
  }
  if (active && pendingHref === url.href) return;
  active?.abort(); const controller = new AbortController(); active = controller; pendingHref = url.href; const id = ++version;
  const timeout = window.setTimeout(() => controller.abort(), 8000);
  document.documentElement.classList.add('sg-navigating');
  document.querySelector('#sg-main')?.setAttribute('aria-busy', 'true');
  try {
    const response = await fetch(url, { signal: controller.signal, credentials: 'same-origin', headers: { 'X-Soraglass-Navigation': '1' } });
    const html = await response.text(); if (id !== version) return;
    if (!response.ok || !response.headers.get('Content-Type')?.includes('text/html')) throw new Error('Page unavailable');
    const next = new DOMParser().parseFromString(html, 'text/html'), main = next.querySelector('#sg-main'), current = document.querySelector('#sg-main');
    const runtime = next.querySelector('[data-sg-config]'), style = next.querySelector('[data-sg-style]');
    if (!main || !current || !runtime || !eligible(new URL(response.url)) || !style || style.getAttribute('href') !== document.querySelector('[data-sg-style]')?.getAttribute('href')) throw new Error('Full navigation required');
    /* 一次性修掉 PJAX 的 noscript 陷阱（2026-11-20）：DOMParser 建的是「脚本禁用」的
       惰性文档，`<noscript>` 里的内容会被当成真实标记解析；搬进当前文档后，其中的
       `<style>` 立刻生效 —— 首页天气/农历就是被 side-calendar 的 noscript 兜底样式
       整块 display:none 掉的，表现成「切页回来只剩日出日落」。
       PJAX 必然有 JS，noscript 兜底在这里没有任何意义，直接剔掉。 */
    main.querySelectorAll('noscript').forEach((node) => node.remove());
    const cfg = JSON.parse(runtime.textContent || '{}') as Partial<SGConfig>;
    const destination = new URL(response.url || url.href);
    if (!destination.hash) destination.hash = url.hash;
    if (push && destination.href !== location.href) {
      history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href);
      history.pushState({ [key]: true, y: 0 }, '', destination);
    }
    disposePage();
    document.querySelectorAll('.sg-lightbox').forEach(n => n.remove());
    closeNavigationUI();
    current.replaceWith(main); window.SG = cfg; document.querySelector('[data-sg-config]')?.replaceWith(runtime); document.title = next.title; currentRoute = routeKey(new URL(location.href));
    for (const name of ['description', 'keywords', 'robots']) {
      document.querySelector(`meta[name="${name}"]`)?.remove(); const meta = next.querySelector(`meta[name="${name}"]`); if (meta) document.head.append(meta);
    }
    for (const rel of ['canonical', 'prev', 'next']) {
      document.querySelector(`link[rel="${rel}"]`)?.remove(); const link = next.querySelector(`link[rel="${rel}"]`); if (link) document.head.append(link);
    }
    for (const attr of ['data-sg-blur']) { const value = next.documentElement.getAttribute(attr); if (value) document.documentElement.setAttribute(attr, value); }
    const bg = document.querySelector('[data-sg-bg]'), newBg = next.querySelector('[data-sg-bg]'); if (bg && newBg) bg.replaceWith(newBg);
    for (const selector of ['[data-sg-nav]', '[data-sg-sheet]', '[data-sg-rightside]']) {
      const previous = document.querySelector(selector), replacement = next.querySelector(selector);
      if (previous && replacement) previous.replaceWith(replacement);
    }
    render(); main.setAttribute('tabindex', '-1'); (main as HTMLElement).focus({ preventScroll: true }); position(new URL(location.href), y);
    document.dispatchEvent(new CustomEvent('sg:navigated'));
  } catch {
    if (id === version) location.assign(url.href);
  } finally {
    clearTimeout(timeout); if (id === version) { active = null; pendingHref = null; document.documentElement.classList.remove('sg-navigating'); document.querySelector('#sg-main')?.removeAttribute('aria-busy'); }
  }
}
export function initNavigation(mount: () => void): void {
  render = mount; history.replaceState({ ...history.state, [key]: true, y: scrollY }, '', location.href); history.scrollRestoration = 'manual';
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest<HTMLAnchorElement>('a[href]') : null;
    if (!link || link.target && link.target !== '_self' || link.hasAttribute('download') || link.hasAttribute('data-no-navigation') || link.closest('[contenteditable]')) return;
    const url = new URL(link.href, location.href); if (!eligible(url) || /\.[a-z0-9]{1,8}$/i.test(url.pathname) && !/\.(html?|php)$/i.test(url.pathname)) return;
    if (local(url) && url.hash && currentRoute === routeKey(url)) {
      cancelNavigation(); closeNavigationUI(); return;
    }
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
