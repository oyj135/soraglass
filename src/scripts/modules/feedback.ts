import { pageTimeout } from './lifecycle';

let toastTimer = 0;

export function toast(message: string): void {
  const el = document.querySelector<HTMLElement>('[data-sg-toast]');
  if (!el) return;
  el.textContent = message;
  el.classList.add('is-visible');
  window.clearTimeout(toastTimer);
  toastTimer = pageTimeout(() => el.classList.remove('is-visible'), 2200);
}
