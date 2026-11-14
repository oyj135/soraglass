/**
 * 光影画廊 · 视差相册（page-photowall.php）
 *
 * 视频里那种「照片墙」：一条通栏的带子，相纸按 --px-depth 分前后。
 * 三股位移叠在一起，都要乘各自的纵深（越「近」走得越快）：
 *
 *   1. 滚动 —— 区块中心相对视口中心的偏移，横向推（视差主体）
 *   2. 漂移 —— 极慢的匀速左移，停下来看也像在呼吸；hover 时降到 15%
 *   3. 摆动 + 跟手 —— 每张照片有自己的相位，慢慢左右荡；鼠标进带子再叠一点
 *
 * 横移超出带子就从另一侧接回来，带子永远没有空档（匀速漂移保证接回后
 * 间距不变，不会越漂越挤）。整个循环只在带子进入视口时跑。
 *
 * prefers-reduced-motion 下不动：直接按模板写好的位置静态排好。
 */
import { isSmallScreen, reducedMotion } from './core';

interface Print {
  el: HTMLElement;
  /** 模板里 left 解析成的像素值，接回时要拿它算基准 */
  base: number;
  width: number;
  depth: number;
  /** 每张照片的摆动相位（下标算出，不用随机数） */
  phase: number;
}

export function initParallax(): void {
  const stage = document.querySelector<HTMLElement>('[data-sg-px-stage]');
  if (!stage) return;

  const nodes = Array.from(stage.querySelectorAll<HTMLElement>('.sg-px-print'));
  if (!nodes.length) return;

  // 静态版：位置/倾角已经由模板和 CSS 排好，这里什么都不用做
  if (reducedMotion()) return;

  const small = isSmallScreen();
  const scrollAmp = small ? 64 : 150;
  const pointerAmp = small ? 0 : 22;
  const driftSpeed = small ? 6 : 12;
  const pad = 140;

  let prints: Print[] = [];
  let width = 0;
  let frame = 0;
  let last = 0;
  let visible = false;
  let hovered = false;

  let drift = 0;
  let speed = 1;
  let scrollP = 0;
  let scrollTarget = 0;
  let pointerX = 0;
  let pointerY = 0;
  let targetX = 0;
  let targetY = 0;

  const readScroll = (): void => {
    const rect = stage.getBoundingClientRect();
    // 带子中心在视口下方 → +1，上方 → -1
    const center = rect.top + rect.height / 2;
    scrollTarget = Math.max(-1.4, Math.min(1.4, (window.innerHeight / 2 - center) / window.innerHeight));
  };

  const place = (): void => {
    for (const print of prints) {
      const span = width + print.width + pad * 2;
      const min = -print.width - pad;
      const phase = (drift + Math.sin(performance.now() / 1000 * 0.16 + print.phase) * 18) * -1;
      const x = print.base + phase + (scrollP * scrollAmp + pointerX * pointerAmp) * print.depth;
      const wrapped = min + ((((x - min) % span) + span) % span);
      const y = scrollP * -14 * print.depth + pointerY * -6 * print.depth;
      print.el.style.transform = `translate3d(${(wrapped - print.base).toFixed(2)}px, ${y.toFixed(2)}px, 0)`;
    }
  };

  const tick = (now: number): void => {
    const dt = last ? Math.min(64, now - last) / 1000 : 0.016;
    last = now;

    // 帧率无关的缓动：120Hz 与 60Hz 上手感一致
    const ease = 1 - Math.pow(0.0015, dt);

    speed += ((hovered ? 0.15 : 1) - speed) * ease;
    drift += dt * speed * driftSpeed;
    scrollP += (scrollTarget - scrollP) * ease;
    pointerX += (targetX - pointerX) * ease;
    pointerY += (targetY - pointerY) * ease;

    place();
    frame = window.requestAnimationFrame(tick);
  };

  const start = (): void => {
    if (frame) return;
    last = 0;
    frame = window.requestAnimationFrame(tick);
  };

  const stop = (): void => {
    if (!frame) return;
    window.cancelAnimationFrame(frame);
    frame = 0;
  };

  const measure = (): void => {
    width = stage.clientWidth;
    prints = nodes.map((el, index) => ({
      el,
      base: parseFloat(window.getComputedStyle(el).left) || 0,
      width: el.offsetWidth,
      depth: parseFloat(window.getComputedStyle(el).getPropertyValue('--px-depth')) || 0.8,
      phase: index * 1.7,
    }));
    readScroll();
  };

  const onPointer = (event: PointerEvent): void => {
    if (small || event.pointerType !== 'mouse') return;
    const rect = stage.getBoundingClientRect();
    targetX = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
    targetY = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
  };

  stage.addEventListener('pointermove', onPointer, { passive: true });
  stage.addEventListener('pointerenter', () => { hovered = true; }, { passive: true });
  stage.addEventListener('pointerleave', () => {
    hovered = false;
    targetX = 0;
    targetY = 0;
  }, { passive: true });

  window.addEventListener('scroll', readScroll, { passive: true });
  window.addEventListener('resize', measure);

  /* 直接落在相册详情（?album=<id>，列表是 hidden）时量到的宽度全是 0，
     等列表重新露出来再量一次，不然相纸会挤成一条。 */
  if ('ResizeObserver' in window) {
    new ResizeObserver(() => measure()).observe(stage);
  }

  /* 相纸宽度现在由原图比例决定：图没下载完时量到的是占位宽度，
     每张图 decode 完补量一次，接回点才不会算错。 */
  for (const img of Array.from(stage.querySelectorAll<HTMLImageElement>('.sg-px-frame img'))) {
    if (!img.complete) img.addEventListener('load', measure, { once: true });
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else if (visible) start();
  });

  // 只在带子露头时跑动画；切到相册详情（列表 hidden）时自动停
  if ('IntersectionObserver' in window) {
    new IntersectionObserver((entries) => {
      visible = entries.some((entry) => entry.isIntersecting);
      if (visible) start();
      else stop();
    }, { rootMargin: '120px' }).observe(stage);
  } else {
    visible = true;
    start();
  }

  measure();
  start();
}
