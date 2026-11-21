/**
 * 换页加载遮罩（2026-11-21，对齐源站 heo preloader）。
 *
 * 源站：pjax:send → preloader.initLoading()（遮罩 opacity 1，body 锁滚动），
 * pjax:complete → endLoading()（opacity 0 + z-index -1000，transition .3s），
 * 另有 load / 5s 超时 / 点击遮罩三保险。
 * soraglass 对应：软换页 fetch 前 loadingShow()、render 后 loadingHide()；
 * 首屏不参与（开屏 .sg-splash 已占位，叠加会黑两次）。
 * DOM 契约：header.php 的 #sg-loading（.sg-loading-img + .sg-loading-dot），
 * 样式见 effects.css「换页加载遮罩」段。
 */
import { reducedMotion } from './runtime';

const EL_ID = 'sg-loading';
/* 最短可见时长：命中缓存的页面 60ms 就换完了，遮罩来不及淡入就被撤掉，
   观感是一下白闪。补齐到 300ms，与源站 .3s 过渡同档。 */
const MIN_VISIBLE = 300;

let shownAt = 0;
let timer = 0;

export function loadingShow(): void {
  if (reducedMotion()) return;
  const node = document.getElementById(EL_ID);
  if (!node) return;
  window.clearTimeout(timer);
  shownAt = performance.now();
  node.classList.add('is-on');
  node.removeAttribute('aria-hidden');
}

export function loadingHide(): void {
  const node = document.getElementById(EL_ID);
  if (!node) return;
  const wait = Math.max(0, MIN_VISIBLE - (performance.now() - shownAt));
  window.clearTimeout(timer);
  timer = window.setTimeout(() => {
    node.classList.remove('is-on');
    node.setAttribute('aria-hidden', 'true');
  }, wait);
}
