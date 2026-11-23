import { pageOn, pageInterval, pageSignal, onPageDispose } from './lifecycle';
import { civilDate, dateKey, dayOrdinal, calendarFacts, fallbackFestival, loadCalendarLibrary, lunarFacts, DAY_MS } from './calendar-data';

type SidebarConfig = { timezone: string; detail: boolean; mode: 'off' | 'site' | 'visitor'; weather: boolean; endpoint: string; fallbackDates: string[]; initial: Context };
type LocationData = { status: string; label?: string; observedAt?: number; stale?: boolean; distance?: number | null; source?: string; ip?: string; province?: string; district?: string };
type WeatherData = { status: string; reason?: string; city?: string; condition?: string; temperature?: number; humidity?: number; feelsLike?: number; windDir?: string; windScale?: number; visibility?: number; icon?: string; observedAt?: string; fetchedAt?: number; stale?: boolean; attributions?: string[] };
type SunData = { status: string; date?: string; timezone?: string; city?: string; rise?: string; set?: string };
type Context = { location: LocationData; weather: WeatherData; sun: SunData; generatedAt: number };
const WEEKDAYS = ['日', '一', '二', '三', '四', '五', '六'];
const put = (root: ParentNode, selector: string, value: string): void => {
  const el = root.querySelector<HTMLElement>(selector);
  if (el && el.textContent !== value) el.textContent = value;
};
const validContext = (value: unknown): value is Context => {
  if (!value || typeof value !== 'object') return false;
  const data = value as Partial<Context>;
  return !!data.location && !!data.weather && !!data.sun && typeof data.generatedAt === 'number'
    && typeof data.location.status === 'string' && typeof data.weather.status === 'string' && typeof data.sun.status === 'string';
};

function weatherIcon(code: string): string {
  const number = Number(code);
  if (number >= 300 && number < 400) return 'rain';
  if (number >= 400 && number < 500) return 'snow';
  if (number >= 500 && number < 600) return 'fog';
  return number === 100 || number === 150 ? 'sun' : 'cloud';
}

