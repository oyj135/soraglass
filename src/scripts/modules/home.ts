import { pageOn,pageFrame,pageTimeout } from './lifecycle';
import { isSmallScreen,reducedMotion } from './runtime';
/**
 * 首页首屏交互（2026-11-16，参考 Fomalhaut 滚动导航）：
 *   DOM 契约（partials/home-front.php）：
 *     [data-sg-front]          100vh 首屏容器
 *     [data-sg-front-next]     向下滚动按钮
 *     [data-sg-typewriter]     副标题（属性值是打字机配置 JSON）
 *     [data-sg-nav]            顶栏（core.ts 已在管 is-hidden，这里只加透明态）
 *
 * 全部按挂点自判存在，缺失就空跑；只作用于首页，不碰其它页面导航。
 */

/** 首屏滚动：顶部时导航透明+白字，离开首屏后恢复实底（由 CSS 默认样式负责）。 */
function initFrontNav(): void {
  const front = document.querySelector<HTMLElement>('[data-sg-front]');
  const nav = document.querySelector<HTMLElement>('[data-sg-nav]');
  if (!front || !nav) return;

  let ticking = false;
  const sync = (): void => {
    const threshold = Math.max(80, front.offsetHeight - nav.offsetHeight - 24);
    nav.classList.toggle('is-front', window.scrollY < threshold);
    ticking = false;
  };
  pageOn(window, 'scroll', () => {
    if (ticking) return;
    ticking = true;
    pageFrame(sync);
  }, { passive: true });
  sync();

  // 向下按钮：平滑滚到文章列表区（没有则滚到正文顶部）
  const next = front.querySelector<HTMLElement>('[data-sg-front-next]');
  const target = document.querySelector<HTMLElement>('#sg-home-content') ?? document.querySelector<HTMLElement>('.sg-home-body');
  next && pageOn(next, 'click', () => {
    if (!target) return;
    target.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
  });
  const read = front.querySelector<HTMLAnchorElement>('[data-sg-front-read]');
  read && pageOn(read, 'click', event => {
    if (!target) return;
    event.preventDefault();
    target.tabIndex = -1;
    target.focus({ preventScroll: true });
    target.scrollIntoView({ behavior: reducedMotion() ? 'auto' : 'smooth', block: 'start' });
  });
}

/** 首屏标语 · 打字机（2026-11-20，替换原 Text Scramble 解码）：
    逐字打出 → 整句停留 → 逐字删除 → 下一条，loop 打开时末条回到第一条。
    文案与节奏全部来自后台「首屏打字机」JSON（partials/home-front.php 解析并以
    data-sg-typewriter 下发），后台留空时回落到站点副标题。
    逐字以「字素」为单位切分（Intl.Segmenter）：emoji、带修饰符/ZWJ 的组合表情
    不会被拆成半个字符；旧环境退回按码点切分。
    减少动效时不建 DOM、直接显示第一条，保留服务端渲染的静态文本。 */
type TypingConfig = { lines: string[]; loop: boolean; speed: number; hold: number; erase: number };

type GraphemeSegmenter = new (locale?: string, options?: { granularity: 'grapheme' }) => {
  segment(input: string): Iterable<{ segment: string }>;
};

const SG_TYPING_DEFAULT: TypingConfig = { lines: [], loop: true, speed: 90, hold: 2200, erase: 40 };

function readTypingConfig(raw: string): TypingConfig | null {
  let data: unknown;
  try {
    data = JSON.parse(raw);
  } catch {
    const lines = raw.split(/\r?\n/).map(line => line.trim()).filter(line => line !== '');
    return lines.length > 0 ? { ...SG_TYPING_DEFAULT, lines } : null;
  }
  const source = Array.isArray(data) ? { lines: data } : data;
  if (!source || typeof source !== 'object') return null;
  const bag = source as Record<string, unknown>;
  const lines = (Array.isArray(bag.lines) ? bag.lines : [])
    .filter((line): line is string => typeof line === 'string')
    .map(line => line.trim())
    .filter(line => line !== '')
    .slice(0, 6);
  if (lines.length === 0) return null;
  const clamp = (value: unknown, min: number, max: number, fallback: number): number =>
    typeof value === 'number' && Number.isFinite(value) ? Math.min(max, Math.max(min, Math.round(value))) : fallback;
  return {
    lines,
    loop: bag.loop === undefined ? true : Boolean(bag.loop),
    speed: clamp(bag.speed, 30, 400, 90),
    hold: clamp(bag.hold, 400, 20000, 2200),
    erase: clamp(bag.erase, 10, 300, 40),
  };
}

const SG_SEGMENTER = (Intl as unknown as { Segmenter?: GraphemeSegmenter }).Segmenter;

