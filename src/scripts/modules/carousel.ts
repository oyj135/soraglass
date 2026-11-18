import { pageOn,pageTimeout } from './lifecycle';
/**
 * 首页轮播（封面轮播 + 碎语轮播）。
 * DOM 契约：[data-sg-carousel] / [data-sg-slide] / 可选 [data-sg-dots]，
 * [data-sg-interval] 缺省 6000ms，首页文章轮播使用 8000ms（第十七轮降速）。
 *
 * 自动播放保留源站 wait 节奏：每段淡出 / 淡入 800ms。
 * 桌面调整（>=1024px）：悬停 / 焦点临时暂停、手动切换每段 200ms、
 * 方向键 / Home / End；不新增触摸手势，也不提供暂停按钮。
 */
import { reducedMotion } from './runtime';

const DEFAULT_INTERVAL = 6000;
const FADE = 800;
/** 与 components.css 的桌面 .is-manual 过渡保持一致。 */
const MANUAL_FADE = 200;
/** <=6 张用圆点，更多用「进度轨 + 当前 / 总数」。 */
const DOTS_MAX = 6;

function setupCarousel(host: HTMLElement): void {
  const slides = Array.from(host.querySelectorAll<HTMLElement>('[data-sg-slide]'));
  const dotsHost = host.querySelector<HTMLElement>('[data-sg-dots]');
  const interval = Number(host.dataset.sgInterval ?? DEFAULT_INTERVAL) || DEFAULT_INTERVAL;

  if (slides.length < 2) {
    slides[0]?.classList.add('is-active');
    if (slides[0]) {
      slides[0].inert = false;
      slides[0].setAttribute('aria-hidden', 'false');
    }
    if (dotsHost) dotsHost.hidden = true;
    return;
  }

  const desktop = window.matchMedia('(min-width: 1024px)');
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let index = slides.findIndex((slide) => slide.classList.contains('is-active'));
  if (index < 0) index = 0;
  // 已显示索引与待切入目标分开：连点只改目标，不反复延长退场。
  let requested = index;
  let pending = 0;
  let timer = 0;

  const dots: HTMLButtonElement[] = [];
  let progressFill: HTMLElement | null = null;
  let progressCount: HTMLElement | null = null;
  let progressBtn: HTMLButtonElement | null = null;

  host.setAttribute('role', 'region');
  host.setAttribute('aria-roledescription', '轮播');
  if (!host.hasAttribute('aria-label')) {
    host.setAttribute('aria-label', host.classList.contains('sg-chatter') ? '碎语轮播' : '最新文章轮播');
  }

  if (dotsHost && slides.length > DOTS_MAX) {
    dotsHost.classList.add('is-progress');
    progressBtn = document.createElement('button');
    progressBtn.type = 'button';
    progressBtn.className = 'sg-progress';
    progressBtn.innerHTML = '<span class="sg-progress-track"><i></i></span><span class="sg-progress-count"></span>';
    progressBtn.addEventListener('click', () => manualMove(requested + 1));
    dotsHost.appendChild(progressBtn);
    progressFill = progressBtn.querySelector<HTMLElement>('i');
    progressCount = progressBtn.querySelector<HTMLElement>('.sg-progress-count');
  } else if (dotsHost) {
    slides.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'sg-dot';
      dot.setAttribute('aria-label', `第 ${i + 1} 张，共 ${slides.length} 张`);
      dot.addEventListener('click', () => manualMove(i));
      dotsHost.appendChild(dot);
      dots.push(dot);
    });
  }

  const paintIndicator = (): void => {
    dots.forEach((dot, i) => {
      const on = i === requested;
      dot.classList.toggle('is-active', on);
      if (on) dot.setAttribute('aria-current', 'true');
      else dot.removeAttribute('aria-current');
    });
    if (progressFill) progressFill.style.transform = `scaleX(${(requested + 1) / slides.length})`;
    if (progressCount) progressCount.textContent = `${requested + 1}/${slides.length}`;
    progressBtn?.setAttribute('aria-label', `第 ${requested + 1} 张，共 ${slides.length} 张，点击看下一张`);
  };
  const paintSlides = (visible: boolean): void => {
    slides.forEach((slide, i) => {
      const on = visible && i === index;
      slide.classList.toggle('is-active', on);
      slide.inert = !on;
      slide.setAttribute('aria-hidden', String(!on));
    });
  };
  const paint = (): void => {
    index = requested;
    paintSlides(true);
    paintIndicator();
  };
  const focusOutsideSlides = (target: number): void => {
    if (!slides.some((slide) => slide.contains(document.activeElement))) return;
    const control = dots[target] ?? progressBtn;
    if (control) control.focus({ preventScroll: true });
    else {
      if (!host.hasAttribute('tabindex')) host.tabIndex = -1;
      host.focus({ preventScroll: true });
    }
  };

  function jump(next: number): void {
    window.clearTimeout(pending);
    pending = 0;
    const target = (next + slides.length) % slides.length;
    if (target !== index) focusOutsideSlides(target);
    requested = target;
    paint();
  }

  function show(next: number, manual = false): void {
    const target = (next + slides.length) % slides.length;
    if (!pending && target === index) return;
    requested = target;
    paintIndicator();
    /* 第十八轮：手动切换与自动播放同节奏（去掉 .2s 快切，观感自然） */
    const quick = false;
    if (reducedMotion()) {
      jump(target);
      return;
    }
    if (pending) {
      // 连点 / 长按只更新最终目标，当前退场期限保持不变，避免持续空白。
      if (quick && !host.classList.contains('is-manual')) {
        host.classList.add('is-manual');
        // 自动退场已启动 800ms CSS transition；改 duration 不会缩短它。
        // 先明确结束旧 opacity 退场，再以 200ms 入场，不叠出两层图。
        slides[index].getAnimations().forEach((animation) => {
          if ('transitionProperty' in animation && animation.transitionProperty === 'opacity') animation.finish();
        });
        window.clearTimeout(pending);
        pending = 0;
        paint();
      }
      return;
    }
    host.classList.toggle('is-manual', quick);
    focusOutsideSlides(target);
    paintSlides(false);
    pending = pageTimeout(() => {
      pending = 0;
      paint();
    }, quick ? MANUAL_FADE : FADE);
  }

  function stop(): void {
    window.clearTimeout(timer);
    timer = 0;
  }

  function restart(): void {
    stop();
    /* 第十八轮：悬停/焦点不再停表（用户要求鼠标在卡片内时也能自动切换）；
       只保留后台标签页与减少动效两道闸。 */
    if (document.hidden || reducedMotion()) return;
    timer = pageTimeout(() => {
      timer = 0;
      show(requested + 1);
      restart();
    }, interval);
  }

  function manualMove(next: number): void {
    show(next, true);
    restart();
  }

  host.addEventListener('keydown', (event) => {
    if (!desktop.matches || event.altKey || event.ctrlKey || event.metaKey) return;
    const target = event.target;
    if (target instanceof HTMLElement && target.closest('input, textarea, select, [contenteditable]')) return;
    let next = requested;
    switch (event.key) {
      case 'ArrowLeft': next = requested - 1; break;
      case 'ArrowRight': next = requested + 1; break;
      case 'Home': next = 0; break;
      case 'End': next = slides.length - 1; break;
      default: return;
    }
    event.preventDefault();
    manualMove(next);
  });
  /* 滚轮切换（2026-11-18，对齐源站 swiper mousewheel: true）：指针在卡内时
     滚轮切换幻灯并拦住页面滚动；阈值 + 冷却防触控板惯性连跳；单张不拦截。 */
  let wheelLock = 0;
  host.addEventListener('wheel', (event) => {
    if (slides.length < 2 || Math.abs(event.deltaY) < 24) return;
    event.preventDefault();
    const now = Date.now();
    if (now - wheelLock < 450) return;
    wheelLock = now;
    manualMove(requested + (event.deltaY > 0 ? 1 : -1));
  }, { passive: false });

  pageOn(document, 'visibilitychange', restart);
  pageOn(desktop, 'change', () => {
    if (!desktop.matches) host.classList.remove('is-manual');
    if (pending) jump(requested);
    restart();
  });
  pageOn(motion, 'change', () => {
    if (reducedMotion()) jump(requested);
    restart();
  });

  jump(index);
  restart();
}

export function initCarousels(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-carousel]').forEach(setupCarousel);
}
