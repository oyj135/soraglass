import { pageOn } from './lifecycle';
/**
 * 网址导航页（page-nav.php）：右侧「通讯录」分组锚点 + scrollspy。
 *
 * DOM 契约：
 *   锚点栏   [data-sg-nav-side]，链接 [data-sg-nav-anchor="<分组 id>"]
 *   分组     [data-sg-nav-group][id]
 * 页面没有锚点栏（未配置数据）时自动空跑。
 *
 * 交互：
 *   - 点击锚点：平滑滚动到分组（reduced-motion 直跳），阻止默认锚点跳变；
 *   - scrollspy：以视口上部 35% 为基准线，取「最后一个越过基准线的分组」为当前组，
 *     高亮锚点（is-active）。监听走 pageOn，PJAX 换页自动清理。
 */

export function initNavBox(): void {
  const side = document.querySelector<HTMLElement>('[data-sg-nav-side]');
  if (!side) return;

  const groups = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-nav-group]'));

  /* —— 分组锚点 + scrollspy —— */
  if (side && groups.length) {
    const links = new Map<string, HTMLElement>();
    side.querySelectorAll<HTMLAnchorElement>('[data-sg-nav-anchor]').forEach((a) => {
      const id = a.dataset.sgNavAnchor ?? '';
      if (id) links.set(id, a);
    });
    if (links.size) {
      const setActive = (id: string): void => {
        links.forEach((a, key) => a.classList.toggle('is-active', key === id));
      };
      side.addEventListener('click', (event) => {
        const a = (event.target as HTMLElement).closest<HTMLAnchorElement>('[data-sg-nav-anchor]');
        if (!a) return;
        const id = a.dataset.sgNavAnchor ?? '';
        const target = document.getElementById(id);
        if (!target) return;
        event.preventDefault();
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        target.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        setActive(id);
      });
      let ticking = false;
      const pick = (): void => {
        ticking = false;
        const line = window.innerHeight * 0.35;
        let current = groups[0].id;
        for (const group of groups) {
          if (group.getBoundingClientRect().top <= line) current = group.id;
          else break;
        }
        setActive(current);
      };
      const onScroll = (): void => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(pick);
      };
      pageOn(window, 'scroll', onScroll, { passive: true });
      pageOn(window, 'resize', onScroll, { passive: true });
      pick();
    }
  }
}
