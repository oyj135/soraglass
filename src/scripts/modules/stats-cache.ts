/**
 * 访客数缓存回显（第十七轮修复「访客数 / 总访问量时显时不显」）。
 *
 * 根因：数字来自外部 vercount（busuanzi 镜像）异步脚本，网络抖动 / 被墙时
 * 占位「—」会一直挂着，观感就是「时显时不显」。
 * 方案：vercount 成功回写后把两个数字缓存进 localStorage；下次打开页面
 * 立即回显缓存值（带 title 说明时间），脚本随后到达时 vercount 会按原 id
 * 覆盖成新值、缓存同步更新。CDN 彻底失败时页面显示最近一次真实计数，
 * 不再出现空的「—」。首页侧栏之外没有挂点，自动空跑。
 */
import { onPageDispose } from './lifecycle';

const KEY = 'sg:visitor-cache';
const IDS = ['busuanzi_value_site_uv', 'busuanzi_value_site_pv'] as const;

export function initStatsCache(): void {
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
