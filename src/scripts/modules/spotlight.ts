import { onPageDispose,pageOn } from './lifecycle';
import { reducedMotion } from './runtime';

const SPOT_SELECTOR = '.sg-feature-card, .sg-project, .sg-music-panel, .sg-music-side';

export function initSpotlight(): void {
  if (reducedMotion()) return;
  const fillEnabled = window.matchMedia('(pointer: fine)').matches;
  const borderEnabled = !window.matchMedia('(hover: none)').matches;
  if (!fillEnabled && !borderEnabled) return;

  document.querySelectorAll<HTMLElement>(`${SPOT_SELECTOR}, [data-sg-spotlight]`).forEach(card => {
    const fill = fillEnabled && card.matches(SPOT_SELECTOR);
    const trackBorder = borderEnabled && card.hasAttribute('data-sg-spotlight');
    if (!fill && !trackBorder) return;
    if (fill) card.classList.add('sg-spotlight');
    let frame = 0;
    let clientX = 0;
    let clientY = 0;
    pageOn(card, 'pointermove', event => {
      clientX = event.clientX;
      clientY = event.clientY;
      if (frame) return;
      frame = window.requestAnimationFrame(() => {
        frame = 0;
        const rect = card.getBoundingClientRect();
        const x = clientX - rect.left;
        const y = clientY - rect.top;
        if (fill) {
          card.style.setProperty('--spot-x', `${x.toFixed(1)}px`);
          card.style.setProperty('--spot-y', `${y.toFixed(1)}px`);
        }
        if (trackBorder) {
          card.style.setProperty('--sg-mx', `${x}px`);
          card.style.setProperty('--sg-my', `${y}px`);
        }
      });
    }, { passive: true });
    onPageDispose(() => { if (frame) window.cancelAnimationFrame(frame); });
  });
}
