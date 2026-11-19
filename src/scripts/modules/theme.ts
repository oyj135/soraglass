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

/**
 * 切换昼夜主题：html.dark 翻转 + localStorage 持久化。
 * （第二十四轮：原「日落转场」triggerSky 已按用户要求移除，转场交回纯配色切换。）
 */
export function toggleTheme(): void {
  const dark = !isDark();
  document.documentElement.classList.toggle('dark', dark);
  // header.php 的内联脚本按裸字符串读取，这里必须写裸值，不能 JSON 序列化
  try {
    window.localStorage.setItem(THEME_KEY, dark ? 'dark' : 'light');
  } catch {
    /* 隐私模式忽略 */
  }
  syncThemeCopy();
  toast(dark ? '已切换到夜间模式' : '已切换到日间模式');
}

export function initTheme(): void {
  syncThemeCopy();
  document.querySelectorAll<HTMLElement>('[data-sg-theme-toggle]').forEach((btn) => {
    pageOn(btn, 'click', toggleTheme);
  });
}
