import { pageOn, pageTimeout } from './lifecycle';
/**
 * 网址导航页（page-nav.php）：右侧「通讯录」分组锚点 + scrollspy + 站点即时过滤。
 *
 * DOM 契约：
 *   锚点栏   [data-sg-nav-side]，链接 [data-sg-nav-anchor="<分组 id>"]
 *   分组     [data-sg-nav-group][id]
 *   过滤容器 [data-sg-nav-filter]，站点卡 .sg-navbox-card[data-keywords]
 *   搜索框   容器所在 main 内的 input.sg-search-input（皮肤复用全站 sg-search）
 * 页面没有锚点栏/卡片（未配置数据）时自动空跑。
 *
 * 交互：
 *   - 点击锚点：平滑滚动到分组（reduced-motion 直跳），阻止默认锚点跳变；
 *   - scrollspy：以视口上部 35% 为基准线，取「最后一个越过基准线的分组」为当前组，
 *     高亮锚点（is-active）；
 *   - 搜索：按 data-keywords + 卡片文本即时过滤，整组全隐时连组标题一起藏
 *     （CSS :has），全部隐藏时显示空态。监听走 pageOn，PJAX 换页自动清理。
 */

const HIDDEN = 'sg-filtered-out';

export function initNavBox(): void {
  const side = document.querySelector<HTMLElement>('[data-sg-nav-side]');
  const container = document.querySelector<HTMLElement>('[data-sg-nav-filter]');
  if (!side && !container) return;

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

  /* —— 站点即时过滤 —— */
  if (!container) return;
  const cards = Array.from(container.querySelectorAll<HTMLElement>('.sg-navbox-card[data-keywords]'));
  if (!cards.length) return;
  const main = container.closest('main');
  const input = main ? main.querySelector<HTMLInputElement>('input.sg-search-input') : null;
  if (!input) return;

  const keywords = cards.map((el) => `${el.dataset.keywords ?? ''} ${el.textContent ?? ''}`.toLowerCase());

  const empty = document.createElement('p');
  empty.className = 'sg-filter-empty';
  empty.hidden = true;
  empty.textContent = '没有匹配的站点，换个关键词试试。';
  container.after(empty);

  const apply = (): void => {
    const keyword = input.value.trim().toLowerCase();
    let shown = 0;
    cards.forEach((el, i) => {
      const hit = keyword === '' || (keywords[i] ?? '').includes(keyword);
      el.classList.toggle(HIDDEN, !hit);
      if (hit) shown += 1;
    });
    empty.hidden = shown > 0 || keyword === '';
  };

  let timer = 0;
  pageOn(input, 'input', () => {
    window.clearTimeout(timer);
    timer = pageTimeout(apply, 120);
  });
  pageOn(input, 'keydown', (event) => {
    if (event.key === 'Escape') {
      input.value = '';
      apply();
    }
  });
}
