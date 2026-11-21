/// <reference types="vite/client" />

/** 由 lib/assets.php 的 sg_runtime_config() 注入到 window.SG */
interface SGTrack {
  id?: string;
  title?: string;
  artist?: string;
  url?: string;
  cover?: string;
  /** 单行歌词（没有 LRC 时兜底显示） */
  lyric?: string;
  /** LRC 原文，player.ts 自己解析时间轴 */
  lrc?: string;
}

interface SGConfig {
  themeUrl: string;
  bgImages: string[];
  themeColors: string[];
  danmaku: string[];
  effects: boolean;
  clickEffect: boolean;
  /** 季节粒子，可为英文逗号组合（如 `snow,fireflies`）；effects.ts 负责拆包与白名单过滤 */
  season: string;
  playlist: SGTrack[];
  nav: { name: string; href: string }[];
  /** 内置占位图（dist/img/ph-*.svg）：外链图片加载失败时前端自动换成它 */
  placeholder: string;
}

interface Window {
  SG?: Partial<SGConfig>;
}
