const DEFAULTS: SGConfig = {
  themeUrl: '',
  bgImages: [],
  themeColors: ['#252229', '#919795', '#d5d2c6', '#f9f2e0'], // 东方美：玄墨→灰青→墨晕→纸白
  danmaku: [],
  effects: true,
  clickEffect: true,
  season: 'none',
  playlist: [],
  nav: [],
  placeholder: '',
};

export const config = (): SGConfig => ({ ...DEFAULTS, ...(window.SG ?? {}) });

export const reducedMotion = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

export const isSmallScreen = (): boolean => window.innerWidth < 900;

/** 本地存储读写都包一层，隐私模式下 silent fail */
export function readStore<T>(key: string, fallback: T): T {
  try {
    const raw = window.localStorage.getItem(key);
    return raw === null ? fallback : (JSON.parse(raw) as T);
  } catch {
    return fallback;
  }
}

export function writeStore(key: string, value: unknown): void {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* 隐私模式忽略 */
  }
}
