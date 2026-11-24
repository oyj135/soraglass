import { disposePage } from './lifecycle';
import { closeNavigationUI } from './navigation-ui';
import { loadingHide, loadingShow } from './loading';
import { paceFinish, paceStart } from './rightside';
let render: (() => void) | null = null, active: AbortController | null = null, version = 0;
let pendingHref: string | null = null;
const key = 'sg-navigation';
/* history 写入容错（2026-11-20）：浏览器对 30 秒内超过 100 次 pushState/replaceState
   会直接抛 SecurityError。滚动记录一帧一次，长页面连续滚动必然会撞上限，
   未捕获时 rAF 整帧中断、地址栏停在半路，还会把页面交互一起打断。 */
function replaceState(state: Record<string, unknown>, href: string): void {
  try { history.replaceState(state, '', href); } catch { /* 配额耗尽时放弃本次记录，不影响导航 */ }
}
function pushState(state: Record<string, unknown>, href: string): void {
  try { history.pushState(state, '', href); } catch { /* 同上 */ }
}
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
  loadingHide();
  paceFinish();
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
      replaceState({ ...history.state, [key]: true, y: scrollY }, location.href);
      pushState({ [key]: true, y }, url.href);
    }
    position(url, y); return;
  }
  if (active && pendingHref === url.href) return;
  active?.abort(); const controller = new AbortController(); active = controller; pendingHref = url.href; const id = ++version;
  const timeout = window.setTimeout(() => controller.abort(), 8000);
  document.documentElement.classList.add('sg-navigating');
  document.querySelector('#sg-main')?.setAttribute('aria-busy', 'true');
  /* 源站 pjax:send 同款：请求一发出就盖上全屏加载遮罩（heo 圆头像 + 绿点），
     顶部胶囊同时跑进度（源站 pace.js 在 pjax 期间的表现） */
  loadingShow();
  paceStart();
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
      replaceState({ ...history.state, [key]: true, y: scrollY }, location.href);
      pushState({ [key]: true, y: 0 }, destination.href);
    }
    paceFinish();
    /* 岁候回归（2026-11-24）：仅「岁候 → 岁候」的站内换页启用 View Transitions ——
       盘转针定保证了轮盘角度换页不跳，转场时盘几乎不动、只有纸面内容换。
       不支持的浏览器 / reduced-motion / 跨页型导航一律走原路径。 */
    const seasonToSeason = !!current.querySelector('.sg-season') && !!main.querySelector('.sg-season');
    const vtApi = document as unknown as { startViewTransition?: (update: () => void) => { finished: Promise<void> } };
    const useVT = typeof vtApi.startViewTransition === 'function'
      && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
      && seasonToSeason;
    const swap = (): void => {
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
      render?.(); main.setAttribute('tabindex', '-1'); (main as HTMLElement).focus({ preventScroll: true }); position(new URL(location.href), y);
    };
    if (useVT) {
      /* 旧帧快照前先揭加载遮罩，转场才不会把遮罩拍进去 */
      loadingHide();
      document.documentElement.classList.add('sg-vt');
      const startVT = vtApi.startViewTransition;
      const vt = startVT?.call(document, () => { swap(); });
      if (vt) {
        void vt.finished.finally(() => document.documentElement.classList.remove('sg-vt'));
      } else {
        document.documentElement.classList.remove('sg-vt');
      }
    } else {
      swap();
      /* 新页已挂载，揭遮罩（源站 pjax:complete → endLoading） */
      loadingHide();
    }
    document.dispatchEvent(new CustomEvent('sg:navigated'));
  } catch {
    loadingHide();
    paceFinish();
    if (id === version) location.assign(url.href);
  } finally {
    clearTimeout(timeout); if (id === version) { active = null; pendingHref = null; document.documentElement.classList.remove('sg-navigating'); document.querySelector('#sg-main')?.removeAttribute('aria-busy'); }
  }
}
export function initNavigation(mount: () => void): void {
  render = mount; replaceState({ ...history.state, [key]: true, y: scrollY }, location.href); history.scrollRestoration = 'manual';
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
  /* 滚动位置记录（2026-11-20 重写）：按固定 800ms 间隔写一次，滚动停下再补写最终位置。
     Chrome 对同一文档 30 秒内的 history 写入上限是 100 次，而滚动按帧记录在长页面上
     两秒就能写满 —— 撞限后 pushState/replaceState 直接抛 SecurityError，
     表现就是地址栏停在半路、控制台刷 "pushState more than 100 times"。 */
  let scrollFrame = 0, lastAt = 0, trailing = 0;
  const record = (y: number): void => {
    lastAt = performance.now();
    replaceState({ ...history.state, [key]: true, y }, location.href);
  };
  window.addEventListener('scroll', () => {
    if (scrollFrame) return;
    scrollFrame = requestAnimationFrame(() => {
      scrollFrame = 0;
      if (active || currentRoute !== routeKey(new URL(location.href))) return;
      if (performance.now() - lastAt >= 800) { window.clearTimeout(trailing); record(scrollY); return; }
      window.clearTimeout(trailing);
      trailing = window.setTimeout(() => { if (!active) record(scrollY); }, 700);
    });
  }, { passive: true });
}
