/**
 * 访客数缓存回显 + 文章阅读量（PJAX）回写。
 *
 * ① 访客数缓存（第十七轮修复「访客数 / 总访问量时显时不显」）：
 *   数字来自外部 vercount（busuanzi 镜像）异步脚本，网络抖动 / 被墙时占位「—」会一直挂着。
 *   方案：vercount 成功回写后把两个数字缓存进 localStorage；下次打开页面立即回显缓存值
 *   （带 title 说明时间），脚本随后到达时按原 id 覆盖成新值、缓存同步更新。
 *
 * ② 文章阅读量（2026-11-19 第九轮）：vercount 是 IIFE —— 只在加载时查一次 DOM，并把
 *   元素引用缓存进内部 map，没有 MutationObserver。站内 PJAX 换页不会重跑脚本，于是
 *   文章页的 #busuanzi_value_page_pv 永远停在「—」。源站是给 script 挂 data-pjax，
 *   由 Butterfly 的 pjax 重载同一个脚本（themes/fomalhaut/layout/includes/additional-js.pug）。
 *   这里同构：导航完成后摘掉旧 <script data-sg-counter> 再插一枚带时间戳的新脚本，
 *   脚本重新扫 DOM 并把 page_pv 写进当前页。副作用与源站一致：站内每次导航记一次 PV
 *   （UV 靠 cookie 去重，不受影响）。
 */
import { onPageDispose } from './lifecycle';

const KEY = 'sg:visitor-cache';
const IDS = ['busuanzi_value_site_uv', 'busuanzi_value_site_pv'] as const;
const COUNTER_SRC = 'https://events.vercount.one/js';

/** PJAX 换页后重跑 vercount：只有文章页挂 #busuanzi_value_page_pv，其他页直接跳过 */
function reloadCounter(): void {
  const node = document.getElementById('busuanzi_value_page_pv');
  if (!node) return;
  /* 先把占位复位：脚本失败时会回落 localStorage 里「上一页」的 page_pv，
     宁可显示「—」也不要把别的文章的阅读量安到这篇上。 */
  node.textContent = '—';
  document.querySelectorAll('script[data-sg-counter]').forEach((el) => el.remove());
  const script = document.createElement('script');
  script.async = true;
  script.src = `${COUNTER_SRC}?t=${Date.now()}`;
  script.dataset.sgCounter = '';
  document.body.appendChild(script);
}

export function initStatsCache(): void {
  /* 自定义事件不在 pageOn 的 DOM 事件联合里，手动绑 + 随页面 dispose 解绑 */
  document.addEventListener('sg:navigated', reloadCounter);
  onPageDispose(() => document.removeEventListener('sg:navigated', reloadCounter));

  const nodes = IDS
    .map((id) => document.getElementById(id))
    .filter((el): el is HTMLElement => !!el);
  if (!nodes.length) return;

  type Cache = { uv: string; pv: string; t: number };
  let cached: Cache | null = null;
  try { cached = JSON.parse(localStorage.getItem(KEY) ?? 'null'); } catch { /* 损坏即忽略 */ }
  if (cached) {
    const values = [cached.uv, cached.pv];
    nodes.forEach((el, i) => {
      const v = values[i];
      if (v && el.textContent?.trim() === '—') {
        el.textContent = v;
        el.title = `上次记录于 ${new Date(cached.t).toLocaleString()}`;
      }
    });
  }

  let tries = 0;
  const timer = window.setInterval(() => {
    tries += 1;
    const uv = document.getElementById(IDS[0])?.textContent?.trim() ?? '';
    const pv = document.getElementById(IDS[1])?.textContent?.trim() ?? '';
    if (uv !== '' && pv !== '' && uv !== '—' && pv !== '—') {
      try { localStorage.setItem(KEY, JSON.stringify({ uv, pv, t: Date.now() })); } catch { /* 隐私模式忽略 */ }
      nodes.forEach((el) => el.removeAttribute('title'));
      window.clearInterval(timer);
    } else if (tries > 36) {
      /* 18 秒没等到就放弃：保持缓存回显或「—」占位 */
      window.clearInterval(timer);
    }
  }, 500);
  onPageDispose(() => window.clearInterval(timer));
}
