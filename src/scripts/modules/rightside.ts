import { pageOn,pageFrame,pageTimeout,onPageDispose } from './lifecycle';
import { reducedMotion } from './runtime';
/**
 * 右侧悬浮按钮组 + 顶部胶囊加载条（对齐 Fomalhaut rightside / reading.js / pace）。
 * DOM 契约（partials/rightside.php）：
 *   [data-sg-pace]               顶部胶囊，内部 .sg-pace-progress 为渐变填充
 *   [data-sg-rightside-config]   设置齿轮，开合上方隐藏组
 *   [data-sg-go-up]              回顶：svg 箭头 + [data-sg-go-up-percent] 百分比双节点
 *   [data-sg-go-down]            直达底部
 * 百分比规则与参考站一致：<95% 显示数字，≥95% 显示箭头；hover 显示箭头由 CSS 处理。
 * 滚动回调 rAF 合帧、数字不变不写 DOM，避免一帧几十次强制布局。
 */

function initGoUp(): void {
  const btn = document.querySelector<HTMLButtonElement>('[data-sg-go-up]');
  if (!btn) return;
  const icon = btn.querySelector<SVGElement>('svg');
  const percent = btn.querySelector<HTMLElement>('[data-sg-go-up-percent]');
  if (!icon || !percent) return;

  let last = -1;
  let top = false;
  let ticking = false;

  const update = (): void => {
    ticking = false;
    const doc = document.documentElement;
    const scrolled = window.scrollY || doc.scrollTop;
    const total = Math.max(doc.scrollHeight, document.body.scrollHeight) - doc.clientHeight;
    const result = total > 0 ? Math.round((scrolled / total) * 100) : 0;
    if (result < 95) {
      if (!top && result === last) return;
      icon.style.display = 'none';
      percent.style.display = 'block';
      percent.innerHTML = result + '<span>%</span>';
      last = result;
      top = false;
    } else {
      if (top) return;
      percent.style.display = 'none';
      icon.style.display = 'block';
      top = true;
    }
  };

  pageOn(window, 'scroll', () => {
    if (ticking) return;
    ticking = true;
    pageFrame(update);
  }, { passive: true });
  pageOn(window, 'resize', update, { passive: true });
  update();

  pageOn(btn, 'click', () => {
    window.scrollTo({ top: 0, behavior: reducedMotion() ? 'instant' : 'smooth' });
  });
}

function initGoDown(): void {
  document.querySelectorAll<HTMLButtonElement>('[data-sg-go-down]').forEach(btn => pageOn(btn, 'click', () => {
    window.scrollTo({
      top: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
      behavior: reducedMotion() ? 'instant' : 'smooth',
    });
  }));
}

function initConfig(): void {
  const btn = document.querySelector<HTMLButtonElement>('[data-sg-rightside-config]');
  const group = document.querySelector<HTMLElement>('[data-sg-rightside-hidden]');
  if (!btn || !group) return;

  const setOpen = (open: boolean): void => {
    if (!open && group.contains(document.activeElement)) btn.focus({ preventScroll: true });
    group.classList.toggle('is-open', open);
    btn.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? '收起设置' : '展开设置');
  };

  pageOn(btn, 'click', (event) => {
    event.stopPropagation();
    setOpen(!group.classList.contains('is-open'));
  });
  pageOn(document, 'click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-sg-rightside]')) setOpen(false);
  });
  pageOn(document, 'keydown', (event) => {
    if (event.key === 'Escape') setOpen(false);
  });
}

/** 顶部胶囊：加载期渐进到 ~90%，load 后补满淡出；reduced-motion 直接跳过。 */
function initPace(): void {
  const pace = document.querySelector<HTMLElement>('[data-sg-pace]');
  if (!pace) return;
  const fill = pace.querySelector<HTMLElement>('.sg-pace-progress');
  if (!fill || reducedMotion()) {
    pace.remove();
    return;
  }

  let value = 0;
  let done = false;
  let timer = 0;

  const render = (): void => {
    fill.style.transform = `translateX(${value - 100}%)`;
  };
  const finish = (): void => {
    if (done) return;
    done = true;
    window.clearTimeout(timer);
    value = 100;
    render();
    pageTimeout(() => {
      pace.classList.add('is-done');
      pageTimeout(() => pace.remove(), 400);
    }, 200);
  };

  pace.classList.add('is-active');
  render();
  const tick = (): void => {
    if (done) return;
    value = Math.min(90, value + (90 - value) * 0.08 + 0.5);
    render();
    timer = pageTimeout(tick, 200);
  };
  timer = pageTimeout(tick, 200);

  if (document.readyState === 'complete') {
    finish();
  } else {
    pageOn(window, 'load', finish, { once: true });
    // 兜底：个别外链图挂住 load 时，8s 后也收尾，不让胶囊常驻
    pageTimeout(finish, 8000);
  }
  onPageDispose(() => window.clearTimeout(timer));
}

export function initRightside(): void {
  initGoUp();
  initGoDown();
  initConfig();
  initPace();
}
