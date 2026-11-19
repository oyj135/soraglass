import { pageOn,pageFrame,onPageDispose } from './lifecycle';

/* 原生模态负责隔离全部背景控件，关闭后回到触发入口。 */
let sheetOpener: HTMLElement | null = null;

function setSheet(open: boolean): void {
  const sheet = document.querySelector<HTMLDialogElement>('[data-sg-sheet]');
  if (!sheet) return;
  if (open) {
    if (sheet.open) return;
    sheetOpener = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    sheet.classList.add('is-open');
    sheet.showModal();
  } else {
    const wasOpen = sheet.open;
    sheet.classList.remove('is-open');
    if (wasOpen) sheet.close();
    if (wasOpen && sheetOpener?.isConnected) sheetOpener.focus({ preventScroll: true });
    sheetOpener = null;
  }
  document.documentElement.classList.toggle('sg-lock', open);
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
  const sheet = document.querySelector<HTMLDialogElement>('[data-sg-sheet]');
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
    pageOn(button, 'click', () => setSheet(!sheet?.open));
  });
  document.querySelectorAll<HTMLElement>('[data-sg-sheet-close]').forEach(button => {
    pageOn(button, 'click', () => setSheet(false));
  });
  sheet && pageOn(sheet, 'click', event => {
    if (event.target === sheet) setSheet(false);
  });
  sheet && pageOn(sheet, 'cancel', event => {
    event.preventDefault();
    setSheet(false);
  });
  sheet && pageOn(sheet, 'close', () => {
    if (!sheet.open) setSheet(false);
  });
  const desktop = window.matchMedia('(min-width: 1024px)');
  pageOn(desktop, 'change', () => { if (desktop.matches) setSheet(false); });
  onPageDispose(() => setSheet(false));
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
