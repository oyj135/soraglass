/**
 * 首页轮播（封面轮播 + 碎语轮播）。
 * DOM 契约：[data-sg-carousel] / [data-sg-slide] / 可选 [data-sg-dots]，
 * [data-sg-interval] 缺省 6000ms，首页主封面使用 5000ms。
 *
 * 自动播放保留源站 wait 节奏：每段淡出 / 淡入 800ms。
 * 第一阶段确认的桌面调整（>=1024px）：暂停 / 继续、悬停 / 焦点暂停、
 * 手动切换每段 200ms、方向键 / Home / End；不新增触摸手势。
 */
import { reducedMotion } from './core';

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
  let hovered = desktop.matches && host.matches(':hover');
  let focused = desktop.matches && host.contains(document.activeElement);
  let userPaused = false;
  // 「继续」仅豁免当前暂停按钮，不允许扩散到文章链接或其他指示器。
  let resumeOnToggle = false;

  const dots: HTMLButtonElement[] = [];
  let progressFill: HTMLElement | null = null;
  let progressCount: HTMLElement | null = null;
  let progressBtn: HTMLButtonElement | null = null;
  let toggle: HTMLButtonElement | null = null;

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

  if (dotsHost) {
    toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'sg-carousel-toggle';
    toggle.addEventListener('click', () => {
      userPaused = !userPaused;
      resumeOnToggle = !userPaused;
      paintToggle();
      restart();
    });
    toggle.addEventListener('mouseleave', (event) => {
      if (event.relatedTarget instanceof Node && host.contains(event.relatedTarget)) {
        resumeOnToggle = false;
        restart();
      }
    });
    dotsHost.appendChild(toggle);
  }

  const paintToggle = (): void => {
    if (!toggle) return;
    const hide = !desktop.matches || reducedMotion();
    if (hide && document.activeElement === toggle) {
      (dots[requested] ?? progressBtn)?.focus({ preventScroll: true });
    }
    toggle.hidden = hide;
    toggle.setAttribute('aria-pressed', String(userPaused));
    const label = userPaused ? '继续自动轮播' : '暂停自动轮播';
    toggle.setAttribute('aria-label', label);
    toggle.title = label;
    const icon = userPaused ? '<path d="m9 5 10 7-10 7V5z"/>' : '<path d="M9 5v14M15 5v14"/>';
    toggle.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${icon}</svg>`;
  };
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
    const control = dots[target] ?? progressBtn ?? (toggle?.hidden ? null : toggle);
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
    const quick = manual && desktop.matches;
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
    pending = window.setTimeout(() => {
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
    const hoverPause = hovered && !(resumeOnToggle && toggle?.matches(':hover'));
    const focusPause = focused && !(resumeOnToggle && document.activeElement === toggle);
    if (document.hidden || userPaused || (desktop.matches && (hoverPause || focusPause)) || reducedMotion()) return;
    timer = window.setTimeout(() => {
      timer = 0;
      show(requested + 1);
      restart();
    }, interval);
  }

  function manualMove(next: number): void {
    resumeOnToggle = false;
    show(next, true);
    restart();
  }

  host.addEventListener('mouseenter', () => {
    if (!desktop.matches) return;
    hovered = true;
    resumeOnToggle = false;
    restart();
  });
  host.addEventListener('mouseleave', () => {
    hovered = false;
    if (!focused) resumeOnToggle = false;
    restart();
  });
  host.addEventListener('focusin', (event) => {
    if (!desktop.matches) return;
    focused = true;
    if (event.target !== toggle || !(event.relatedTarget instanceof Node) || !host.contains(event.relatedTarget)) {
      resumeOnToggle = false;
    }
    restart();
  });
  host.addEventListener('focusout', (event) => {
    if (event.relatedTarget instanceof Node && host.contains(event.relatedTarget)) return;
    focused = false;
    if (!hovered) resumeOnToggle = false;
    restart();
  });
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
  document.addEventListener('visibilitychange', restart);
  desktop.addEventListener('change', () => {
    hovered = desktop.matches && host.matches(':hover');
    focused = desktop.matches && host.contains(document.activeElement);
    resumeOnToggle = false;
    if (!desktop.matches) host.classList.remove('is-manual');
    if (pending) jump(requested);
    paintToggle();
    restart();
  });
  motion.addEventListener('change', () => {
    if (reducedMotion()) jump(requested);
    paintToggle();
    restart();
  });

  jump(index);
  paintToggle();
  restart();
}

export function initCarousels(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-carousel]').forEach(setupCarousel);
}
