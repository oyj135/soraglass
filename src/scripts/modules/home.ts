import { pageOn,pageFrame,pageTimeout } from './lifecycle';
import { isSmallScreen,reducedMotion } from './runtime';
/**
 * 首页首屏交互（2026-11-16，参考 Fomalhaut 滚动导航）：
 *   DOM 契约（partials/home-front.php / side-countdown.php）：
 *     [data-sg-front]          100vh 首屏容器
 *     [data-sg-front-next]     向下滚动按钮
 *     [data-sg-nav]            顶栏（core.ts 已在管 is-hidden，这里只加透明态）
 *     [data-sg-countdown]      春节倒计时卡（可选）
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

/** 春节倒计时：按本地今天刷新天数与进度（服务端已渲染首帧，这里只在跨天时更新）。 */
function initCountdown(): void {
  const card = document.querySelector<HTMLElement>('[data-sg-countdown]');
  if (!card) return;
  const targetRaw = card.dataset.sgCdTarget;
  if (!targetRaw) return;
  const target = new Date(targetRaw + 'T00:00:00');
  if (Number.isNaN(target.getTime())) return;

  const daysEl = card.querySelector<HTMLElement>('[data-sg-cd-days]');
  const refresh = (): void => {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const diff = Math.max(0, Math.ceil((target.getTime() - today.getTime()) / 86400000));
    if (daysEl) daysEl.textContent = String(diff);

    const yearStart = new Date(now.getFullYear(), 0, 1);
    const yearEnd = new Date(now.getFullYear() + 1, 0, 1);
    const monthEnd = new Date(now.getFullYear(), now.getMonth() + 1, 1);
    const weekPct = Math.round((now.getDay() / 7) * 100);
    const pcts = [
      Math.round(((today.getTime() - yearStart.getTime()) / (yearEnd.getTime() - yearStart.getTime())) * 100),
      Math.round(((today.getTime() - new Date(now.getFullYear(), now.getMonth(), 1).getTime()) / (monthEnd.getTime() - new Date(now.getFullYear(), now.getMonth(), 1).getTime())) * 100),
      weekPct,
    ];
    const bars = card.querySelectorAll<HTMLElement>('.sg-hcd-row');
    bars.forEach((row, i) => {
      const fill = row.querySelector<HTMLElement>('.sg-hcd-bar i');
      const label = row.querySelector<HTMLElement>('em');
      if (fill) fill.style.width = pcts[i] + '%';
      if (label) label.textContent = pcts[i] + '%';
    });
  };
  refresh();
}

/** 首屏标语 · Text Scramble 文字解码（第十六轮 P1，替换原打字机，源自「16 种 UI 动效」清单第 7 项）：
    标语先整句变成随机字符，再按随机进度从左到右逐字还原；多行标语循环时
    旧句随机化湮灭 → 新句解码进场。参考 Codrops TextScramble 的帧驱动模型。
    随机字符池以全角假名为主（与中文标语等宽，解码抖动最小），可按口味调整。
    减少动效时保留服务端渲染的静态全文；静态全文与视觉解码层分开。 */
const SG_SCRAMBLE_CHARS = 'アイウエオカキクケコサシスセソタチツテトナニヌネノハヒフヘホ<>*+-=#01';

function initScramble(): void {
  const el = document.querySelector<HTMLElement>('.sg-front-sub[data-sg-typewriter]');
  if (!el || reducedMotion()) return;
  const strings = (el.dataset.sgTypewriter ?? '')
    .split(/\r?\n/)
    .map(s => s.trim())
    .filter(s => s !== '');
  if (strings.length === 0) return;

  el.textContent = '';
  const accessibleText = document.createElement('span');
  accessibleText.className = 'sr-only';
  accessibleText.textContent = strings.join(' ');
  const text = document.createElement('span');
  text.className = 'sg-front-type-text';
  text.setAttribute('aria-hidden', 'true');
  const caret = document.createElement('span');
  caret.className = 'sg-front-caret';
  caret.setAttribute('aria-hidden', 'true');
  el.append(accessibleText, text, caret);

  const randomChar = (): string => SG_SCRAMBLE_CHARS[Math.floor(Math.random() * SG_SCRAMBLE_CHARS.length)];
  /** str 的第 resolvedFrom 位之前为已还原字符，其余渲染成随机字符（空格保持空格防抖动） */
  const render = (str: string, resolvedFrom: number): void => {
    let out = '';
    for (let i = 0; i < str.length; i++) {
      const ch = str[i];
      out += (ch === ' ' || i < resolvedFrom) ? ch : randomChar();
    }
    text.textContent = out;
  };

  const STEP = 70;            // ≈14fps 的解码节奏（第十八轮：整段解码放缓到 3-4s）
  const IN_BASE = 12;         // 解码先整句乱码 12 帧（≈0.8s）再开始逐字还原
  const IN_JITTER = 8;        // 每字还原帧的随机抖动（解码感的杂乱来源）
  const OUT_FRAMES = 12;      // 旧句湮灭时长
  const HOLD_FRAMES = 75;     // 解码完成后停留 ≈5.3s

  let si = 0;
  let frame = 0;
  let endAt: number[] = [];
  let phase: 'in' | 'hold' | 'out' = 'in';
  let outLeft = 0;

  const arm = (str: string): void => {
    endAt = str.split('').map((_, i) => IN_BASE + Math.round(i * 1.9) + Math.floor(Math.random() * IN_JITTER));
    frame = 0;
    phase = 'in';
  };
  arm(strings[0]);

  const step = (): void => {
    if (!el.isConnected) return;
    const str = strings[si];
    frame += 1;

    if (phase === 'in') {
      /* endAt 递增序列：从左到右逐字锁定，锁定前该位显示随机字符 */
      let resolved = 0;
      while (resolved < endAt.length && frame >= endAt[resolved]) resolved += 1;
      render(str, resolved);
      if (resolved >= str.length) {
        text.textContent = str;
        phase = 'hold';
        if (strings.length === 1) return;   // 单条标语解码完成即定格
      }
      pageTimeout(step, STEP);
      return;
    }
    if (phase === 'hold') {
      if (frame < HOLD_FRAMES) { pageTimeout(step, STEP); return; }
      phase = 'out';
      outLeft = OUT_FRAMES;
      render(str, 0);                        // 整句随机化（湮灭开始）
      pageTimeout(step, STEP);
      return;
    }
    /* out：旧句随机化湮灭完毕 → 解码下一句 */
    outLeft -= 1;
    if (outLeft > 0) { render(str, 0); pageTimeout(step, STEP); return; }
    si = (si + 1) % strings.length;
    arm(strings[si]);
    pageTimeout(step, STEP);
  };
  pageTimeout(step, STEP);
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
  initCountdown();
  initScramble();
}
