/**
 * 岁候 · 轮盘交互（评审稿 §6.1 / §6.4 / §7.7）
 *
 * 只做三件事，别的都不做：
 *   1) hover 节气 → 它所属的整段宫弧亮起；hover 宫 → 它覆盖的两个节气同时亮起
 *   2) 中心读数跟着 hover 变，移出即复原
 *   3) roving tabindex：整环一个 Tab 停靠点，←/→/Home/End 在 24 节气间移动，
 *      ↑/↓ 在节气与所属宫之间切换
 *
 * 生命周期：由 main.ts 的 mountPage() 调用。PJAX 换页时 mountPage 会重跑，
 * 事件全部走 pageOn（随页面销毁自动解绑），DOM 引用一律现查，不存模块顶层。
 */
import { pageOn } from './lifecycle';

type Focusable = { focus: () => void; getAttribute: (name: string) => string | null; setAttribute: (name: string, value: string) => void };

export function initSeasonWheel(): void {
  const wheel = document.querySelector<HTMLElement>('.sg-wheel');
  if (!wheel) return;

  const terms = Array.from(wheel.querySelectorAll<Element>('[data-sg-term-i]'));
  const gongs = Array.from(wheel.querySelectorAll<Element>('[data-sg-gong-i]'));
  if (terms.length === 0) return;

  const nameEl = wheel.querySelector<HTMLElement>('[data-sg-wheel-name]');
  const subEl = wheel.querySelector<HTMLElement>('[data-sg-wheel-sub]');
  const originName = nameEl?.textContent ?? '';
  const originSub = subEl?.textContent ?? '';

  const gongOf = new Map<string, Element>();
  for (const gong of gongs) gongOf.set(gong.getAttribute('data-sg-gong-i') ?? '', gong);

  const clearLinked = (): void => {
    for (const el of terms) el.classList.remove('is-linked');
    for (const el of gongs) el.classList.remove('is-linked');
  };

  const restore = (): void => {
    clearLinked();
    if (nameEl) nameEl.textContent = originName;
    if (subEl) subEl.textContent = originSub;
  };

  const highlightTerm = (term: Element): void => {
    clearLinked();
    term.classList.add('is-linked');
    const gong = gongOf.get(term.getAttribute('data-sg-gong') ?? '');
    if (gong) gong.classList.add('is-linked');
    if (nameEl) nameEl.textContent = term.getAttribute('data-sg-label') ?? originName;
    if (subEl) subEl.textContent = term.getAttribute('data-sg-sub') ?? originSub;
  };

  const highlightGong = (gong: Element): void => {
    clearLinked();
    gong.classList.add('is-linked');
    const index = gong.getAttribute('data-sg-gong-i') ?? '';
    for (const term of terms) {
      if (term.getAttribute('data-sg-gong') === index) term.classList.add('is-linked');
    }
    if (nameEl) nameEl.textContent = gong.getAttribute('data-sg-label') ?? originName;
    if (subEl) subEl.textContent = '';
  };

  pageOn(wheel, 'mouseover', (event) => {
    const target = event.target as Element | null;
    const term = target?.closest?.('[data-sg-term-i]');
    if (term) { highlightTerm(term); return; }
    const gong = target?.closest?.('[data-sg-gong-i]');
    if (gong) highlightGong(gong);
  });

  pageOn(wheel, 'mouseleave', restore);

  /* 键盘焦点也触发同一套高亮：鼠标能看见的关系，键盘也要能看见 */
  pageOn(wheel, 'focusin', (event) => {
    const target = event.target as Element | null;
    const term = target?.closest?.('[data-sg-term-i]');
    if (term) { highlightTerm(term); return; }
    const gong = target?.closest?.('[data-sg-gong-i]');
    if (gong) highlightGong(gong);
  });

  const setTabStop = (active: Element): void => {
    for (const el of [...terms, ...gongs]) el.setAttribute('tabindex', el === active ? '0' : '-1');
  };

  const move = (current: Element, delta: number): void => {
    const index = terms.indexOf(current);
    if (index < 0) return;
    const next = terms[(index + delta + terms.length) % terms.length];
    setTabStop(next);
    (next as unknown as Focusable).focus();
  };

  pageOn(wheel, 'keydown', (event) => {
    const key = (event as KeyboardEvent).key;
    const target = (event.target as Element | null)?.closest?.('[data-sg-term-i], [data-sg-gong-i]');
    if (!target) return;

    if (key === 'Escape') {
      restore();
      return;
    }

    const isTerm = target.hasAttribute('data-sg-term-i');

    if (key === 'ArrowRight' && isTerm) {
      event.preventDefault();
      move(target, 1);
    } else if (key === 'ArrowLeft' && isTerm) {
      event.preventDefault();
      move(target, -1);
    } else if (key === 'Home' && isTerm) {
      event.preventDefault();
      setTabStop(terms[0]);
      (terms[0] as unknown as Focusable).focus();
    } else if (key === 'End' && isTerm) {
      event.preventDefault();
      const last = terms[terms.length - 1];
      setTabStop(last);
      (last as unknown as Focusable).focus();
    } else if (key === 'ArrowDown' && isTerm) {
      const gong = gongOf.get(target.getAttribute('data-sg-gong') ?? '');
      if (gong) {
        event.preventDefault();
        setTabStop(gong);
        (gong as unknown as Focusable).focus();
      }
    } else if (key === 'ArrowUp' && !isTerm) {
      const index = target.getAttribute('data-sg-gong-i') ?? '';
      const term = terms.find((el) => el.getAttribute('data-sg-gong') === index);
      if (term) {
        event.preventDefault();
        setTabStop(term);
        (term as unknown as Focusable).focus();
      }
    }
  });
}
