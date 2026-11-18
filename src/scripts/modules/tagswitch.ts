/**
 * 知识档案标签切换过渡（第十七轮，用户反馈「标签切换太僵硬」）。
 *
 * 标签筛选是服务端渲染的真实链接（保留分页、SEO 与无 JS 可用）。这里只补
 * 动效节奏：点击标签 → 列表整体下沉淡出 0.27s（CSS .is-switching）→ 导航；
 * 新页面卡片由 reveal 模块交错入场 + view-transition 页面转场完成「洗牌」感。
 * reduced-motion、修饰键 / 中键（新标签页）、当前标签重复点击都不拦截。
 */
import { reducedMotion } from './runtime';

export function initTagSwitch(): void {
  const list = document.querySelector<HTMLElement>('.sg-archive-list');
  if (!list || list.dataset.sgTagExitBound === '1') return;
  list.dataset.sgTagExitBound = '1';

  document.querySelectorAll<HTMLAnchorElement>('.sg-archive-tags .sg-filter-btn').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      if (reducedMotion() || event.defaultPrevented) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;
      if (btn.classList.contains('is-active')) return;
      event.preventDefault();
      list.classList.add('is-switching');
      window.setTimeout(() => { window.location.href = btn.href; }, 270);
    });
  });
}
