/** Resources owned by the current page, released before replacing its DOM. */
let owner = new AbortController();
let disposers = new Set<() => void>();
export const pageSignal = (): AbortSignal => owner.signal;
export function onPageDispose(fn: () => void): () => void { disposers.add(fn); return () => disposers.delete(fn); }
export function disposePage(): void {
  owner.abort(); for (const fn of disposers) { try { fn(); } catch { /* finish other cleanup */ } }
  disposers = new Set(); owner = new AbortController();
}
type PageEvents = WindowEventMap & DocumentEventMap;
export function pageOn<K extends keyof PageEvents>(target: EventTarget, type: K, fn: (event: PageEvents[K]) => void, options?: boolean | AddEventListenerOptions): void {
  target.addEventListener(type, fn as EventListener, { ...(typeof options === 'boolean' ? { capture: options } : options), signal: owner.signal });
}
export function pageTimeout(fn: () => void, ms: number): number {
  const signal = owner.signal; let remove = (): void => {};
  const id = window.setTimeout(() => { remove(); if (!signal.aborted) fn(); }, ms);
  remove = onPageDispose(() => clearTimeout(id)); return id;
}
export function pageInterval(fn: () => void, ms: number): number {
  const signal = owner.signal; const id = window.setInterval(() => { if (!signal.aborted) fn(); }, ms);
  onPageDispose(() => clearInterval(id)); return id;
}
export function pageFrame(fn: FrameRequestCallback): number {
  const signal = owner.signal; let remove = (): void => {};
  const id = requestAnimationFrame(time => { remove(); if (!signal.aborted) fn(time); });
  remove = onPageDispose(() => cancelAnimationFrame(id)); return id;
}
