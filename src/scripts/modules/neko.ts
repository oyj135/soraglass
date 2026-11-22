import { pageOn,pageFrame,pageInterval,onPageDispose } from './lifecycle';
import { reducedMotion } from './runtime';
/**
 * 右缘小猫滚动进度（2026-11-16 第三轮，对齐 Fomalhaut cursor.js 的 nekoScroll）：
 *   [data-sg-neko-rope]  右缘竖绳，高度 = 滚动进度 × 0.9 × 视口高
 *   [data-sg-neko]       64px 墨龙挂绳尾（top = 绳高 - 50）， scrollTop>0 才现身（昼夜双图 CSS 切换）
 *   [data-sg-neko-msg]   气泡：悬停小猫或滚到页底时显示当前时间
 * 点绳或点小猫回顶部；小猫 pointer-events:none，点击命中下层交互元素时不拦截。
 * ≤768px 由 CSS 隐藏，模块直接空跑。
 */

const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [onclick], [role="button"], [role="link"], [data-sg-rightside]';

export function initNeko(): void {
  const rope = document.querySelector<HTMLElement>('[data-sg-neko-rope]');
  const neko = document.querySelector<HTMLElement>('[data-sg-neko]');
  const msg = document.querySelector<HTMLElement>('[data-sg-neko-msg]');
  if (!rope || !neko) return;
  if (!window.matchMedia('(min-width: 769px)').matches) return;

  let progress = 0;
  let ropeH = 0;
  const sync = (): void => {
    const doc = document.documentElement;
    const range = doc.scrollHeight - window.innerHeight;
    progress = range > 0 ? Math.min(1, Math.max(0, window.scrollY / range)) : 0;
    ropeH = progress * 0.9 * window.innerHeight;
    rope.style.height = ropeH + 'px';
    neko.style.top = ropeH - 50 + 'px';
    const on = window.scrollY > 1;
    rope.classList.toggle('is-on', on);
    neko.classList.toggle('is-on', on);
    neko.classList.toggle('is-bottom', progress > 0.985);
  };
  let ticking = false;
  const queue = (): void => {
    if (ticking) return;
    ticking = true;
    pageFrame(() => { ticking = false; sync(); });
  };
  pageOn(window, 'scroll', queue, { passive: true });
  pageOn(window, 'resize', queue, { passive: true });
  const ro = new ResizeObserver(queue);
  ro.observe(document.body);
  onPageDispose(() => ro.disconnect());
  sync();

  const toTop = (): void => window.scrollTo({ top: 0, behavior: reducedMotion() ? 'auto' : 'smooth' });
  pageOn(rope, 'click', toTop);
  // 小猫不拦截指针：命中测试矩形，下层是链接/按钮等则放行，否则回顶部
  pageOn(document, 'click', (e: MouseEvent) => {
    if (!neko.classList.contains('is-on')) return;
    const r = neko.getBoundingClientRect();
    if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) return;
    const under = document.elementFromPoint(e.clientX, e.clientY);
    if (under && under.closest(INTERACTIVE)) return;
    toTop();
  });

  if (msg) {
    const stamp = (): void => {
      const d = new Date();
      const p = (n: number): string => String(n).padStart(2, '0');
      msg.textContent = `${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
    };
    stamp();
    pageInterval(stamp, 1000);
    let hoverTick = false;
    pageOn(document, 'pointermove', (e: PointerEvent) => {
      if (hoverTick) return;
      hoverTick = true;
      pageFrame(() => {
        hoverTick = false;
        const r = neko.getBoundingClientRect();
        const hit = e.clientX >= r.left && e.clientX <= r.right && e.clientY >= r.top && e.clientY <= r.bottom;
        neko.classList.toggle('is-hover', hit && neko.classList.contains('is-on'));
      });
    }, { passive: true });
  }
}