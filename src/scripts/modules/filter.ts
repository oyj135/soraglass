import { pageTimeout } from './lifecycle';
/**
 * 页内即时筛选：在已渲染的卡片上按关键词过滤，不发起请求。
 * DOM 契约：[data-sg-filter-grid] 容器 + 子项 [data-sg-filter-item data-keywords="..."]。
 * 页面上没有列表（如首页 / 文章页）时自动空跑。
 */

const HIDE_CLASS = 'sg-filtered-out';
const DEBOUNCE = 120;

export function initFilters(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-filter-grid]').forEach((grid) => {
    const items = Array.from(grid.querySelectorAll<HTMLElement>('[data-sg-filter-item]'));
    if (!items.length) return;

    const scope = grid.closest('main') ?? document;
    const input = scope.querySelector<HTMLInputElement>('input.sg-search-input');
    if (!input) return;

    // 索引一次，避免每次输入都读 DOM
    const index = items.map((el) => `${el.dataset.keywords ?? ''} ${el.textContent ?? ''}`.toLowerCase());

    const empty = document.createElement('p');
    empty.className = 'sg-filter-empty';
    empty.hidden = true;
    empty.textContent = '没有匹配的条目，换个关键词试试。';
    grid.after(empty);

    let timer = 0;

    const apply = (): void => {
      const keyword = input.value.trim().toLowerCase();
      let shown = 0;
      items.forEach((el, i) => {
        const hit = keyword === '' || (index[i] ?? '').includes(keyword);
        el.classList.toggle(HIDE_CLASS, !hit);
        if (hit) shown += 1;
      });
      empty.hidden = shown > 0 || keyword === '';
    };

    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = pageTimeout(apply, DEBOUNCE);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        input.value = '';
        apply();
      }
    });
  });
}