function splitGraphemes(text: string): string[] {
  if (SG_SEGMENTER) {
    try {
      return Array.from(new SG_SEGMENTER(undefined, { granularity: 'grapheme' }).segment(text), part => part.segment);
    } catch { /* Segmenter 不支持 grapheme 时退回码点 */ }
  }
  return Array.from(text);
}

function initTypewriter(): void {
  const el = document.querySelector<HTMLElement>('.sg-front-sub[data-sg-typewriter]');
  const config = el ? readTypingConfig(el.dataset.sgTypewriter ?? '') : null;
  if (!el || !config) return;
  if (reducedMotion()) {
    el.textContent = config.lines[0];
    return;
  }

  /* 逐字打出时行数会从 1 行涨到多行，下方的社交图标会被一路顶下去。
     先用最长一条量出高度锁成 min-height（此处字体已按首屏字号解析），后续打字不再重排。 */
  const longest = config.lines.reduce((a, b) => (b.length > a.length ? b : a), '');
  el.textContent = longest;
  const reserve = el.offsetHeight;
  el.textContent = '';
  if (reserve > 0) el.style.minHeight = `${reserve}px`;

  const accessibleText = document.createElement('span');
  accessibleText.className = 'sr-only';
  accessibleText.textContent = config.lines.join(' ');
  const text = document.createElement('span');
  text.className = 'sg-front-type-text';
  text.setAttribute('aria-hidden', 'true');
  const caret = document.createElement('span');
  caret.className = 'sg-front-caret';
  caret.setAttribute('aria-hidden', 'true');
  el.append(accessibleText, text, caret);

  let index = 0;
  let chars = splitGraphemes(config.lines[0]);
  let typed = 0;
  let phase: 'typing' | 'hold' | 'erasing' = 'typing';

  const paint = (): void => { text.textContent = chars.slice(0, typed).join(''); };

  const step = (): void => {
    if (!el.isConnected) return;
    if (document.hidden) { pageTimeout(step, 600); return; }   // 后台标签页不烧 CPU
    if (phase === 'typing') {
      typed = Math.min(chars.length, typed + 1);
      paint();
      if (typed >= chars.length) {
        phase = 'hold';
        pageTimeout(step, config.hold);
        return;
      }
      // ±35% 的随机抖动，避免节奏像节拍器
      pageTimeout(step, config.speed + Math.round(Math.random() * config.speed * 0.35));
      return;
    }
    if (phase === 'hold') {
      // 不循环时停在最后一条，光标继续闪，不再清屏
      if (!config.loop && index >= config.lines.length - 1) return;
      phase = 'erasing';
      pageTimeout(step, Math.max(140, config.erase * 2));
      return;
    }
    typed = Math.max(0, typed - 1);
    paint();
    if (typed === 0) {
      index = (index + 1) % config.lines.length;
      chars = splitGraphemes(config.lines[index]);
      phase = 'typing';
      pageTimeout(step, 420);
      return;
    }
    pageTimeout(step, config.erase);
  };
  pageTimeout(step, config.speed);
}

/** 首屏视差（第十六轮 P1，源自「16 种 UI 动效」清单第 9 项）：
    内容层以 0.28 倍速上移（比页面慢，产生「背景钉住、内容慢走」的视差感）并随滚动渐隐；
    遮罩深度 0.30 → 0.50 随滚动加深，正文接管前把首屏压暗，过渡更自然。
    源站的视差 = background-attachment:fixed + 30% 黑遮罩（head.styl）；
    本主题壁纸层本就是 fixed（视差主体已具备），这里补内容层动效与遮罩。
    减少动效 / 触屏 / 小屏时全部禁用，遮罩保持静态 0.30（源站同值）。 */
function initFrontParallax(): void {
  const front = document.querySelector<HTMLElement>('[data-sg-front]');
  const inner = front?.querySelector<HTMLElement>('.sg-front-inner');
  if (!front || !inner) return;
  if (reducedMotion() || isSmallScreen() || !window.matchMedia('(pointer: fine)').matches) return;

  let ticking = false;
  const sync = (): void => {
    const y = window.scrollY;
    const span = Math.max(1, front.offsetHeight * 0.9);
    const p = Math.min(1, Math.max(0, y / span));
    inner.style.transform = `translate3d(0, ${(y * 0.28).toFixed(1)}px, 0)`;
    inner.style.opacity = (1 - p * 0.85).toFixed(3);
    front.style.setProperty('--sg-front-veil', (0.3 + p * 0.2).toFixed(3));
    ticking = false;
  };
  pageOn(window, 'scroll', () => {
    if (ticking) return;
    ticking = true;
    pageFrame(sync);
  }, { passive: true });
  sync();
}

export function initHome(): void {
  initFrontNav();
  initFrontParallax();
  initTypewriter();
}
