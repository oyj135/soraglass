import { pageOn } from './lifecycle';
import { reducedMotion } from './runtime';

/**
 * 顶部公告跑马灯（2026-11-20）：按内容实际宽度换算滚动时长。
 *
 * 固定时长的问题很明显 —— 一条短公告慢得发闷，长公告又快到看不清。
 * 这里按 62px/s 的横向阅读速度反算「一份内容宽度 ÷ 速度」，
 * 再夹在 12–120s 之间；CSS 里保留 26s 兜底值，脚本没跑也能滚。
 * 减少动效时 CSS 已关掉动画并改为可横向滑动，这里什么都不做。
 */
export function initTicker(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-ticker]');
  const track = root?.querySelector<HTMLElement>('.sg-ticker-track');
  const group = track?.querySelector<HTMLElement>('.sg-ticker-group');
  if (!root || !track || !group) return;

  const sync = (): void => {
    if (reducedMotion()) return;
    const width = group.getBoundingClientRect().width;
    if (width < 1) return;
    const duration = Math.min(120, Math.max(12, width / 62));
    track.style.setProperty('--sg-ticker-duration', `${duration.toFixed(1)}s`);
    track.style.setProperty('--sg-ticker-shift', `${-Math.round(width)}px`);
  };

  sync();
  pageOn(window, 'resize', sync, { passive: true });
  // 中文字体（霞鹜文楷）晚到会改变文字宽度，字体就绪后再量一次
  document.fonts?.ready.then(sync).catch(() => { /* 量不到就沿用上一次 */ });
}
