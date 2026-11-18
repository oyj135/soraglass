import { pageOn,pageFrame } from './lifecycle';

function setSheet(open: boolean): void {
  const sheet = document.querySelector<HTMLElement>('[data-sg-sheet]');
  sheet?.classList.toggle('is-open', open);
  sheet?.setAttribute('aria-hidden', open ? 'false' : 'true');
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
  const sheet = document.querySelector<HTMLElement>('[data-sg-sheet]');
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
