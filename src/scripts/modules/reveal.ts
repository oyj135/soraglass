/**
 * 滚动显现 + 交错入场 + 数字递增（第十六轮 P0，源自「16 种 UI 动效」适配清单）。
 *
 * 三个能力合在同一个 IntersectionObserver 里：
 *  · Reveal —— .sg-feature-card / .sg-hcard 进入视口后从下方 24px 淡入，一次性
 *  · Stagger —— 同一批进入的元素按视口内 top 排序依次入场，55ms 步进、上限 10 步
 *  · Count-up —— 侧栏纯数字（KPI / 倒计时天数 / 年度产出）入视口后 800ms 滚到目标值
 *
 * 约束：
 *  · 隐藏态只挂在 html.sg-ready 下（head 内联脚本同步打标）——JS 禁用/爬虫/无 JS
 *    时内容始终可见；prefers-reduced-motion 下整个模块直接跳过，元素保持终态；
 *  · 数字终值由服务端渲染（SEO 友好），动画只是表达层，播完写回精确目标值；
 *  · 与春节倒计时的跨天刷新（home.ts）不冲突：动画只播一次，跨天直接写终值。
 */
import { onPageDispose } from './lifecycle';
import { reducedMotion } from './runtime';

const REVEAL_SELECTOR = '.sg-feature-card, .sg-hcard';
const COUNTUP_SELECTOR = '.sg-hinfo-num, .sg-hcd-num, .sg-hinfo-bar-n';
const STAGGER_MAX = 10;

/** 数字从 0 递增到目标值（目标值取服务端渲染的文本），ease-out 三次曲线 */
function runCountUp(el: HTMLElement): void {
  if (el.dataset.sgCountDone === '1') return;
  const target = Number.parseInt((el.textContent ?? '').trim(), 10);
  if (!Number.isFinite(target) || target <= 0) return;
  el.dataset.sgCountDone = '1';

  const duration = 800;
  const t0 = performance.now();
  const tick = (now: number): void => {
    const p = Math.min(1, (now - t0) / duration);
    const eased = 1 - Math.pow(1 - p, 3);
    el.textContent = String(Math.round(target * eased));
    if (p < 1) {
      window.requestAnimationFrame(tick);
    } else {
      el.textContent = String(target);
    }
  };
  window.requestAnimationFrame(tick);
}

export function initReveal(): void {
  if (reducedMotion() || !('IntersectionObserver' in window)) return;

  const nodes = Array.from(document.querySelectorAll<HTMLElement>(REVEAL_SELECTOR));
  const counters = Array.from(document.querySelectorAll<HTMLElement>(COUNTUP_SELECTOR));
  if (!nodes.length && !counters.length) return;

  for (const el of nodes) {
    el.classList.add('sg-reveal');
    el.style.setProperty('--stagger-i', '0');
  }

  const observer = new IntersectionObserver((entries) => {
    /* 同一批进入的元素按视口内 top 排序后依次入场（交错步进走 CSS transition-delay） */
    const batch = entries
      .filter((entry) => entry.isIntersecting)
      .map((entry) => entry.target as HTMLElement)
      .sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);

    batch.forEach((el, i) => {
      el.style.setProperty('--stagger-i', String(Math.min(i, STAGGER_MAX)));
      el.classList.add('is-revealed');
      observer.unobserve(el);
      /* 卡片入场时同步触发卡内的数字递增 */
      el.querySelectorAll<HTMLElement>(COUNTUP_SELECTOR).forEach(runCountUp);
      if (el.matches(COUNTUP_SELECTOR)) runCountUp(el);
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

  nodes.forEach((el) => observer.observe(el));
  /* 不在任何 reveal 单元里的独立数字单独观察 */
  counters
    .filter((el) => !el.closest(REVEAL_SELECTOR))
    .forEach((el) => observer.observe(el));

  onPageDispose(() => observer.disconnect());
}
