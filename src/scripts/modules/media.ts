import { config,reducedMotion } from './runtime';
import { isDark } from './theme';
import { pageOn,pageTimeout,onPageDispose,pageSignal } from './lifecycle';

/** 日间/夜间独立壁纸组；隐藏或减少动效时暂停轮播。 */
export function initBackground(): void {
  const cfg = config();
  const wall = document.querySelector<HTMLElement>('[data-sg-bg]');
  if (wall) {
    const delay = Number(wall.dataset.interval || 9000); let timer = 0, dark = isDark();
    const start = (): void => {
      clearTimeout(timer); if (document.hidden || reducedMotion() || delay <= 0) return;
      const group = wall.querySelector<HTMLElement>(`[data-bg-mode="${isDark() ? 'dark' : 'light'}"]`);
      const frames = group ? [...group.querySelectorAll<HTMLElement>('.sg-bg-frame')] : [];
      if (frames.length < 2) return;
      timer = pageTimeout(() => { const index = (frames.findIndex(frame => frame.classList.contains('is-active')) + 1) % frames.length; frames.forEach((frame, n) => frame.classList.toggle('is-active', n === index)); start(); }, Math.max(3000, delay));
    };
    const observer = new MutationObserver(() => { if (dark !== isDark()) { dark = isDark(); start(); } });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] }); onPageDispose(() => observer.disconnect());
    pageOn(document, 'visibilitychange', start); pageOn(matchMedia('(prefers-reduced-motion: reduce)'), 'change', start);
    wall.querySelectorAll<HTMLImageElement>('img').forEach(img => pageOn(img, 'error', () => { if (!img.dataset.fallback) { img.dataset.fallback = '1'; img.src = cfg.themeUrl + 'dist/img/bg-1.svg'; } }));
    start();
  }

  const gradient = document.querySelector<HTMLElement>('[data-sg-gradient]');
  if (gradient && cfg.themeColors.length >= 2) {
    gradient.style.backgroundImage = `linear-gradient(-45deg, ${cfg.themeColors.join(', ')})`;
  }
}

/**
 * 图片兜底：外链图挂了 / 悬住不回就换成内置占位图。
 *
 * 2026-11-12 第九轮（用户报「内容都出来了，标签页还在转圈」）：
 * 根因不是主题的加载状态没重置 —— 浏览器要等文档里**所有** <img> 的请求结束
 * 才派发 load，标签页的转圈动画就是 load 的指示灯。外链图床（picsum）「连上了
 * 但不回数据」时请求会一直挂着，load 永远不来，转圈也就永远不停。
 * 上一版看门狗只处理「视口内」的图，而首页那几张图全挂在首屏下方
 * （实测 top 1083~1505px），被 `rect.top > viewport` 直接跳过 —— 于是内容看得见、
 * 转圈停不下来。实测佐证：把这几张图的 src 换成本地占位图后 readyState 立刻
 * 由 interactive 变 complete、load 补发。
 *
 * 现在的三级止损：
 *   ① 判定「请求已发出」（armed）：非 lazy 图解析即发；lazy 图进入 3000px 预取距离
 *      才发（Chrome 的懒加载预取上限是 1250px，留了余量）。只有已发出的请求才拖得住
 *      load，所以先只救这一批，不误伤还没轮到的懒加载图。
 *   ② 6s 看门狗：仍未完成就换占位图。换 src 会让浏览器中止旧请求，load 立刻走完。
 *   ③ 12s 死线：文档还是 interactive 就全量换（含还没开始下载的），保证转圈有尽头。
 * 换掉不等于放弃：原地址记在 data-sg-origin，图滚回视口时用游离 Image 探一次，
 * 成功再换回来 —— 那时 load 已结束，游离请求不影响任何计时。
 *
 * 例外：带 data-sg-fallback 的图片（播放器封面）有自己的专用兜底图，交给 player.ts。
 */
const RESCUE_MS = 6000;
const DEADLINE_MS = 12000;
const ARM_DISTANCE = 3000;

