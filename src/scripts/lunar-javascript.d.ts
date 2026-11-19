declare module 'lunar-javascript' {
  interface SolarDate {
    getYear(): number;
    getMonth(): number;
    getDay(): number;
    toYmd(): string;
    getLunar(): LunarDate;
  }
  interface LunarDate {
    getYear(): number;
    getYearInGanZhi(): string;
    getYearShengXiao(): string;
    getMonthInChinese(): string;
    getDayInChinese(): string;
    getSolar(): SolarDate;
    getJieQi(): string;
    getNextJieQi(wholeDay?: boolean): { getName(): string; getSolar(): SolarDate };
  }
  export const Solar: { fromYmd(year: number, month: number, day: number): SolarDate };
  export const Lunar: { fromYmd(year: number, month: number, day: number): LunarDate };
}
