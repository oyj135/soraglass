/**
 * Uiverse / bhaveshxrawat 的旋转色带改为透明中部的细边框。
 * 独立装饰层不占用卡片的伪元素，不改内容层级、尺寸与点击行为。
 */
const CARD_SELECTOR = '.sg-glass, .sg-lyric-bar, .sg-poster-wide, .sg-reading-list, .sg-photo, .sg-px-frame';

export function initCardBorders(): void {
  const cards = Array.from(document.querySelectorAll<HTMLElement>(CARD_SELECTOR));
  if (!cards.length) return;

  const visible = typeof IntersectionObserver === 'undefined' ? null : new IntersectionObserver((entries) => {
    entries.forEach(({ target, isIntersecting }) => target.classList.toggle('is-rim-visible', isIntersecting));
  });

  cards.forEach((card, index) => {
    if (card.querySelector(':scope > .sg-card-rim')) return;
    if (getComputedStyle(card).position === 'static') card.classList.add('sg-rim-anchor');
    card.classList.add('sg-rim-host');
    card.style.setProperty('--sg-rim-delay', `${-(index % 9) / 3}s`);
    const rim = document.createElement('span');
    rim.className = 'sg-card-rim';
    rim.setAttribute('aria-hidden', 'true');
    // 放在开头，保留阅读列表最后一项的 :last-child 分隔线规则。
    card.prepend(rim);
    if (visible) visible.observe(card);
    else card.classList.add('is-rim-visible');
  });
  const syncVisibility = (): void => {
    document.documentElement.classList.toggle('sg-rims-paused', document.hidden);
  };
  document.addEventListener('visibilitychange', syncVisibility);
  syncVisibility();
}