export function initImageFallback(): void {
  const signal = pageSignal();
  const { placeholder } = config();
  if (!placeholder) return;

  const sameOrigin = window.location.origin;
  const remote = (url: string): boolean =>
    /^https?:\/\//i.test(url) && !url.startsWith(`${sameOrigin}/`);

  /** 被换掉的图滚回视口时再试一次（只试一次，失败就认占位图） */
  const observer = typeof IntersectionObserver === 'undefined'
    ? null
    : new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          const img = entry.target as HTMLImageElement;
          if (!entry.isIntersecting) return;
          observer?.unobserve(img);
          const origin = img.dataset.sgOrigin ?? '';
          if (origin === '' || img.dataset.sgRetry === '1') return;
          // 刚换掉的那几秒还在超时窗口里，别立刻又打一枪；图本来就在视口内时
          // IO 不会再回调，所以这里用定时器补齐，不能只靠「再次进入视口」。
          const wait = Math.max(0, 4000 - (Date.now() - Number(img.dataset.sgRescueAt ?? '0')));
          pageTimeout(() => {
            if (img.dataset.sgRetry === '1') return;
            img.dataset.sgRetry = '1';
            // 游离 Image 先探路：成功再写回，失败就安静地留着占位图（不会破图）
            const probe = new Image();
            probe.decoding = 'async';
            const detach = onPageDispose(() => { probe.onload = probe.onerror = null; });
            probe.onload = () => {
              detach();
              probe.onload = probe.onerror = null;
              if (!signal.aborted) img.src = origin;
            };
            probe.onerror = () => { detach(); probe.onload = probe.onerror = null; };
            probe.src = origin;
          }, wait);
        });
      }, { rootMargin: '400px 0px' });

  /**
   * 换占位图 + 记住原地址。
   * retryable=false 用于「已经明确 error」的图：地址本身坏了，不用再试。
   */
  onPageDispose(() => observer?.disconnect());
  const rescue = (img: HTMLImageElement, retryable = true): void => {
    const src = img.getAttribute('src') ?? '';
    if (src === '' || src === placeholder) return;
    if (img.dataset.sgRescue === '1') return;
    img.dataset.sgRescue = '1';
    img.dataset.sgOrigin = src;
    img.dataset.sgRescueAt = String(Date.now());
    if (!retryable) img.dataset.sgRetry = '1';
    img.src = placeholder;
    if (retryable) observer?.observe(img);
  };

  /** 懒加载图是否已经发出请求：非 lazy 一律算已发；lazy 看是否进入预取距离 */
  const armed = (img: HTMLImageElement): boolean => {
    if (img.loading !== 'lazy') return true;
    return img.getBoundingClientRect().top < window.innerHeight + ARM_DISTANCE;
  };

  const sweep = (force: boolean): void => {
    document.querySelectorAll<HTMLImageElement>('img').forEach((img) => {
      if (img.hasAttribute('data-sg-fallback')) return;
      if (img.dataset.sgRescue === '1') return;
      if (img.complete && img.naturalWidth > 0) return;
      if (!remote(img.getAttribute('src') ?? '')) return;
      if (!force && !armed(img)) return;
      rescue(img);
    });
  };

  // ① 首屏已经失败的图（error 在脚本执行前就派发完了）
  document.querySelectorAll<HTMLImageElement>('img').forEach((img) => {
    if (img.hasAttribute('data-sg-fallback')) return;
    if (img.complete && img.naturalWidth === 0) rescue(img, false);
  });

  // ② 之后才失败的图（含灯箱、歌词封面等动态插入的节点）
  pageOn(document, 'error', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLImageElement)) return;
    if (target.hasAttribute('data-sg-fallback')) return;
    rescue(target, false);
  }, true);

  // ③ 两级止损：常规 6s，卡死 12s
  pageTimeout(() => sweep(false), RESCUE_MS);
  pageTimeout(() => {
    if (document.readyState !== 'complete') sweep(true);
  }, DEADLINE_MS);
}
