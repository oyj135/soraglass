/**
 * 换页幕布（第十八轮，对齐源站 fomalhaut heo preloader）。
 *
 * 源站的换页动画 = 全屏幕布：pjax:send 盖上（initLoading）、内容换好 pjax:complete
 * 揭开（endLoading，.3s 淡出）；首次加载靠 window load / 5s 超时 / 点击揭开。
 * soraglass 的等价实现：
 *  · 首次加载 —— initCurtain 在 load / 5s 超时 / 点击时揭开（与源站三保险一致）
 *  · 软换页 —— navigation.ts 在 fetch 前盖上（curtainShow）、render 后揭开（curtainHide）
 * 幕布本体由 header.php 输出；样式见 effects.css .sg-curtain。
 */
const EL_ID = 'sg-curtain';

const el = (): HTMLElement | null => document.getElementById(EL_ID);

export function curtainShow(): void {
  el()?.classList.remove('is-loaded');
}

export function curtainHide(): void {
  el()?.classList.add('is-loaded');
}

export function initCurtain(): void {
  const node = el();
  if (!node) return;
  if (node.dataset.sgCurtainBound === '1') return;
  node.dataset.sgCurtainBound = '1';
  node.addEventListener('click', curtainHide, { once: true });
  if (document.readyState === 'complete') {
    curtainHide();
  } else {
    window.addEventListener('load', curtainHide, { once: true });
    /* 源站同款 5s 兜底：资源卡住时也保证幕布最终揭开 */
    window.setTimeout(curtainHide, 5000);
  }
  /* bfcache 后退返回：恢复的页面本来就是揭开状态，兜底再揭一次 */
  window.addEventListener('pageshow', () => curtainHide());
}
