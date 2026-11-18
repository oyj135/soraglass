import { pageOn,pageTimeout,onPageDispose } from './lifecycle';
/**
 * AI 日报卡片的背景轮播。
 *
 * DOM 契约（全部由 partials/briefing.php 服务端渲染）：
 *   容器  [data-sg-briefing-bg]   带 data-sg-bg-source / data-sg-bg-count / data-sg-bg-interval
 *   单图  [data-sg-bg-index="<i>"]，当前张带 is-active
 *
 * 行为约束：
 *   1. 两张及以上才自动播放；单张就是静态背景，与旧行为完全一致。
 *   2. 索引取模循环，首尾衔接不出现空白帧 —— img 都是绝对定位叠放，只切 opacity。
 *   3. 主题切换（html.dark 翻转）不换图、不重置索引，只把当前张的停留时间重新计一轮，
 *      避免「刚切完主题背景就被切走」。日夜共用同一组图。
 *   4. 翻期事件 sg:briefing-issue 会跳到对应序号，并暂停自动轮播 12 秒。
 *   5. 离屏 / 页面隐藏 / 鼠标悬停 / prefers-reduced-motion 一律停表，不白烧性能。
 */

const DEFAULT_INTERVAL = 7000;
const HOLD_AFTER_ISSUE = 12000;

export function initBriefingBg(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-briefing-bg]');
  if (!root) return;

  const covers = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-bg-index]'));
  if (covers.length === 0) return;

  const card = root.closest<HTMLElement>('[data-sg-briefing]') ?? root;
  const parsed = Number(root.dataset.sgBgInterval ?? '');
  const interval = Number.isFinite(parsed) && parsed >= 2000 ? parsed : DEFAULT_INTERVAL;

  let index = Math.max(0, covers.findIndex((cover) => cover.classList.contains('is-active')));
  let timer = 0;
  let holdUntil = 0;
  let visible = true;
  let hovering = false;
  let lastDark = document.documentElement.classList.contains('dark');

  /** 预热下一张：懒加载的图在切换瞬间才开始请求会出现一帧空白 */
  const warm = (pos: number): void => {
    if (covers.length < 2) return;
    const next = covers[((pos % covers.length) + covers.length) % covers.length] as HTMLImageElement | undefined;
    const src = next?.currentSrc || next?.src;
    if (src) {
      const preload = new Image();
      preload.src = src;
    }
  };

  const paint = (next: number, force = false): void => {
    const total = covers.length;
    if (total === 0) return;
    const target = ((next % total) + total) % total;
    if (!force && target === index) return;
    index = target;
    covers.forEach((cover, i) => cover.classList.toggle('is-active', i === index));
    warm(index + 1);
  };

  /* 注意：prefers-reduced-motion 只由 CSS 用来关掉推镜和淡入时长，
     这里不参与「播不播」的判断 —— 否则系统一开「减少动画」，背景就永久停在第一张，
     用户会以为功能坏了。淡入淡出不构成前庭负担，缩放动画仍会被 CSS 媒体查询去掉。 */
  const canPlay = (): boolean =>
    covers.length > 1 && visible && !hovering && !document.hidden && Date.now() >= holdUntil;

  /** 递归 setTimeout 而不是 setInterval：翻期暂停后能立刻按新的等待时长重排 */
  const tick = (): void => {
    window.clearTimeout(timer);
    if (covers.length < 2) return;
    const delay = Date.now() < holdUntil ? Math.max(600, holdUntil - Date.now()) : interval;
    timer = pageTimeout(() => {
      if (canPlay()) paint(index + 1);
      tick();
    }, delay);
  };

  /* 翻期：跳到对应序号。自定义图组可能比期数少，取模保证永远落在有效范围内。
     reason=auto 是日报自己的 8 秒自动切期（以及弹窗内部重绘），它与背景轮播无关，
     直接忽略 —— 否则每 8 秒一次的「暂停 12 秒」会让背景永远轮不起来。 */
  card.addEventListener('sg:briefing-issue', (event) => {
    const detail = (event as CustomEvent<{ index?: number; reason?: string }>).detail;
    if (detail?.reason === 'auto') return;
    if (detail?.reason !== 'init') holdUntil = Date.now() + HOLD_AFTER_ISSUE;
    paint(Number(detail?.index ?? 0));
    tick();
  });

  /* 主题切换：只重置停留时间，不换图、不动索引 —— 「保持一致」指的就是这个 */
  const themeObserver = new MutationObserver(() => {
    const dark = document.documentElement.classList.contains('dark');
    if (dark === lastDark) return;
    lastDark = dark;
    holdUntil = Date.now() + interval;
    tick();
  });
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  onPageDispose(() => themeObserver.disconnect());

  card.addEventListener('mouseenter', () => {
    hovering = true;
  });
  card.addEventListener('mouseleave', () => {
    hovering = false;
    tick();
  });

  pageOn(document, 'visibilitychange', () => {
    if (!document.hidden) tick();
  });

  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(
      (entries) => {
        visible = entries.some((entry) => entry.isIntersecting);
        tick();
      },
      { rootMargin: '120px' },
    );
    observer.observe(card);
    onPageDispose(() => observer.disconnect());
  }

  paint(index, true);
  tick();
}
