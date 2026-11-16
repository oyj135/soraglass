import { pageOn, pageFrame } from './lifecycle';
/**
 * 首页首屏交互（2026-11-16，参考 Fomalhaut 滚动导航）：
 *   DOM 契约（partials/home-front.php / side-countdown.php）：
 *     [data-sg-front]          100vh 首屏容器
 *     [data-sg-front-next]     向下滚动按钮
 *     [data-sg-nav]            顶栏（core.ts 已在管 is-hidden，这里只加透明态）
 *     [data-sg-countdown]      春节倒计时卡（可选）
 *
 * 全部按挂点自判存在，缺失就空跑；只作用于首页，不碰其它页面导航。
 */

/** 首屏滚动：顶部时导航透明+白字，离开首屏后恢复实底（由 CSS 默认样式负责）。 */
function initFrontNav(): void {
  const front = document.querySelector<HTMLElement>('[data-sg-front]');
  const nav = document.querySelector<HTMLElement>('[data-sg-nav]');
  if (!front || !nav) return;

  let ticking = false;
  const sync = (): void => {
    const threshold = Math.max(80, front.offsetHeight - nav.offsetHeight - 24);
    nav.classList.toggle('is-front', window.scrollY < threshold);
    ticking = false;
  };
  pageOn(window, 'scroll', () => {
    if (ticking) return;
    ticking = true;
    pageFrame(sync);
  }, { passive: true });
  sync();

  // 向下按钮：平滑滚到文章列表区（没有则滚到正文顶部）
  const next = front.querySelector<HTMLElement>('[data-sg-front-next]');
  const target = document.querySelector<HTMLElement>('#sg-home-content') ?? document.querySelector<HTMLElement>('.sg-home-body');
  next?.addEventListener('click', () => {
    if (!target) return;
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
}

/** 春节倒计时：按本地今天刷新天数与进度（服务端已渲染首帧，这里只在跨天时更新）。 */
function initCountdown(): void {
  const card = document.querySelector<HTMLElement>('[data-sg-countdown]');
  if (!card) return;
  const targetRaw = card.dataset.sgCdTarget;
  if (!targetRaw) return;
  const target = new Date(targetRaw + 'T00:00:00');
  if (Number.isNaN(target.getTime())) return;

  const daysEl = card.querySelector<HTMLElement>('[data-sg-cd-days]');
  const refresh = (): void => {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const diff = Math.max(0, Math.ceil((target.getTime() - today.getTime()) / 86400000));
    if (daysEl) daysEl.textContent = String(diff);

    const yearStart = new Date(now.getFullYear(), 0, 1);
    const yearEnd = new Date(now.getFullYear() + 1, 0, 1);
    const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const weekPct = Math.round((now.getDay() / 7) * 100);
    const pcts = [
      Math.round(((today.getTime() - yearStart.getTime()) / (yearEnd.getTime() - yearStart.getTime())) * 100),
      Math.round(((today.getTime() - new Date(now.getFullYear(), now.getMonth(), 1).getTime()) / (monthEnd.getTime() - new Date(now.getFullYear(), now.getMonth(), 1).getTime())) * 100),
      weekPct,
    ];
    const bars = card.querySelectorAll<HTMLElement>('.sg-hcd-row');
    bars.forEach((row, i) => {
      const fill = row.querySelector<HTMLElement>('.sg-hcd-bar i');
      const label = row.querySelector<HTMLElement>('em');
      if (fill) fill.style.width = pcts[i] + '%';
      if (label) label.textContent = pcts[i] + '%';
    });
  };
  refresh();
}

export function initHome(): void {
  initFrontNav();
  initCountdown();
}