export function initSidebarData(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-sidebar-data]');
  const script = root?.querySelector('[data-sg-sidebar-config]');
  if (!root || !script) return;
  let cfg: SidebarConfig;
  try { cfg = JSON.parse(script.textContent ?? ''); civilDate(new Date(), cfg.timezone); }
  catch { return; }
  const signal = pageSignal();
  const calendar = root.querySelector<HTMLElement>('[data-sg-calendar]');
  const countdown = root.querySelector<HTMLElement>('[data-sg-countdown]');
  const welcome = root.querySelector<HTMLElement>('[data-sg-welcome]');
  const weather = root.querySelector<HTMLElement>('[data-sg-weather]');
  let lastDay = '';
  let calendarLibrary: Awaited<ReturnType<typeof loadCalendarLibrary>> | null = null;
  let libraryLoading = false;
  let active = false;
  let fetching = false;
  let nextFetch = 0;
  let context: Context = cfg.initial;
  const cacheKey = `sg:sidebar-location:${cfg.endpoint}:${cfg.mode}`;

  const greetingOf = (hour: number): string => (hour >= 5 && hour < 11 ? '上午好' : hour >= 11 && hour < 13 ? '中午好' : hour >= 13 && hour < 18 ? '下午好' : hour >= 18 ? '晚上好' : '夜深了');
  const clockOf = (date: Date): string => `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;

  /** 源站欢迎语的高亮段：`<b>` + 主色。IPv6 在冒号后插 `<wbr>`，长地址能断行不撑破卡片。 */
  const strongOf = (text: string): HTMLElement => {
    const bold = document.createElement('b');
    bold.className = 'sg-hw-strong';
    if (!text.includes(':')) { bold.textContent = text; return bold; }
    text.split(':').forEach((part, index) => {
      if (index > 0) bold.append(':', document.createElement('wbr'));
      bold.append(part);
    });
    return bold;
  };

  let lastLocation: LocationData | null = null;

  // 2026-11-20：欢迎语对齐源站 —— 「来自 省 市 区 的访客，下午好，现在是 15:35，
  // 你目前距站长约 1063 公里，IP 地址：x.x.x.x」，一段话、高亮段走主色。
  // 时间随每分钟重绘一次（见 refresh），所以整句由 DOM 节点拼装而非 innerHTML。
  const paintLocation = (location: LocationData, cached = false): void => {
    if (!welcome) return;
    lastLocation = location;
    const line = welcome.querySelector<HTMLElement>('[data-sg-welcome-location]');
    const meta = welcome.querySelector<HTMLElement>('[data-sg-welcome-meta]');
    if (!line) return;
    if (location.status === 'ok' && typeof location.label === 'string') {
      const now = new Date();
      const parts: (string | Node)[] = ['来自 ', strongOf(location.label), ` 的访客，${greetingOf(now.getHours())}，现在是 `, strongOf(clockOf(now))];
      if (typeof location.distance === 'number') parts.push('，你目前距站长约 ', strongOf(String(location.distance)), ' 公里');
      if (typeof location.ip === 'string' && location.ip !== '') parts.push('，IP 地址：', strongOf(location.ip));
      line.replaceChildren(...parts);
      if (meta) {
        const stale = cached || location.stale === true;
        meta.hidden = !stale;
        put(welcome, '[data-sg-welcome-meta]', stale ? '上次定位结果，正在更新' : '');
      }
      return;
    }
    const site = location.status === 'site' && typeof location.label === 'string';
    put(welcome, '[data-sg-welcome-location]', site ? `来自${location.label}的一声问候` : '来自远方的朋友，欢迎来访');
    if (meta) meta.hidden = false;
    put(welcome, '[data-sg-welcome-meta]', site ? '站长所在城市' : cfg.mode === 'visitor' ? '位置暂不可用' : '愿你在这里找到喜欢的文字');
  };

  /** 底部虚线附加行：节气与日出日落都为空时整行收起（源站 extraHTML 无内容即返回空串）。 */
  const syncExtra = (): void => {
    const extra = root.querySelector<HTMLElement>('[data-sg-calendar-extra]');
    if (!extra) return;
    const term = extra.querySelector<HTMLElement>('[data-sg-calendar-term]');
    const sun = extra.querySelector<HTMLElement>('[data-sg-sun]');
    extra.hidden = !(term?.textContent ?? '').trim() && (!sun || sun.hidden);
  };

  const paintContext = (data: Context): void => {
    paintLocation(data.location);
    const sun = root.querySelector<HTMLElement>('[data-sg-sun]');
    if (sun) {
      sun.hidden = data.sun.status !== 'ok';
      if (!sun.hidden) {
        sun.title = `${data.sun.city ?? ''} · ${data.sun.timezone ?? ''} · ${data.sun.date ?? ''} · 天文估算`;
        put(sun, '[data-sg-sun-rise]', `☀️ ${data.sun.rise ?? '—'}`);
        put(sun, '[data-sg-sun-set]', `🌙 ${data.sun.set ?? '—'}`);
      }
    }
    syncExtra();
    if (!weather) return;
    const current = data.weather;
    const valid = current.status === 'ok' && typeof current.temperature === 'number' && typeof current.humidity === 'number' && typeof current.condition === 'string';
    // 2026-11-20：主行拆成「大温度 + 城市 / 天气」，数据行按接口实返字段拼 span（缺项直接不输出，
    // 旧缓存没有 feelsLike/wind 时不会留空占位）。一切文本走 textContent，不拼 innerHTML。
    put(weather, '[data-sg-weather-temp]', valid ? `${current.temperature}°` : '--');
    put(weather, '[data-sg-weather-main]', valid ? (current.city || '当前位置') : '天气暂不可用');
    put(weather, '[data-sg-weather-cond]', valid ? (current.condition ?? '') : '');
    let update = '';
    if (valid) {
      const time = new Date(current.observedAt || (current.fetchedAt ?? 0) * 1000);
      update = Number.isNaN(time.getTime()) ? '' : new Intl.DateTimeFormat('zh-CN', { timeZone: data.sun.timezone || cfg.timezone, hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(time);
    }
    const meta = weather.querySelector<HTMLElement>('[data-sg-weather-meta]');
    if (meta) {
      if (!valid) {
        put(weather, '[data-sg-weather-meta]', '稍后会自动重试');
      } else {
        meta.replaceChildren();
        const cell = (label: string, value: string): void => {
          const span = document.createElement('span');
          if (label) {
            const name = document.createElement('span');
            name.textContent = label;
            span.append(name);
          }
          const strong = document.createElement('b');
          strong.textContent = value;
          span.append(strong);
          meta.append(span);
        };
        if (typeof current.feelsLike === 'number') cell('体感 ', `${current.feelsLike}°`);
        cell('湿度 ', `${current.humidity}%`);
        if (typeof current.windScale === 'number' && current.windScale >= 0) cell('', `${current.windDir ?? ''} ${current.windScale}级`.trim());
        else if (current.windDir) cell('', current.windDir);
        if (update) {
          const span = document.createElement('span');
          span.textContent = `${current.stale ? '最近记录' : '更新'} ${update}`;
          meta.append(span);
        }
      }
    }
    weather.dataset.state = valid ? (current.stale ? 'stale' : 'ready') : 'error';
    // 失败原因只放在 data 属性上供站长排查（枚举值，无凭据信息），界面文案仍是「稍后会自动重试」。
    weather.dataset.reason = valid ? '' : (current.reason ?? '');
    weather.querySelectorAll<HTMLElement>('[data-sg-weather-icon]').forEach(icon => { icon.hidden = !valid || icon.dataset.sgWeatherIcon !== weatherIcon(current.icon ?? ''); });
    const credits = weather.querySelector<HTMLElement>('[data-sg-weather-attributions]');
    if (credits) {
      credits.replaceChildren();
      (current.attributions ?? []).forEach((url, index) => {
        if (typeof url !== 'string' || !url.startsWith('https://')) return;
        const link = document.createElement('a'); link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer';
        link.textContent = index === 0 ? '数据声明' : `声明 ${index + 1}`;
        credits.append(link);
      });
    }
  };

  const paintDates = (): void => {
    if (signal.aborted || !root.isConnected) return;
    const now = new Date();
    const date = civilDate(now, cfg.timezone);
    const key = dateKey(date);
    const facts = calendarFacts(date);
    if (calendar && key !== lastDay) {
      put(calendar, '[data-sg-calendar-day]', String(date.day).padStart(2, '0'));
      put(calendar, '[data-sg-calendar-ym]', `${date.year}年${date.month}月`);
      put(calendar, '[data-sg-calendar-week]', `第${facts.week}周 周${WEEKDAYS[facts.weekday]}`);
      put(calendar, '[data-sg-calendar-doy]', `第${facts.dayOfYear}天`);
      calendar.title = `${date.year}年${date.month}月${date.day}日 星期${WEEKDAYS[facts.weekday]}`;
      const table = calendar.querySelector<HTMLTableElement>('.sg-hcal-grid');
      const tbody = table?.tBodies[0];
      if (tbody) {
        table!.setAttribute('aria-label', `${date.year}年${date.month}月，${cfg.timezone}`);
        const first = new Date(dayOrdinal(date.year, date.month, 1) * DAY_MS).getUTCDay();
        const days = dayOrdinal(date.year, date.month + 1, 1) - dayOrdinal(date.year, date.month, 1);
        const fragment = document.createDocumentFragment();
        for (let row = 0; row < Math.ceil((first + days) / 7); row++) {
          const tr = document.createElement('tr');
          for (let col = 0; col < 7; col++) {
            const day = row * 7 + col - first + 1;
            const cell = document.createElement('td'); cell.className = 'sg-hcal-cell';
            if (day < 1 || day > days) cell.classList.add('is-empty');
            else {
              const label = document.createElement('span');
              label.textContent = String(day);
              cell.append(label);
              if (day === date.day) { cell.classList.add('is-today'); cell.setAttribute('aria-current', 'date'); }
            }
            tr.append(cell);
          }
          fragment.append(tr);
        }
        tbody.replaceChildren(fragment);
      }
    }
    let target = fallbackFestival(date, cfg.fallbackDates);
    if (calendarLibrary) {
      try {
        const lunar = lunarFacts(date, calendarLibrary);
        /* 节气名由服务端直出（与岁候页同一张表），前端不再算第二遍；
           这里只补农历与春节倒计时。 */
        if (cfg.detail && calendar) put(calendar, '[data-sg-calendar-lunar]', lunar.label);
        target = lunar.festival ?? target;
      } catch {
        if (calendar) put(calendar, '[data-sg-calendar-lunar]', '农历暂不可用');
      }
    }
    if (countdown) {
      put(countdown, '[data-sg-cd-date]', target ? (target.days === 0 ? '就是今天' : target.date) : '日期待更新');
      put(countdown, '[data-sg-cd-days]', target ? String(target.days) : '—');
      countdown.dataset.sgCdTarget = target?.date ?? '';
      countdown.querySelectorAll<HTMLElement>('.sg-hcd-row').forEach((row, index) => {
        const progress = facts.progress[index];
        const bar = row.querySelector<HTMLElement>('.sg-hcd-bar i');
        if (bar) bar.style.width = `${progress.percent}%`;
        put(row, 'em', `${progress.percent.toFixed(2)}%`);
        put(row, '[data-sg-cd-remaining]', `还剩 ${progress.remaining} 天`);
      });
    }
    syncExtra();
    lastDay = key;
    // 欢迎语含「现在是 HH:mm」，每分钟按当前时刻重绘（问候语也随时段切换）。
    if (welcome && lastLocation && lastLocation.status === 'ok') paintLocation(lastLocation);
  };

  const fetchContext = async (force = false): Promise<void> => {
    if ((!active && !force) || document.hidden || fetching || signal.aborted || (!force && Date.now() < nextFetch)) return;
    const url = new URL(cfg.endpoint, location.href);
    if (url.origin !== location.origin) return;
    if (force) url.searchParams.set('refresh', '1');
    const controller = new AbortController();
    const abort = (): void => controller.abort();
    signal.addEventListener('abort', abort, { once: true });
    const timeout = window.setTimeout(abort, 8500);
    fetching = true;
    if (weather) weather.setAttribute('aria-busy', 'true');
    nextFetch = Date.now() + 60000;
    try {
      // Unsupported browsers ignore priority; explicit user refresh keeps normal priority.
      const init: RequestInit & { priority?: 'low' } = { signal: controller.signal, cache: 'no-store', headers: { Accept: 'application/json' } };
      if (!force) init.priority = 'low';
      const response = await fetch(url, init);
      if (!response.ok) throw new Error('sidebar-response');
      const data: unknown = await response.json();
      if (signal.aborted || !root.isConnected) return;
      if (!validContext(data)) throw new Error('sidebar-payload');
      context = data; paintContext(data);
      nextFetch = Date.now() + (cfg.weather && data.weather.status !== 'ok' ? 60000 : cfg.weather ? Math.max(60000, 900000 - (Date.now() - (data.weather.fetchedAt ?? 0) * 1000)) : 43200000);
      if (data.location.status === 'ok') {
        try { localStorage.setItem(cacheKey, JSON.stringify({ at: (data.location.observedAt ?? data.generatedAt) * 1000, location: data.location })); } catch { /* optional */ }
      }
    } catch {
      if (signal.aborted || !root.isConnected) return;
      const oldWeather = context.weather;
      const recent = oldWeather.status === 'ok' && Date.now() - (oldWeather.fetchedAt ?? 0) * 1000 <= 7200000;
      const oldSun = context.sun;
      const sameSunDay = oldSun.status === 'ok' && oldSun.date && oldSun.timezone && dateKey(civilDate(new Date(), oldSun.timezone)) === oldSun.date;
      context = { ...context, location: { ...context.location, stale: true },
        weather: recent ? { ...oldWeather, stale: true } : { status: cfg.weather ? 'unavailable' : 'disabled' },
        sun: sameSunDay ? oldSun : { status: 'unavailable' } };
      paintContext(context);
    } finally {
      clearTimeout(timeout); signal.removeEventListener('abort', abort); fetching = false;
      if (!signal.aborted && root.isConnected) {
        weather?.removeAttribute('aria-busy');
      }
    }
  };

  const activate = (): void => {
    if (signal.aborted || !root.isConnected) return;
    active = true;
    if (!libraryLoading) {
      libraryLoading = true;
      void loadCalendarLibrary().then(library => {
        if (!signal.aborted && root.isConnected) { calendarLibrary = library; paintDates(); }
      }).catch(() => { if (!signal.aborted && calendar) put(calendar, '[data-sg-calendar-lunar]', '农历暂不可用'); });
    }
    if (cfg.mode === 'visitor' || cfg.weather) void fetchContext();
  };
  paintContext(context); paintDates();
  if (cfg.mode === 'visitor') {
    try {
      const cached = JSON.parse(localStorage.getItem(cacheKey) ?? 'null');
      if (cached && typeof cached.at === 'number' && Date.now() - cached.at < 43200000 && cached.location?.status === 'ok') paintLocation(cached.location, true);
    } catch { /* optional */ }
  }
  const watches = [calendar, welcome, weather].filter((node): node is HTMLElement => !!node);
  if (typeof IntersectionObserver !== 'undefined') {
    const visible = new Set<Element>();
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => { if (entry.isIntersecting) visible.add(entry.target); else visible.delete(entry.target); });
      active = visible.size > 0;
      if (active) activate();
    }, { rootMargin: '150px' });
    watches.forEach(node => observer.observe(node)); onPageDispose(() => observer.disconnect());
  } else activate();
  const refresh = (): void => {
    if (document.hidden || signal.aborted) return;
    paintDates();
    const sun = context.sun;
    if (sun.status === 'ok' && sun.timezone && sun.date && dateKey(civilDate(new Date(), sun.timezone)) !== sun.date) nextFetch = 0;
    if (active && (cfg.mode === 'visitor' || cfg.weather || sun.status === 'ok')) void fetchContext();
  };
  pageInterval(refresh, 60000);
  pageOn(document, 'visibilitychange', () => { if (!document.hidden) { nextFetch = Math.min(nextFetch, Date.now()); refresh(); } });
  pageOn(window, 'pageshow', refresh);
  pageOn(window, 'focus', () => { nextFetch = Math.min(nextFetch, Date.now()); refresh(); });
  pageOn(window, 'online', () => { nextFetch = 0; refresh(); });
}
