/** Civil dates are ordinal days, never elapsed local-midnight milliseconds. */
export type CivilDate = { year: number; month: number; day: number; hour: number; minute: number; second: number };
export type CalendarProgress = { percent: number; remaining: number };
export const DAY_MS = 86400000;
export const dayOrdinal = (year: number, month: number, day: number): number => Date.UTC(year, month - 1, day) / DAY_MS;
export const dateKey = (date: Pick<CivilDate, 'year' | 'month' | 'day'>): string =>
  `${date.year}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}`;

export function civilDate(now: Date, timeZone: string): CivilDate {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone, year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
  }).formatToParts(now);
  const value = (name: string): number => Number(parts.find(part => part.type === name)?.value ?? 0);
  return { year: value('year'), month: value('month'), day: value('day'), hour: value('hour'), minute: value('minute'), second: value('second') };
}

export function calendarFacts(date: CivilDate): { weekday: number; week: number; weekYear: number; dayOfYear: number; progress: CalendarProgress[] } {
  const today = dayOrdinal(date.year, date.month, date.day);
  const weekday = new Date(today * DAY_MS).getUTCDay();
  const monday = today - (weekday + 6) % 7;
  const thursday = new Date((monday + 3) * DAY_MS);
  const weekYear = thursday.getUTCFullYear();
  const jan4 = dayOrdinal(weekYear, 1, 4);
  const firstMonday = jan4 - (new Date(jan4 * DAY_MS).getUTCDay() + 6) % 7;
  const fraction = (date.hour * 3600 + date.minute * 60 + date.second) / 86400;
  const progress = [
    [dayOrdinal(date.year, 1, 1), dayOrdinal(date.year + 1, 1, 1)],
    [dayOrdinal(date.year, date.month, 1), dayOrdinal(date.year, date.month + 1, 1)],
    [monday, monday + 7],
  // 两位小数：与源站 (Math.round(pct * 10000) / 100) 同值，PHP 首帧用同一取整规则。
  ].map(([start, end]) => ({ percent: Math.min(100, Math.max(0, Math.round((today + fraction - start) / (end - start) * 10000) / 100)), remaining: end - today - 1 }));
  return { weekday, week: 1 + (monday - firstMonday) / 7, weekYear, dayOfYear: today - dayOrdinal(date.year, 1, 1) + 1, progress };
}

export function fallbackFestival(date: CivilDate, dates: string[]): { date: string; days: number } | null {
  const today = dayOrdinal(date.year, date.month, date.day);
  for (const raw of [...dates].sort()) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) continue;
    const [year, month, day] = raw.split('-').map(Number);
    const ordinal = dayOrdinal(year, month, day);
    if (ordinal >= today) return { date: raw, days: ordinal - today };
  }
  return null;
}

type LunarLibrary = typeof import('lunar-javascript');
let lunarPromise: Promise<LunarLibrary> | undefined;
/** Loaded once, after the sidebar enters view; main/first-screen stay lightweight. */
export function loadCalendarLibrary(): Promise<LunarLibrary> {
  if (!lunarPromise) lunarPromise = import('lunar-javascript').catch(error => { lunarPromise = undefined; throw error; });
  return lunarPromise;
}

export function lunarFacts(date: CivilDate, library: LunarLibrary): { label: string; term: string; termName: string; festival: { date: string; days: number } | null } {
  // Explicit supported display range; outside it keep the Gregorian card useful.
  if (date.year < 1900 || date.year > 2100) throw new RangeError('calendar-range');
  const solar = library.Solar.fromYmd(date.year, date.month, date.day);
  const lunar = solar.getLunar();
  const today = dayOrdinal(date.year, date.month, date.day);
  let festival: { date: string; days: number } | null = null;
  for (let year = lunar.getYear(); year <= lunar.getYear() + 2; year++) {
    const target = library.Lunar.fromYmd(year, 1, 1).getSolar();
    const days = dayOrdinal(target.getYear(), target.getMonth(), target.getDay()) - today;
    if (days >= 0) { festival = { date: target.toYmd(), days }; break; }
  }
  const currentTerm = lunar.getJieQi();
  const next = lunar.getNextJieQi(true);
  const termDate = next.getSolar();
  const termDays = dayOrdinal(termDate.getYear(), termDate.getMonth(), termDate.getDay()) - today;
  const termName = currentTerm || next.getName();
  return {
    // 源站：gzYear + Animal + '年 ' + IMonthCn + IDayCn（空格分隔，不插中点）
    label: `${lunar.getYearInGanZhi()}${lunar.getYearShengXiao()}年 ${lunar.getMonthInChinese()}月${lunar.getDayInChinese()}`,
    // 源站：'今天就是X' / 'N天后是X'，节气名单独加粗（cal-term 的 em）
    term: currentTerm ? `今天就是${currentTerm}` : `${termDays}天后是${next.getName()}`,
    termName,
    festival,
  };
}
