/**
 * 知识档案（page-timeline.php）：关键词 × 标签双条件筛选。
 *
 * DOM 契约：
 *   根        [data-sg-archive]
 *   标签按钮  [data-sg-archive-tag="全部|标签名"]
 *   卡片      [data-sg-archive-card data-keywords="…" data-tags="a|b"]
 *   空态      [data-sg-archive-empty]
 *   搜索框    main 内的 input.sg-search-input（可选，没有就只按标签筛）
 *
 * 与 filter.ts 的区别：那边是单关键词、多用于简单列表；这里是两维交叉，
 * 所以单独成模块，两者挂点不重叠、不会互相抢。
 *
 * 2026-11-12 第四轮：视图切换（中枢链路 / 矩阵网格）整体移除，
 * 布局固定为矩阵，这里只剩筛选逻辑 —— 少一份 localStorage 状态，
 * 也少一次 resize 重排。
 */

const HIDE = 'sg-filtered-out';
const DEBOUNCE = 120;

export function initArchive(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-archive]');
  if (!root) return;

  const cards = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-archive-card]'));
  if (!cards.length) return;

  const tagButtons = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-archive-tag]'));
  const empty = root.querySelector<HTMLElement>('[data-sg-archive-empty]');
  const input = root.querySelector<HTMLInputElement>('input.sg-search-input')
    ?? document.querySelector<HTMLInputElement>('main input.sg-search-input');

  // 索引一次，输入时不再读 DOM
  const keywords = cards.map((el) => `${el.dataset.keywords ?? ''} ${el.textContent ?? ''}`.toLowerCase());
  const tags = cards.map((el) => (el.dataset.tags ?? '').split('|').filter(Boolean));

  let activeTag = '';

  const apply = (): void => {
    const query = (input?.value ?? '').trim().toLowerCase();
    let shown = 0;
    cards.forEach((card, i) => {
      const byTag = activeTag === '' || tags[i].includes(activeTag);
      const byText = query === '' || (keywords[i] ?? '').includes(query);
      const hit = byTag && byText;
      card.classList.toggle(HIDE, !hit);
      if (hit) shown += 1;
    });
    if (empty) empty.hidden = shown > 0;
  };

  tagButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      activeTag = btn.dataset.sgArchiveTag === '全部' ? '' : (btn.dataset.sgArchiveTag ?? '');
      tagButtons.forEach((other) => other.classList.toggle('is-active', other === btn));
      apply();
    });
  });

  if (input) {
    let timer = 0;
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(apply, DEBOUNCE);
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        input.value = '';
        apply();
      }
      // 这个输入框在 sg_page_head 的搜索表单里，回车会跳走。
      // 本页已是站内全量档案，即时过滤即可，拦住回车留在原地。
      if (event.key === 'Enter') {
        event.preventDefault();
        apply();
      }
    });
  }

  apply();
}
