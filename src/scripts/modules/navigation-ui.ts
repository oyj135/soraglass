import { pageOn,pageFrame } from './lifecycle';

/* P1-9：抽屉打开时把背景区域（顶栏 / 正文 / 页脚）设为 inert，Tab 不再穿到
   背后内容；关闭即撤销。抽屉自身是 body 级兄弟节点，不在这些区域内。 */
const SHEET_BG = '[data-sg-nav], #sg-main, .sg-footer-zone';

function setSheet(open: boolean): void {
  const sheet = document.querySelector<HTMLElement>('[data-sg-sheet]');
  sheet?.classList.toggle('is-open', open);
  sheet?.setAttribute('aria-hidden', open ? 'false' : 'true');
  document.documentElement.classList.toggle('sg-lock', open);
  document.querySelectorAll<HTMLElement>(SHEET_BG).forEach(el => {
    if (open) el.setAttribute('inert', ''); else el.removeAttribute('inert');
  });
  document.querySelectorAll('[data-sg-sheet-open]').forEach(button => {
    button.setAttribute('aria-expanded', String(open));
  });
}

function closeGroups(except?: HTMLElement): void {
  document.querySelectorAll<HTMLElement>('[data-sg-nav-more]').forEach(group => {
    if (group === except) return;
    group.classList.remove('is-open');
    group.querySelector('button')?.setAttribute('aria-expanded', 'false');
  });
}

export function closeNavigationUI(): void {
  setSheet(false);
  closeGroups();
}

export function initNav(): void {
  const nav = document.querySelector<HTMLElement>('[data-sg-nav]');
  const sheet = document.querySelector<HTMLElement>('[data-sg-sheet]');
  /* A1（2026-11-19）：滚动后顶栏中央显示当前页名，点击回顶（源站 #name-container）。
     文字取正文页头标题，拿不到就用 document.title 去掉「 - 站名」后缀。 */
  const pageName = document.querySelector<HTMLElement>('[data-sg-nav-page]');
  if (pageName) {
    const heading = document.querySelector<HTMLElement>('.sg-page-title, .sg-front-title, .sg-article-head h1');
    const raw = (heading?.textContent || document.title.split(/\s+-\s+/)[0] || '').trim();
    if (raw) {
      pageName.textContent = raw;
      pageName.removeAttribute('hidden');
    }
    pageOn(pageName, 'click', event => {
      event.preventDefault();
      window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    });
  }
  document.querySelectorAll<HTMLElement>('[data-sg-sheet-open]').forEach(button => {
    pageOn(button, 'click', () => setSheet(!sheet?.classList.contains('is-open')));
  });
  sheet && pageOn(sheet, 'click', event => {
    if (event.target === sheet) setSheet(false);
  });
  pageOn(document, 'keydown', event => {
    if (event.key === 'Escape') closeNavigationUI();
  });
  if (!nav) return;
  let last = window.scrollY;
  let ticking = false;
  pageOn(window, 'scroll', () => {
    if (ticking) return;
    ticking = true;
    pageFrame(() => {
      const y = window.scrollY;
      nav.classList.toggle('is-hidden', y > last && y > 56);
      nav.classList.toggle('is-scrolled', y > 56);
      if (y === 0) nav.classList.remove('is-hidden');
      last = y;
      ticking = false;
    });
  }, { passive: true });
}

export function initNavMore(): void {
  const groups = document.querySelectorAll<HTMLElement>('[data-sg-nav-more]');
  if (!groups.length) return;
  groups.forEach(group => {
    const button = group.querySelector<HTMLButtonElement>('.sg-nav-more-btn');
    if (!button) return;
    pageOn(button, 'click', event => {
      event.stopPropagation();
      const open = !group.classList.contains('is-open');
      closeGroups(group);
      group.classList.toggle('is-open', open);
      button.setAttribute('aria-expanded', String(open));
    });
  });
  pageOn(document, 'click', event => {
    if (!(event.target instanceof Element) || !event.target.closest('[data-sg-nav-more]')) closeGroups();
  });
}
