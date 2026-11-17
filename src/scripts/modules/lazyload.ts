import { onPageDispose } from './lifecycle';

/**
 * 全站懒加载（2026-11-17，对齐 Fomalhaut post_lazyload.js + vanilla-lazyload 链路）。
 *
 * PHP 侧把真实地址写进 data-lazy-src，src 恒为 1px 透明 GIF；这里用
 * IntersectionObserver 在图片接近视口时才换装 —— 画廊一次几十张缩略图
 * 不再全部随页面下载，减少负载。加载完成加 .is-lazy-in 淡入（源站 coverIn 同观感）。
 * 动态插入的节点（画廊「加载更多」/ 搜索结果）由 MutationObserver 自动接管。
 */
export function initLazyload(): void {
  const swap = (img: HTMLImageElement): void => {
    const real = img.dataset.lazySrc ?? '';
    if (!real) return;
    img.addEventListener('load', () => img.classList.add('is-lazy-in'), { once: true });
    img.addEventListener('error', () => img.classList.remove('is-lazy-in'), { once: true });
    img.src = real;
    img.removeAttribute('data-lazy-src');
  };

  if (!('IntersectionObserver' in window)) {
    /* 老浏览器兜底：直接加载，不卡住内容 */
    document.querySelectorAll<HTMLImageElement>('img[data-lazy-src]').forEach(swap);
    return;
  }

  const io = new IntersectionObserver((entries, observer) => {
    for (const entry of entries) {
      if (!entry.isIntersecting) continue;
      observer.unobserve(entry.target);
      swap(entry.target as HTMLImageElement);
    }
  }, { rootMargin: '300px 0px', threshold: 0 });

  const scan = (scope: ParentNode = document): void => {
    scope.querySelectorAll('img[data-lazy-src]').forEach((el) => io.observe(el));
  };
  scan();

  const mo = new MutationObserver((muts) => {
    for (const mut of muts) {
      mut.addedNodes.forEach((n) => { if (n instanceof HTMLElement) scan(n); });
    }
  });
  mo.observe(document.body, { childList: true, subtree: true });
  onPageDispose(() => { io.disconnect(); mo.disconnect(); });
}
