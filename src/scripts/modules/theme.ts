import { pageOn } from './lifecycle';
import { toast } from './feedback';

const THEME_KEY = 'sg-theme';

export const isDark = (): boolean => document.documentElement.classList.contains('dark');

/** 首页昼夜卡的文案随主题切换（圆盘与配色由 CSS 的 html.dark 驱动） */
function syncThemeCopy(): void {
  const dark = isDark();
  document.querySelectorAll<HTMLElement>('[data-sg-theme-title]').forEach((el) => {
    el.textContent = dark ? '日间模式' : '夜间模式';
  });
  document.querySelectorAll<HTMLElement>('[data-sg-theme-sub]').forEach((el) => {
    el.textContent = dark ? '樱花漫舞的白昼' : '流萤飞舞的深空';
  });
}

/** View Transitions 的最小类型（与 navigation.ts 的写法保持一致） */
const VT_API = document as unknown as {
  startViewTransition?: (update: () => void) => { finished: Promise<void> };
};

/** 揭幕圆心：取被点按钮的中心；取不到（键盘触发 / 无 currentTarget）时退回视口中心 */
function dawnOrigin(ev?: MouseEvent): { x: number; y: number } {
  const el = (ev?.currentTarget ?? null) as HTMLElement | null;
  const box = el?.getBoundingClientRect();
  if (box && box.width > 0 && box.height > 0) {
    return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
  }
  return { x: window.innerWidth / 2, y: window.innerHeight / 2 };
}

/** 防连点：旧转场的 finished 迟到时不能摘掉新转场加的 class */
let dawnSeq = 0;

/**
 * 切换昼夜主题：html.dark 翻转 + localStorage 持久化。
 * （第二十四轮曾移除「日落转场」triggerSky；第九十一轮按新方案重新引入——
 *   载体换成 View Transitions，形态是「从按钮位置扩圆的揭幕」，
 *   不支持该 API 或用户要求减少动效时，仍回到纯配色渐变。）
 */
export function toggleTheme(ev?: MouseEvent): void {
  const dark = !isDark();
  const root = document.documentElement;
  const apply = (): void => {
    root.classList.toggle('dark', dark);
    // header.php 的内联脚本按裸字符串读取，这里必须写裸值，不能 JSON 序列化
    try {
      window.localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');
    } catch {
      /* 隐私模式忽略 */
    }
    syncThemeCopy();
  };
  const start = VT_API.startViewTransition;
  const say = (): void => toast(dark ? '已切换到夜间模式' : '已切换到日间模式');

  if (typeof start !== 'function' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    apply();
    say();
    return;
  }

  const { x, y } = dawnOrigin(ev);
  root.style.setProperty('--sg-dawn-x', `${x}px`);
  root.style.setProperty('--sg-dawn-y', `${y}px`);
  // sg-dawn 同时负责「关掉全站颜色过渡」：否则新帧快照会拍到过渡起始色，
  // 露出「底色还是白天、文字已是夜间」的错位。
  root.classList.add('sg-dawn');
  const seq = ++dawnSeq;
  const done = (): void => {
    if (seq !== dawnSeq) return;
    root.classList.remove('sg-dawn');
    say();
  };
  let vt: { finished: Promise<void> } | undefined;
  try {
    vt = start.call(document, apply);
  } catch {
    /* 极端环境下 API 存在但不可用：摘掉 sg-dawn，退回纯配色切换 */
    root.classList.remove('sg-dawn');
    apply();
    say();
    return;
  }
  // 转场被跳过时 finished 会 reject，吞掉它再收尾，避免未处理异常
  void vt.finished.catch(() => undefined).then(done);
}

export function initTheme(): void {
  syncThemeCopy();
  document.querySelectorAll<HTMLElement>('[data-sg-theme-toggle]').forEach((btn) => {
    pageOn(btn, 'click', toggleTheme);
  });
}
