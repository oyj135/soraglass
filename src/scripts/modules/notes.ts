/**
 * 碎语时间轴：标签筛选 / 正倒序 / 长文展开。
 *
 * 全部在已渲染的 DOM 上做，不发请求。
 * DOM 契约（见 partials/notes-stream.php）：
 *   根      [data-sg-notes]
 *   年份组  [data-sg-tl-month] > [data-sg-tl-items] > [data-sg-tl-item][data-ts][data-tag]
 *   标签    [data-sg-notes-tag="标签名"]（空串 = 全部）
 *   排序    [data-sg-notes-sort="desc|asc"]
 *   空态    [data-sg-notes-empty]
 */

const HIDDEN = 'sg-filtered-out';

type Month = {
  section: HTMLElement;
  items: HTMLElement[];
};

export function initNotes(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-notes]');
  if (!root) return;

  const months: Month[] = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-tl-month]')).map((section) => ({
    section,
    items: Array.from(section.querySelectorAll<HTMLElement>('[data-sg-tl-item]')),
  }));
  const items = months.flatMap((month) => month.items);
  if (!items.length) return;

  const tags = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-sg-notes-tag]'));
  const sorts = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-sg-notes-sort]'));
  const empty = root.querySelector<HTMLElement>('[data-sg-notes-empty]');

  let activeTag = '';
  let dir: 'desc' | 'asc' = 'desc';

  const apply = (): void => {
    let shown = 0;

    items.forEach((el) => {
      const hit = activeTag === '' || (el.dataset.tag ?? '') === activeTag;
      el.classList.toggle(HIDDEN, !hit);
      if (hit) shown += 1;
    });

    months.forEach((month) => {
      const visible = month.items.some((el) => !el.classList.contains(HIDDEN));
      month.section.classList.toggle('is-empty', !visible);
    });

    if (empty) empty.hidden = shown > 0;
  };

  /* 排序：先排每个月的条目，再按「该月最新的那条」重排月份块 */
  const sort = (): void => {
    const sign = dir === 'asc' ? 1 : -1;

    months.forEach((month) => {
      const list = month.section.querySelector<HTMLElement>('[data-sg-tl-items]');
      if (!list) return;
      month.items
        .slice()
        .sort((a, b) => sign * (Number(a.dataset.ts ?? 0) - Number(b.dataset.ts ?? 0)))
        .forEach((el) => list.appendChild(el));
    });

    const anchor = (month: Month): number =>
      Math.max(...month.items.map((el) => Number(el.dataset.ts ?? 0)));

    const wrap = months[0]?.section.parentElement;
    if (wrap) {
      months
        .slice()
        .sort((a, b) => sign * (anchor(a) - anchor(b)))
        .forEach((month) => wrap.appendChild(month.section));
    }
  };

  tags.forEach((btn) => {
    btn.addEventListener('click', () => {
      activeTag = btn.dataset.sgNotesTag ?? '';
      tags.forEach((other) => other.classList.toggle('is-active', other === btn));
      apply();
    });
  });

  sorts.forEach((btn) => {
    btn.addEventListener('click', () => {
      const next = btn.dataset.sgNotesSort === 'asc' ? 'asc' : 'desc';
      if (next === dir) return;
      dir = next;
      sorts.forEach((other) => {
        const on = other === btn;
        other.classList.toggle('is-active', on);
        other.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      sort();
      apply();
    });
  });

  /* 长文：超过 10 行才显示展开按钮，点一下展开，不跳转 */
  root.querySelectorAll<HTMLElement>('.sg-moment-bubble').forEach((el) => {
    const expandable = (): boolean => el.scrollHeight - el.clientHeight > 4;
    if (expandable()) {
      el.classList.add('is-clampable');
      el.setAttribute('role', 'button');
      el.setAttribute('tabindex', '0');
      el.setAttribute('title', '展开全文');
    }
    const toggle = (): void => {
      if (!el.classList.contains('is-clampable')) return;
      const open = el.classList.toggle('is-expanded');
      el.setAttribute('title', open ? '收起' : '展开全文');
    };
    el.addEventListener('click', toggle);
    el.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggle();
      }
    });
  });
}
