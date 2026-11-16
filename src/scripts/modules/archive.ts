import { pageOn, pageTimeout, pageInterval, pageFrame, onPageDispose, pageSignal } from './lifecycle';
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
/** 知识档案显示层分页：每页 9 篇（3×3 矩阵）。服务端仍取全量，分页只控制可见性。 */
const PAGE_SIZE = 9;

export function initArchive(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-archive]');
  if (!root) return;

  const cards = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-archive-card]'));
  if (!cards.length) return;

  const tagButtons = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-archive-tag]'));
  const empty = root.querySelector<HTMLElement>('[data-sg-archive-empty]');
  const pager = root.querySelector<HTMLElement>('[data-sg-page-nav]');
  const input = root.querySelector<HTMLInputElement>('input.sg-search-input')
    ?? document.querySelector<HTMLInputElement>('main input.sg-search-input');

  // 索引一次，输入时不再读 DOM
  const keywords = cards.map((el) => `${el.dataset.keywords ?? ''} ${el.textContent ?? ''}`.toLowerCase());
  const tags = cards.map((el) => (el.dataset.tags ?? '').split('|').filter(Boolean));

  let activeTag = '';
  let currentPage = 1;

  const renderPager = (totalPages: number): void => {
    if (!pager) return;
    if (totalPages <= 1) {
      pager.replaceChildren();
      pager.hidden = true;
      return;
    }
    pager.hidden = false;
    const frag = document.createDocumentFragment();

    const makeBtn = (label: string, page: number, opts: { disabled?: boolean; current?: boolean; aria?: string } = {}): HTMLButtonElement => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sg-page-btn';
      btn.textContent = label;
      btn.dataset.sgPage = String(page);
      if (opts.disabled) { btn.disabled = true; btn.setAttribute('aria-disabled', 'true'); }
      if (opts.current) { btn.classList.add('is-active'); btn.setAttribute('aria-current', 'page'); }
      if (opts.aria) btn.setAttribute('aria-label', opts.aria);
      return btn;
    };

    frag.appendChild(makeBtn('上一页', currentPage - 1, { disabled: currentPage <= 1, aria: '上一页' }));
    for (let p = 1; p <= totalPages; p += 1) {
      frag.appendChild(makeBtn(String(p), p, { current: p === currentPage, aria: `第 ${p} 页` }));
    }
    frag.appendChild(makeBtn('下一页', currentPage + 1, { disabled: currentPage >= totalPages, aria: '下一页' }));
    pager.replaceChildren(frag);
  };

  const apply = (): void => {
    const query = (input?.value ?? '').trim().toLowerCase();
    // 第一遍：算出所有命中项（不触碰 DOM class）
    const hits: number[] = [];
    cards.forEach((card, i) => {
      const byTag = activeTag === '' || tags[i].includes(activeTag);
      const byText = query === '' || (keywords[i] ?? '').includes(query);
      if (byTag && byText) hits.push(i);
    });

    const totalPages = Math.max(1, Math.ceil(hits.length / PAGE_SIZE));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * PAGE_SIZE;
    const visible = new Set(hits.slice(start, start + PAGE_SIZE));

    // 第二遍：命中且在当前页的才显示
    cards.forEach((card, i) => card.classList.toggle(HIDE, !visible.has(i)));
    if (empty) empty.hidden = hits.length > 0;
    renderPager(totalPages);
  };

  const resetAndApply = (): void => {
    currentPage = 1;
    apply();
  };

  const selectTag = (btn: HTMLElement): void => {
    const tag = btn.dataset.sgArchiveTag === '全部' ? '' : (btn.dataset.sgArchiveTag ?? '');
    if (tag === activeTag) return;
    activeTag = tag;
    tagButtons.forEach((other) => {
      const on = other === btn;
      other.classList.toggle('is-active', on);
      other.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    resetAndApply();
  };

  // 分页条点击（事件委托）：翻页并把焦点留在分页区顶部
  if (pager) {
    pageOn(pager, 'click', (event) => {
      const btn = (event.target instanceof Element) ? event.target.closest<HTMLButtonElement>('.sg-page-btn') : null;
      if (!btn || btn.disabled) return;
      const page = Number(btn.dataset.sgPage);
      if (!Number.isFinite(page) || page === currentPage) return;
      currentPage = page;
      apply();
      const active = pager.querySelector<HTMLButtonElement>('.sg-page-btn.is-active');
      active?.focus();
    });
  }

  tagButtons.forEach((btn) => {
    btn.setAttribute('aria-pressed', btn.classList.contains('is-active') ? 'true' : 'false');
    // 点击才选中（2026-11-16 改回）：悬浮划过不再切换筛选，避免鼠标移动误触。
    // button 元素天然支持 Enter/Space 触发 click，键盘无障碍不受影响。
    btn.addEventListener('click', () => selectTag(btn));
  });

  if (input) {
    let timer = 0;
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      timer = pageTimeout(resetAndApply, DEBOUNCE);
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        input.value = '';
        resetAndApply();
      }
      // 这个输入框在 sg_page_head 的搜索表单里，回车会跳走。
      // 本页已是站内全量档案，即时过滤即可，拦住回车留在原地。
      if (event.key === 'Enter') {
        event.preventDefault();
        resetAndApply();
      }
    });
  }

  apply();
}
