/**
 * 首页轮播（封面轮播 + 杂谈轮播）。
 * DOM 契约：容器 [data-sg-carousel]，子项 [data-sg-slide]，可选圆点容器 [data-sg-dots]，
 * 可选节奏 [data-sg-interval]（毫秒，缺省 6000）。
 *
 * 切换方式对齐源站 framer-motion 的 `AnimatePresence mode="wait"`：
 * 先让当前这张淡出（0.8s），退场播完再让下一张淡入（0.8s），两张图不会同时可见。
 * 早期版本用 CSS 交叉淡入，观感是两层图糊在一起，和演示站不是一回事。
 */
import { reducedMotion } from './core';

const DEFAULT_INTERVAL = 6000;
/** 与源站 transition={{ duration: 0.8 }} 对齐；CSS 里的 opacity 过渡必须同值 */
const FADE = 800;

function setupCarousel(host: HTMLElement): void {
  const slides = Array.from(host.querySelectorAll<HTMLElement>('[data-sg-slide]'));
  const dotsHost = host.querySelector<HTMLElement>('[data-sg-dots]');
  const interval = Number(host.dataset.sgInterval ?? DEFAULT_INTERVAL) || DEFAULT_INTERVAL;

  if (slides.length < 2) {
    // 只有一张时也要把 is-active 补上：CSS 里 .sg-post-slide / .sg-chatter-slide
    // 默认 opacity:0，去掉轮播逻辑后会剩一张谁也看不见的空卡。
    slides[0]?.classList.add('is-active');
    if (dotsHost) dotsHost.hidden = true;
    return;
  }

  let index = slides.findIndex((slide) => slide.classList.contains('is-active'));
  if (index < 0) index = 0;

  let pending = 0;

  const dots: HTMLButtonElement[] = [];
  if (dotsHost) {
    slides.forEach((_, i) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.className = 'sg-dot';
      dot.setAttribute('aria-label', `第 ${i + 1} 张`);
      dot.addEventListener('click', () => {
        show(i);
        restart();
      });
      dotsHost.appendChild(dot);
      dots.push(dot);
    });
  }

  const paint = (): void => {
    slides.forEach((slide, i) => slide.classList.toggle('is-active', i === index));
    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
  };

  /** 直接切换（首屏、reduced-motion 走这里，不要留 0.8s 的空白） */
  function jump(next: number): void {
    window.clearTimeout(pending);
    pending = 0;
    index = (next + slides.length) % slides.length;
    paint();
  }

  /** 源站节奏：淡出 → 等退场播完 → 淡入下一张 */
  function show(next: number): void {
    const target = (next + slides.length) % slides.length;
    if (target === index && pending === 0) return;
    if (reducedMotion()) {
      jump(target);
      return;
    }
    window.clearTimeout(pending);
    index = target;
    slides.forEach((slide) => slide.classList.remove('is-active'));
    // 圆点立刻跟手：源站 setCurrentIndex 也是即时生效，动的是图不是点
    dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
    pending = window.setTimeout(() => {
      pending = 0;
      paint();
    }, FADE);
  }

  jump(index);

  let timer = 0;

  const stop = (): void => {
    if (timer) {
      window.clearInterval(timer);
      timer = 0;
    }
  };

  function restart(): void {
    stop();
    if (reducedMotion()) return;
    timer = window.setInterval(() => {
      if (document.hidden) return;
      show(index + 1);
    }, interval);
  }

  // 源站没有「鼠标悬停就暂停」：鼠标停在卡上照样按节奏换。
  // 只在标签页切到后台时停表，避免看不见的动画白跑。
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else restart();
  });

  restart();
}

export function initCarousels(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-carousel]').forEach(setupCarousel);
}
