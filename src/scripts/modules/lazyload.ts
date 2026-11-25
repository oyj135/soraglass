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
    /* 源站 lazyload blur 语义（2026-11-25）：
       未加载 = [data-lazy-src] 上的 blur(8px) 占位，覆盖「进入视口 → 真图解码完成」整段；
       加载成功 = 加 .is-lazy-in → 淡入 + 移除 data-lazy-src → 模糊归零；
       加载失败 = 加 .is-error → 模糊归零，不留一团糊（源站 .error 同款）。
       关键：data-lazy-src 不能在换装时就移除 —— 那样模糊会在「刚发出请求」时消失，
       真图还没到就露出空白，正是要治的病。与源站一致，保留到 load/error 才清。 */
    const settle = (): void => {
      img.classList.remove('is-error');
      img.classList.add('is-lazy-in');
      img.removeAttribute('data-lazy-src');
    };
    img.addEventListener('load', settle, { once: true });
    img.addEventListener('error', () => {
      /* 画廊缩略图失败时 gallery.ts 会把原图地址写回 data-lazy-src 重试；
         此时属性已变（≠ real），不急着定死错误态，等下一次 load 收尾。 */
      if ((img.dataset.lazySrc ?? '') !== real) return;
      img.classList.add('is-error');
      img.removeAttribute('data-lazy-src');
    }, { once: true });
    img.src = real;
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
