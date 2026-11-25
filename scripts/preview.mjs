/**
 * 本地视觉预览生成器。
 *
 * 本地没有 PHP CLI，无法直接跑 Typecho 渲染模板；这里按模板的 DOM 结构生成
 * 静态 HTML（class 名与 partials/*.php 完全一致），用真实构建产物核对视觉。
 *
 * 用法：npm run build && node scripts/preview.mjs
 * 产物：dist/preview-index.html、dist/preview-post.html（dist 为临时目录，release 时一并清理）
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const dist = path.join(root, 'dist');
const manifestPath = path.join(dist, '.vite/manifest.json');

if (!fs.existsSync(manifestPath)) {
  console.error('未找到 dist/.vite/manifest.json，请先执行 npm run build');
  process.exit(1);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const entry = manifest['src/scripts/main.ts'];
const css = (entry?.css ?? []).map((f) => `./${f}`);
const js = entry?.file ? `./${entry.file}` : '';

const IMG = {
  bg: './img/bg-1.svg',
  bg2: './img/bg-1.svg',
  avatar: './img/avatar.svg',
  cover: './img/cover-default.svg',
};

const runtime = {
  themeUrl: './',
  bgImages: [IMG.bg, IMG.bg],
  themeColors: ['#a18cd1', '#fbc2eb', '#a1c4fd', '#c2e9fb'],
  danmaku: ['空璃', '今日也要好好写代码', '毛玻璃调好了吗'],
  effects: true,
  clickEffect: true,
  season: 'sakura',
  placeholder: './img/ph-2.svg',
  playlist: [
    { title: '夜色与静电', artist: '空璃音乐盒', lyric: '风穿过玻璃的声音', url: '', cover: IMG.cover },
  ],
  nav: [],
};

const icons = {
  arrowLeft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>',
  arrowUp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>',
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
  tag: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h6l8 8-6 6-8-8z"/><circle cx="8" cy="9" r="1.2"/></svg>',
  music: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="7" cy="17" r="3"/><circle cx="18" cy="15" r="3"/><path d="M10 17V6l11-2v11"/></svg>',
  sun: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg>',
  moon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 14a8 8 0 1 1-10-10 7 7 0 0 0 10 10z"/></svg>',
  play: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5l12 7-12 7z"/></svg>',
  pause: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5h4v14H7zM13 5h4v14h-4z"/></svg>',
  prev: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5h2v14H7zM19 5l-10 7 10 7z"/></svg>',
  next: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15 5h2v14h-2zM5 5l10 7-10 7z"/></svg>',
  chevronDown: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
  chevronLeft: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>',
  chevronRight: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
  folder: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h6l2 2h10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>',
  github: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-2c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1-1.4-1-1.4-.9-.6 0-.6 0-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.7.4-1.1.7-1.4-2.2-.2-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.3 9.3 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.8-4.6 5 .4.3.7 1 .7 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2z"/></svg>',
  /* 社交品牌图标：多色填充（2026-11-19，与 lib/icons.php sg_social_svg() 同源自绘） */
  wechat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.6 3.3c-3.7 0-6.7 2.9-6.7 6.5 0 2 .9 3.8 2.4 5L4.6 17.9l3.3-1.6c.5.1 1.1.2 1.7.2 3.7 0 6.7-2.9 6.7-6.7s-3-6.5-6.7-6.5z" fill="#2DC100"/><circle cx="7.4" cy="8.7" r="1.05" fill="#fff"/><circle cx="11.8" cy="8.7" r="1.05" fill="#fff"/><path d="M14 19.6l-.8 2.3 2.6-1.3c.3 0 .7.1 1.1.1 2.6 0 4.8-1.8 4.8-4.1s-2.2-4.1-4.8-4.1-4.8 1.8-4.8 4.1c0 1.2.6 2.3 1.5 3z" fill="#9BE45C" stroke="#fff" stroke-width="1.1" stroke-linejoin="round"/><circle cx="15.8" cy="14.9" r=".8" fill="#fff"/><circle cx="19.2" cy="14.9" r=".8" fill="#fff"/></svg>',
  qq: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.4a6.4 6.4 0 0 0-6.4 6.4c0 1.2.2 2.3.6 3.3-1 1.8-2.1 4.5-2.1 6.4 0 1.7 1 2.4 1.9 2.4 1.4 0 2.4-1.2 3-2.8h6c.6 1.6 1.6 2.8 3 2.8.9 0 1.9-.7 1.9-2.4 0-1.9-1.1-4.6-2.1-6.4.4-1 .6-2.1.6-3.3A6.4 6.4 0 0 0 12 2.4z" fill="#12B7F5"/><ellipse cx="9.8" cy="8.1" rx="2.3" ry="2.7" fill="#fff"/><ellipse cx="14.2" cy="8.1" rx="2.3" ry="2.7" fill="#fff"/><circle cx="9.8" cy="7.9" r="1.15" fill="#182B44"/><circle cx="14.2" cy="7.9" r="1.15" fill="#182B44"/><path d="M10.4 10.6h3.2L12 12.6z" fill="#F5A623"/><ellipse cx="12" cy="16.4" rx="3.3" ry="2.8" fill="#fff"/></svg>',
  leetcode: '<svg viewBox="0 0 24 24" aria-hidden="true"><path class="sg-si-lc-c" d="M12.4 4.9a7.1 7.1 0 1 0 0 14.2" fill="none" stroke="#1E2A38" stroke-width="3.4" stroke-linecap="round"/><path d="m13.2 4.6 4.3-2.7" fill="none" stroke="#FFA116" stroke-width="3.2" stroke-linecap="round"/><path d="M13 19.1h5.4" fill="none" stroke="#FFA116" stroke-width="3.2" stroke-linecap="round"/></svg>',
  bilibili: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7.4 3.2 9.2 5.9M16.6 3.2 14.8 5.9" fill="none" stroke="#23ADE5" stroke-width="1.8" stroke-linecap="round"/><rect x="2.4" y="5.9" width="19.2" height="13.8" rx="4.2" fill="#23ADE5"/><rect x="7.6" y="10" width="2.1" height="5.6" rx="1.05" fill="#fff"/><rect x="14.3" y="10" width="2.1" height="5.6" rx="1.05" fill="#fff"/></svg>',
  user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>',
  share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/></svg>',
  link: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/></svg>',
  bookOpen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7z"/></svg>',
  settings: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
  arrowDown: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>',
  activity: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12h4l3 8 4-16 3 8h4"/></svg>',
  calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>',
  history: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 8v4l3 2"/></svg>',
  eye: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
  type: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7V5h16v2"/><path d="M12 5v14"/><path d="M9 19h6"/></svg>',
  bookmark: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>',
  star: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/></svg>',
  image: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 18 5-5 4 4 3-3 4 4"/></svg>',
  message: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
  mail: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.4" y="4.9" width="19.2" height="14.2" rx="3.2" fill="#3D7BE0"/><path d="M5.6 4.9h12.8a3.2 3.2 0 0 1 3.2 3.2v.7L12 14.4 2.4 8.8v-.7a3.2 3.2 0 0 1 3.2-3.2z" fill="#6BA6FF"/><path d="m2.4 8.8 9.6 5.6 9.6-5.6" fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
  rss: '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="5.3" cy="18.7" r="2.4" fill="#F7841D"/><path d="M3.4 12.4a8.2 8.2 0 0 1 8.2 8.2" fill="none" stroke="#F7841D" stroke-width="3" stroke-linecap="round"/><path d="M3.4 4.6A16.2 16.2 0 0 1 19.6 20.8" fill="none" stroke="#FBB03B" stroke-width="3" stroke-linecap="round"/></svg>',
};

const NAV = [
  { name: '首页', href: '#', active: true },
  { name: '归档', href: '#' },
  { name: '关于', href: '#' },
  { name: '友链', href: '#' },
  { name: '杂谈', href: '#' },
  { name: '说说', href: '#' },
  { name: '音乐', href: '#' },
  { name: '光影画廊', href: '#' },
  { name: '项目', href: '#' },
  { name: '知识档案', href: '#' },
  { name: '时光机', href: '#' },
];

/* 与 partials/nav.php 的分组规则一致：顶栏最多 8 个位置（首页 + 6 页 + 更多） */
const NAV_PRIMARY = NAV.slice(0, 7);
const NAV_MORE = NAV.slice(7);

const head = (title) => `<!DOCTYPE html>
<html lang="zh-CN" class="dark sg-ready">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>${title}</title>
${css.map((href) => `<link rel="stylesheet" data-sg-style href="${href}">`).join('\n')}
<script type="application/json" data-sg-config>${JSON.stringify(runtime)}</script>
<script>window.SG=${JSON.stringify(runtime)};</script>
${js ? `<script type="module" src="${js}"></script>` : ''}
</head>
<body class="sg-body">
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<div class="sg-bg" aria-hidden="true">
  <div class="sg-bg-images" data-sg-bg>
    <div class="sg-bg-frame is-active"><img src="${IMG.bg}" alt=""></div>
  </div>
  <div class="sg-gradient" data-sg-gradient></div>
  <div class="sg-glow sg-glow-a"></div>
  <div class="sg-glow sg-glow-b"></div>
</div>
<canvas class="sg-effects" data-sg-effects aria-hidden="true"></canvas>
<div class="sg-danmaku" data-sg-danmaku aria-hidden="true"></div>
<header class="sg-nav" data-sg-nav>
  <div class="sg-nav-inner">
    <a class="sg-brand" href="#">Xinghuisama<span class="sg-brand-accent">の</span>空璃</a>
    <a class="sg-nav-page" data-sg-nav-page href="#" data-tip="回到顶部" hidden>预览页面</a>
    <nav class="sg-nav-links" aria-label="主导航">
      <a class="sg-nav-link" href="#">首页</a>
      <div class="sg-nav-group is-active-group" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">🍃 拾遗${icons.chevronDown}</button>
        <div class="sg-nav-group-menu" role="menu">
          <a class="sg-nav-group-item" href="#">归档</a>
          <a class="sg-nav-group-item" href="#">知识档案</a>
          <a class="sg-nav-group-item is-active" href="#">时光机</a>
        </div>
      </div>
      <div class="sg-navbox-group" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">🎐 逸趣${icons.chevronDown}</button>
        <div class="sg-nav-group-menu" role="menu">
          <a class="sg-nav-group-item" href="#">音乐馆</a>
          <a class="sg-nav-group-item" href="#">光影画廊</a>
        </div>
      </div>
      <div class="sg-navbox-group" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">🧰 匣匣${icons.chevronDown}</button>
        <div class="sg-nav-group-menu" role="menu">
          <a class="sg-nav-group-item" href="#">项目</a>
        </div>
      </div>
      <div class="sg-navbox-group" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">🕊 相逢${icons.chevronDown}</button>
        <div class="sg-nav-group-menu" role="menu">
          <a class="sg-nav-group-item" href="#">友链</a>
          <a class="sg-nav-group-item" href="#">碎语</a>
        </div>
      </div>
      <a class="sg-nav-link" href="#">自述</a>
    </nav>
  </div>
</header>
<button class="sg-disc" type="button" data-sg-sheet-open aria-label="打开导航"><span class="sg-disc-dots"><i></i><i></i><i></i></span></button>
<div class="sg-sheet" data-sg-sheet aria-hidden="true">
  <div class="sg-sheet-panel">
    <a class="sg-sheet-link" href="#">首页</a>
    <p class="sg-sheet-group-name">拾遗</p>
    <a class="sg-sheet-link sg-sheet-sub" href="#">归档</a>
    <a class="sg-sheet-link sg-sheet-sub is-active" href="#">时光机</a>
    <a class="sg-sheet-link sg-sheet-sub" href="#">知识档案</a>
    <p class="sg-sheet-group-name">逸趣</p>
    <a class="sg-sheet-link sg-sheet-sub" href="#">音乐馆</a>
    <a class="sg-sheet-link sg-sheet-sub" href="#">光影画廊</a>
    <p class="sg-sheet-group-name">匣匣</p>
    <a class="sg-sheet-link sg-sheet-sub" href="#">项目</a>
    <p class="sg-sheet-group-name">相逢</p>
    <a class="sg-sheet-link sg-sheet-sub" href="#">友链</a>
    <a class="sg-sheet-link sg-sheet-sub" href="#">碎语</a>
    <a class="sg-sheet-link" href="#">自述</a>
    <hr class="sg-divider">
    <button class="sg-sheet-link" type="button" data-sg-theme-toggle>${icons.sun.replace('<svg', '<svg class="sg-i-sun"')}${icons.moon.replace('<svg', '<svg class="sg-i-moon"')} 切换昼夜</button>
  </div>
</div>
`;

const foot = (readmode = false) => `
<div class="sg-footer-zone">
<footer class="sg-footer-wrap">
  <div class="sg-footer">
    <div class="sg-footer-status">
      <span class="sg-footer-run">
        <i class="sg-footer-dot" aria-hidden="true"></i>
        <span class="sg-footer-label">系统已稳定运行：</span>
        <b class="sg-footer-uptime">211 天 20 小时</b>
      </span>
      <span class="sg-footer-icp">京ICP备00000000号</span>
    </div>
    <hr class="sg-footer-rule">
    <p class="sg-footer-copy">&copy; 2026 Xinghuisama</p>
    <p class="sg-footer-powered">Powered by Typecho · Theme Soraglass 空璃</p>
  </div>
</footer>
</div>
<div class="sg-pace" data-sg-pace aria-hidden="true"><span class="sg-pace-progress"></span></div>
<div class="sg-rightside" data-sg-rightside>
  <div class="sg-rightside-hidden" data-sg-rightside-hidden>
    <a class="sg-rightside-btn" data-sg-music-entry href="#" aria-label="音乐馆">${icons.music}</a>
    <button class="sg-rightside-btn" type="button" data-sg-theme-toggle aria-label="切换昼夜模式">${icons.sun.replace('<svg', '<svg class="sg-i-sun"')}${icons.moon.replace('<svg', '<svg class="sg-i-moon"')}</button>
    ${readmode ? `<button class="sg-rightside-btn" type="button" data-sg-readmode-toggle aria-label="切换阅读模式" aria-pressed="false">${icons.bookOpen}</button>` : ''}
  </div>
  <button class="sg-rightside-btn" type="button" data-sg-rightside-config aria-label="展开设置" aria-expanded="false">${icons.settings}</button>
  <button class="sg-rightside-btn sg-go-up" type="button" data-sg-go-up aria-label="回到顶部">${icons.arrowUp}<span class="sg-go-up-percent" data-sg-go-up-percent>0<span>%</span></span></button>
  <button class="sg-rightside-btn" type="button" data-sg-go-down aria-label="直达底部">${icons.arrowDown}</button>
</div>
<div class="sg-neko-rope" data-sg-neko-rope aria-hidden="true"></div>
<div class="sg-neko" data-sg-neko aria-hidden="true">
  <img src="./img/neko.png" alt="" width="64" height="64">
  <span class="sg-neko-msg" data-sg-neko-msg></span>
</div>
<audio data-sg-audio preload="none"></audio>
<div class="sg-toast" data-sg-toast role="status" aria-live="polite"></div>
<script async src="https://events.vercount.one/js"></script>
</body>
</html>
`;

const player = `
<div class="sg-player" data-sg-player data-href="#">
  <div class="sg-player-top"><i class="sg-player-state-dot" aria-hidden="true"></i><span data-sg-playback-status>等待播放</span></div>
  <div class="sg-album-art" data-sg-album-art aria-hidden="true">
    <div class="sg-album-cd"><div class="sg-album-rotor"><span class="sg-album-hub"></span></div></div>
    <div class="sg-album-sleeve"><img src="${IMG.bg}" alt="" data-sg-track-cover></div>
  </div>
  <div class="sg-player-info">
    <a class="sg-player-title" href="#" data-sg-track-title>放心去飞</a>
    <div class="sg-player-artist" data-sg-track-artist>欧豪 / 杨洋 / 胡夏</div>
  </div>
  <div class="sg-player-lyric" data-sg-lyric data-sg-typewriter>[ti:放心去飞]</div>
  <canvas class="sg-player-wave" data-sg-wave aria-hidden="true"></canvas>
  <div class="sg-player-progress">
    <span data-sg-time-current>00:02</span>
    <input class="sg-progress" type="range" min="0" max="1000" value="0" data-sg-progress aria-label="播放进度">
    <span data-sg-time-total>04:07</span>
  </div>
  <div class="sg-player-controls">
    <button class="sg-player-mode" type="button" data-sg-mode data-mode="loop" aria-label="播放模式：列表循环">${icons.music}<span data-sg-mode-label>列表循环</span></button>
    <span class="sg-player-transport">
      <button type="button" data-sg-prev aria-label="上一首">${icons.prev}</button>
      <button type="button" class="sg-player-play" data-sg-play aria-label="播放/暂停" aria-pressed="false"><span class="sg-i-play">${icons.play}</span><span class="sg-i-pause">${icons.pause}</span></button>
      <button type="button" data-sg-next aria-label="下一首">${icons.next}</button>
    </span>
    <button type="button" class="sg-player-mute" data-sg-mute aria-label="静音">${icons.music}</button>
  </div>
</div>
`;

const profile = `
<div class="sg-profile-banner" aria-hidden="true"></div>
<div class="sg-profile-top">
  <div class="sg-avatar-wrap">
    <a class="sg-avatar-ring" href="#"><img src="${IMG.avatar}" alt="Xinghuisama"></a>
    <span class="sg-status-bubble" title="正在打磨毛玻璃">${icons.message}<span class="sg-status-text">正在打磨毛玻璃</span></span>
  </div>
  <h1 class="sg-profile-name">Xinghuisama</h1>
  <p class="sg-profile-bio">在空璃里写代码、拍照片、记碎碎念。</p>
</div>
<div class="sg-stats">
  <a class="sg-stat" href="#"><span class="sg-stat-label">文章</span><span class="sg-stat-num">42</span></a>
  <a class="sg-stat" href="#"><span class="sg-stat-label">标签</span><span class="sg-stat-num">18</span></a>
  <a class="sg-stat" href="#"><span class="sg-stat-label">分类</span><span class="sg-stat-num">6</span></a>
</div>
<a class="sg-author-btn" href="#">${icons.user}关于我</a>
<div class="sg-profile-social">
  <a class="sg-social" href="#" title="微信" aria-label="微信">${icons.wechat}</a>
  <a class="sg-social" href="#" title="QQ" aria-label="QQ">${icons.qq}</a>
  <a class="sg-social" href="#" title="哔哩哔哩" aria-label="哔哩哔哩">${icons.bilibili}</a>
  <a class="sg-social" href="#" title="邮箱" aria-label="邮箱">${icons.mail}</a>
  <a class="sg-social" href="#" title="力扣" aria-label="力扣">${icons.leetcode}</a>
</div>
`;

const folderColor = '<svg class="sg-ci" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="var(--sg-icon-cate)"/><path d="M3 10h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="color-mix(in srgb, var(--sg-icon-cate) 55%, #fff)"/></svg>';

const homeCates = `
<section class="sg-glass sg-home-cates" aria-label="分类入口">
  <div class="sg-home-cates-grid">
    ${[['设计札记', 12], ['Typecho', 8], ['配色', 5], ['前端工程', 9], ['生活碎片', 6], ['音乐盒', 2]].map(([name, count]) => `
    <a class="sg-home-cate" href="#">${folderColor}<span class="sg-home-cate-name">${name}</span><span class="sg-home-cate-count">(${count})</span>${icons.chevronRight}</a>`).join('')}
  </div>
  <a class="sg-home-cates-more" href="#">查看更多…</a>
</section>
`;

const slide = (i, title, desc, date, cat) => `
<article class="sg-post-slide${i === 0 ? ' is-active' : ''}" data-sg-slide>
  <a class="sg-slide-media" href="#" tabindex="-1" aria-hidden="true"><img class="sg-slide-cover" src="${IMG.bg}" alt=""></a>
  <a class="sg-slide-body" href="#">
    <span class="sg-slide-chips"><span class="sg-chip--tag">${cat}</span><span class="sg-chip--mono">${date}</span></span>
    <h2 class="sg-slide-title">${title}</h2>
    <span class="sg-slide-desc">${desc}</span>
  </a>
</article>`;

const chatter = (i, title, desc, date) => `
<article class="sg-chatter-slide${i === 0 ? ' is-active' : ''}" data-sg-slide>
  <img src="${IMG.bg}" alt="">
  <span class="sg-veil"></span>
  <a class="sg-chatter-body" href="#">
    <span class="sg-chatter-meta"><span class="sg-chip--tag">杂谈</span><time>${date}</time></span>
    <h3>${title}</h3>
    <p>${desc}</p>
  </a>
</article>`;

const briefing = `
<section class="sg-glass sg-briefing is-open" data-sg-briefing>
  <div class="sg-briefing-head">
    <div class="sg-briefing-title"><h2>每日选刊</h2><span>共 3 期</span></div>
    <div class="sg-briefing-actions">
      <button type="button" data-sg-briefing-toggle><span data-sg-briefing-toggle-text>收起</span>${icons.chevronDown}</button>
    </div>
  </div>
  <div class="sg-briefing-body">
    ${[
      ['把毛玻璃调成呼吸的频率', '背景渐变每 15 秒循环一次，卡片模糊固定在 12px。', '设计札记', '6 min read', 1],
      ['Typecho 主题里那些会踩的坑', '自定义模板必须写 @package custom。', 'Typecho', '8 min read', 0],
    ]
      .map(
        ([t, d, m1, m2, active]) => `
    <div class="sg-briefing-slide${active ? ' is-active' : ''}" data-sg-briefing-slide>
      <img src="${IMG.bg}" alt="" loading="lazy">
      <span class="sg-veil"></span>
      <div class="sg-briefing-body-inner">
        <div>
          <div class="sg-briefing-date"><span>DAILY SIGNAL</span><span>2026.01.12</span></div>
          <span class="sg-briefing-label">每日拾光</span>
          <span class="sg-briefing-title-main">${t}</span>
          <span class="sg-briefing-desc">${d}</span>
        </div>
        <div class="sg-briefing-foot">
          <div><span>${m1}</span><span>${m2}</span></div>
          <div class="sg-briefing-pager">
            <button type="button" data-sg-briefing-prev aria-label="上一期">${icons.chevronLeft}</button>
            <span data-sg-briefing-index>1/2</span>
            <button type="button" data-sg-briefing-next aria-label="下一期">${icons.chevronRight}</button>
          </div>
        </div>
      </div>
    </div>`,
      )
      .join('')}
  </div>
</section>`;

/* 首屏：静态预览统一用 bg-1（2026-11-25 删 bg-2.svg 后），对齐 home-front.php */
const front = `
<section class="sg-front" data-sg-front aria-label="站点首屏">
  <div class="sg-front-media" aria-hidden="true">
    <img class="sg-front-img sg-front-img--light" src="${IMG.bg}" alt="" fetchpriority="high">
    <img class="sg-front-img sg-front-img--dark" src="${IMG.bg}" alt="" fetchpriority="high">
    <span class="sg-front-veil"></span>
  </div>
  <div class="sg-front-inner">
    <h1 class="sg-front-title">Xinghuisama<span class="sg-front-title-mark">· 空璃</span></h1>
    <p class="sg-front-sub" data-sg-typewriter="在空璃里写代码、拍照片、记碎碎念。&#10;把每一个夜晚调成深空色。">在空璃里写代码、拍照片、记碎碎念。</p>
    <div class="sg-front-social">
      <a class="sg-front-social-link" href="#" title="微信" aria-label="微信">${icons.wechat}</a>
      <a class="sg-front-social-link" href="#" title="QQ" aria-label="QQ">${icons.qq}</a>
      <a class="sg-front-social-link" href="#" title="哔哩哔哩" aria-label="哔哩哔哩">${icons.bilibili}</a>
    </div>
  </div>
  <button class="sg-front-next" type="button" data-sg-front-next aria-label="向下滚动查看正文">
    <span class="sg-front-next-label">向下探索</span>${icons.chevronDown}
  </button>
</section>`;

const featureCard = (title, desc, ago, cat, tags) => `
<article class="sg-feature-card" data-sg-filter-item>
  <a class="sg-feature-media" href="#" tabindex="-1" aria-hidden="true">
    <img src="${IMG.bg}" alt="" loading="lazy">
    <span class="sg-feature-quote">「${desc}」</span>
  </a>
  <div class="sg-feature-body">
    <h3 class="sg-feature-title"><a href="#">${title}</a></h3>
    <div class="sg-feature-meta">
      <span class="is-date">${icons.clock}发表于 ${ago}</span>
      ${cat || tags ? `<div class="sg-feature-taxonomy">
        ${cat ? `<span class="is-cate">${icons.folder}${cat}</span>` : ''}
        ${tags ? `<span class="is-tags">${icons.tag}${tags}</span>` : ''}
      </div>` : ''}
    </div>
  </div>
</article>`;

/* 侧栏日历：与 side-calendar.php 同构（左栏 44% 文本 + 右栏表格月历 + 底部虚线附加行） */
const calNow = new Date();
const calY = calNow.getFullYear();
const calM = calNow.getMonth() + 1;
const calD = calNow.getDate();
const calDays = new Date(calY, calM, 0).getDate();
const calFirst = new Date(calY, calM - 1, 1).getDay();
const calWd = ['日', '一', '二', '三', '四', '五', '六'];
const calWeek = (() => {
  const t = new Date(calY, calM - 1, calD);
  t.setDate(t.getDate() - ((t.getDay() + 6) % 7) + 3);
  const isoYear = t.getFullYear();
  const jan4 = new Date(isoYear, 0, 4);
  const week1 = new Date(isoYear, 0, 4 - ((jan4.getDay() + 6) % 7) + 3);
  return 1 + Math.round((t - week1) / 604800000);
})();
const calDoy = Math.round((new Date(calY, calM - 1, calD) - new Date(calY, 0, 1)) / 86400000) + 1;
const calRows = Array.from({ length: Math.ceil((calFirst + calDays) / 7) }, (_, row) => `<tr>${Array.from({ length: 7 }, (_, col) => {
  const day = row * 7 + col - calFirst + 1;
  if (day < 1 || day > calDays) return '<td class="sg-hcal-cell is-empty"></td>';
  const today = day === calD;
  return `<td class="sg-hcal-cell${today ? ' is-today' : ''}"${today ? ' aria-current="date"' : ''}><span>${day}</span></td>`;
}).join('')}</tr>`).join('');
const calendar = `
<section class="sg-hcard sg-hcal" aria-label="本月日历" data-sg-calendar title="${calY}年${calM}月${calD}日 星期${calWd[calNow.getDay()]}">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title">${icons.calendar}日历</span>
  </div>
  <div class="sg-hcal-main">
    <div class="sg-hcal-left">
      <span class="sg-hcal-week">第${calWeek}周 周${calWd[calNow.getDay()]}</span>
      <span class="sg-hcal-day">${String(calD).padStart(2, '0')}</span>
      <span class="sg-hcal-solar"><span>${calY}年${calM}月</span> <span>第${calDoy}天</span></span>
      <span class="sg-hcal-lunar">丙午马年 十月十二</span>
    </div>
    <table class="sg-hcal-grid" aria-label="${calY}年${calM}月">
      <thead><tr>${calWd.map((w) => `<th class="sg-hcal-wd" scope="col">${w}</th>`).join('')}</tr></thead>
      <tbody>${calRows}</tbody>
    </table>
  </div>
  <div class="sg-hcal-extra">
    <span class="sg-hcal-term">2天后是<strong>小雪</strong></span>
    <span class="sg-hcal-sun"><span>☀️ 06:44</span><span>🌙 17:41</span></span>
  </div>
</section>`;

const cdTarget = '2027-02-06';
const cdDays = Math.max(0, Math.ceil((new Date(cdTarget) - new Date(calY, calM - 1, calD)) / 86400000));
const cdSub = cdDays === 0 ? '就是今天' : cdTarget;
const pct2 = (n) => Math.min(100, Math.max(0, n)).toFixed(2);
const cdYearPct = pct2(((calDoy - 1) / 365) * 100);
const cdMonthPct = pct2(((calD - 1) / calDays) * 100);
const cdWeekPct = pct2((((calNow.getDay() + 6) % 7) / 7) * 100);
const cdRows = [['本年', cdYearPct, 365 - calDoy], ['本月', cdMonthPct, calDays - calD], ['本周', cdWeekPct, 6 - ((calNow.getDay() + 6) % 7)]]
  .map(([label, pct, remain]) => `<div class="sg-hcd-row"><span class="sg-hcd-rlabel">${label}</span><span class="sg-hcd-bar"><i style="width:${pct}%"></i><em>${pct}%</em><b>还剩 ${remain} 天</b></span></div>`).join('');
const countdown = `
<section class="sg-hcard sg-hcd" aria-label="春节倒计时" data-sg-countdown data-sg-cd-target="${cdTarget}">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title">${icons.star}春节倒计时</span>
  </div>
  <div class="sg-hcd-main">
    <div class="sg-hcd-left">
      <span class="sg-hcd-label">距离春节</span>
      <span class="sg-hcd-num" data-sg-cd-days>${cdDays}</span>
      <span class="sg-hcd-date" data-sg-cd-date>${cdSub}</span>
    </div>
    <div class="sg-hcd-rows">${cdRows}</div>
  </div>
</section>`;

/* 小站资讯：与 side-webinfo.php 同构（KPI + 时间条 + 年度产出柱状 + 访客统计）。
   今年还没发文也要露出 0（2026-11-16 第三轮）；访客数由 busuanzi 镜像异步回写。 */
const wiBars = [[2022, 4], [2023, 9], [2024, 12], [2025, 10], [2026, 0]];
const wiMax = Math.max(...wiBars.map(([, c]) => c));
const webinfo = `
<section class="sg-hcard sg-hinfo" aria-label="小站资讯">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title">${icons.activity}小站资讯</span>
  </div>
  <div class="sg-hinfo-kpi">
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num">5.2 万</span><span class="sg-hinfo-label">总字数</span></div>
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num">1.2k</span><span class="sg-hinfo-label">篇均字</span></div>
    <a class="sg-hinfo-kpi-item" href="#"><span class="sg-hinfo-num">3</span><span class="sg-hinfo-label">友链</span></a>
  </div>
  <div class="sg-hinfo-time">
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">已运行</span><b>211 天</b></span>
    <span class="sg-hinfo-time-sep" aria-hidden="true">·</span>
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">距上次发文</span><b>2 天</b></span>
    <span class="sg-hinfo-time-sep" aria-hidden="true">·</span>
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">更新</span><b>11-14</b></span>
  </div>
  <div class="sg-hinfo-chart">
    <div class="sg-hinfo-chart-head">
      <span>年度产出</span>
      <span class="sg-hinfo-chart-sub">共 38 个写作日</span>
    </div>
    <div class="sg-hinfo-bars">
      ${wiBars.map(([y, c]) => `<div class="sg-hinfo-bar">
        <span class="sg-hinfo-bar-n">${c}</span>
        <span class="sg-hinfo-bar-track"><i style="height:${c > 0 ? Math.max(12, Math.round(c / wiMax * 68)) : 4}%${c > 0 ? '' : ';opacity:.25'}"></i></span>
        <span class="sg-hinfo-bar-y">${y}</span>
      </div>`).join('')}
    </div>
  </div>
  <div class="sg-hinfo-visit">
    <div class="sg-hinfo-visit-item"><span class="sg-hinfo-visit-num" id="busuanzi_value_site_uv">—</span><span class="sg-hinfo-visit-label">本站访客数</span></div>
    <div class="sg-hinfo-visit-item"><span class="sg-hinfo-visit-num" id="busuanzi_value_site_pv">—</span><span class="sg-hinfo-visit-label">本站总访问量</span></div>
  </div>
</section>`;

const indexPage = `${head('Soraglass · 空璃')}
<main id="sg-main" class="sg-main sg-main--home" data-sg-page="index">
  ${front}
  <div class="sg-home-body">
    <div class="sg-home-main">
      <div class="sg-stack">
        <div class="sg-hero">
          <section class="sg-glass sg-post-carousel sg-hero-cover" data-sg-carousel data-sg-interval="5000" data-sg-hover>
            ${slide(0, '把毛玻璃调成呼吸的频率', '背景渐变 15 秒一轮，卡片模糊固定在 12px——再多就糊，再少就透。', '2026.01.12', '设计札记')}
            ${slide(1, 'Typecho 主题里那些会踩的坑', 'themeUrl 属性在模板 include 里可能是空串，必须留三级回退。', '2026.01.10', 'Typecho')}
            ${slide(2, '夜里把深空调成 #020617', '夜间模式不是简单反色，卡片透明度与光晕都要重新配。', '2026.01.08', '配色')}
            <div class="sg-dots" data-sg-dots></div>
          </section>
        </div>
        ${homeCates}
        <section class="sg-home-latest" id="sg-home-content" aria-label="最新文章">
          <div class="sg-home-section-head">
            <h2 class="sg-home-section-title">${icons.bookOpen}最新文章</h2>
          </div>
          <div class="sg-home-grid">
            ${featureCard('把毛玻璃调成呼吸的频率', '背景渐变 15 秒一轮，卡片模糊固定在 12px——再多就糊，再少就透。', '3 天前', '设计札记', 'CSS · 设计')}
            ${featureCard('Typecho 主题里那些会踩的坑', '自定义模板必须写 @package custom；themeUrl 属性可能是空串。', '5 天前', 'Typecho', 'Typecho · PHP')}
            ${featureCard('夜里把深空调成 #020617', '夜间模式不是简单反色，卡片透明度与光晕都要重新配。', '1 周前', '配色', 'CSS · 配色')}
            ${featureCard('用 unicode-range 把中文字体切薄', '自托管思源宋体，按需加载，首屏只下拉几片。', '2 周前', 'CSS', '字体 · 性能')}
            ${featureCard('给博客加一条会呼吸的歌词栏', '五根声波随播放状态起伏，歌词逐字打出，暂停时全部静止。', '3 周前', '设计札记', '交互 · 动效')}
            ${featureCard('春节倒计时卡的轻量实现', '内置未来八年春节公历表，前端只做天数差，不引农历算法。', '1 个月前', 'Typecho', 'Typecho · 日历')}
          </div>
        </section>
        <div class="sg-lyric-bar" data-sg-lyric-bar>
          <span class="sg-lyric-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
          <p class="sg-lyric-text" data-sg-lyric-text>点击播放，开始今天的 BGM</p>
          <span class="sg-lyric-icon" aria-hidden="true">${icons.music}</span>
        </div>
        <div class="sg-home-stories">
          <a class="sg-poster-wide" href="#" data-sg-hover>
            <img src="${IMG.bg}" alt="" loading="lazy">
            <span class="sg-veil"></span>
            <div class="sg-poster-body">
              <span class="sg-poster-kicker">${icons.image}光影画廊</span>
              <h3>樱色纪行</h3>
              <p>三月的风把整条街吹成粉色。</p>
            </div>
          </a>
          <div class="sg-glass sg-chatter" data-sg-carousel data-sg-hover>
            ${chatter(0, '今天把字体换成了自托管的思源宋体', '按 unicode-range 分片，页面只下载用得上的那几片。', '2026.01.12 21:40')}
            ${chatter(1, '给毛玻璃加了一层很淡的光晕', '两层 blur(100px) 的圆斑，叠加在渐变之上。', '2026.01.11 23:02')}
            <span class="sg-dots" data-sg-dots></span>
          </div>
        </div>
        ${briefing}
      </div>
    </div>
    <aside class="sg-home-aside" aria-label="站点侧栏">
      <section class="sg-glass sg-card-pad sg-profile" data-sg-hover>${profile}</section>
      <section class="sg-glass sg-player-card" data-sg-hover>${player}</section>
      ${calendar}
      ${countdown}
      ${webinfo}
    </aside>
  </div>
</main>
${foot()}`;

const postPage = `${head('把毛玻璃调成呼吸的频率 - Soraglass')}
<main class="sg-main sg-article-layout" data-sg-page="post">
  <article class="sg-glass sg-article sg-reading-surface">
    <header class="sg-post-hero">
      <img class="sg-post-hero-cover" src="${IMG.bg}" alt="" aria-hidden="true">
      <span class="sg-post-hero-scrim" aria-hidden="true"></span>
      <div class="sg-post-hero-inner">
        <h1 class="sg-post-hero-title">把毛玻璃调成呼吸的频率</h1>
        <div class="sg-post-hero-meta">
          <div class="sg-post-meta-line">
            <time class="sg-post-meta-item" datetime="2026-01-12T21:40:00+08:00" title="2026-01-12 21:40">${icons.calendar}发表于 28 天前</time>
            <span class="sg-post-meta-sep" aria-hidden="true">|</span>
            <span class="sg-post-meta-item" title="更新于 2026-01-12">${icons.history}更新于 28 天前</span>
            <span class="sg-post-meta-sep" aria-hidden="true">|</span>
            <a class="sg-post-meta-item sg-post-meta-cate" href="#">${icons.tag}设计札记</a>
          </div>
          <div class="sg-post-meta-line">
            <span class="sg-post-meta-item">${icons.type}字数总计: 1284</span>
            <span class="sg-post-meta-sep" aria-hidden="true">|</span>
            <span class="sg-post-meta-item">${icons.clock}阅读时长: 4分钟</span>
            <span class="sg-post-meta-sep" aria-hidden="true">|</span>
            <span class="sg-post-meta-item">${icons.eye}阅读量: <span id="busuanzi_value_page_pv">—</span></span>
          </div>
        </div>
      </div>
    </header>
    <div class="sg-article-body">
      <a class="sg-back" href="#">${icons.arrowLeft}返回知识档案</a>
      <p class="sg-lead">毛玻璃的观感并不取决于模糊半径，而是取决于「透明层 + 边框高光 + 阴影」三者的配比。</p>
      <div class="sg-prose" data-sg-article>
        <h2>一、先定容器</h2>
        <p>容器宽度 95% / 90%，最大 72rem，上边距 6rem 起——这组数字决定了整页的呼吸感。</p>
        <h2>二、再定玻璃</h2>
        <p>卡片用 40% 白，导航用 60% 白；模糊 12px 与 24px 分成两档，避免所有层都糊成一片。</p>
        <h3>2.1 边框高光</h3>
        <p>1px 的 rgba(255,255,255,.4) 边框，是玻璃「有厚度」的关键。</p>
        <pre><code class="language-css">.sg-glass {
  background: rgba(255, 255, 255, .40);
  backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, .40);
}</code></pre>
        <h2>三、最后才是动效</h2>
        <p>渐变每 15 秒一轮，壁纸 2 秒交叉淡入，其余一律不做——动效越多，越像廉价模板。</p>
        <p><img src="${IMG.bg}" alt="毛玻璃层级示意：卡片、导航与光晕的三层配比"></p>
      </div>
      <div class="sg-meta sg-article-tags" aria-label="文章标签">
        <a class="sg-meta-chip is-pink" href="#">${icons.tag}CSS</a>
        <a class="sg-meta-chip is-pink" href="#">${icons.tag}设计</a>
      </div>
      <div class="sg-share" role="group" aria-label="分享这篇文章">
        <button class="sg-btn sg-btn--ghost sg-share-btn" type="button" data-sg-share>${icons.share}分享</button>
        <button class="sg-btn sg-btn--ghost" type="button" data-sg-copy-link>${icons.link}复制链接</button>
      </div>
      <nav class="sg-post-nav" aria-label="上下篇">
        <a class="sg-post-nav-item is-prev" href="#">
          <img src="${IMG.bg}" alt="" loading="lazy">
          <span class="sg-post-nav-info"><em>上一篇</em><b>Typecho 主题里那些会踩的坑</b></span>
        </a>
        <a class="sg-post-nav-item is-next" href="#">
          <img src="${IMG.cover}" alt="" loading="lazy">
          <span class="sg-post-nav-info"><em>下一篇</em><b>夜里把深空调成 #020617</b></span>
        </a>
      </nav>
    </div>
  </article>
  <aside class="sg-aside">
    <div class="sg-aside-pin" data-sg-aside-pin>
      <section class="sg-glass sg-author-card">
        <a class="sg-author-avatar" href="#"><img src="${IMG.avatar}" alt="Xinghuisama"></a>
        <h3 class="sg-author-name">Xinghuisama</h3>
        <p class="sg-author-bio">在空璃里写代码、拍照片、记碎碎念。</p>
        <div class="sg-socials">
          <a class="sg-social" href="#" title="哔哩哔哩">${icons.bilibili}</a>
          <a class="sg-social" href="#" title="邮箱">${icons.mail}</a>
        </div>
        <a class="sg-author-btn" href="#">${icons.user}关于我</a>
      </section>
      <section class="sg-glass sg-side-card sg-side-notice">
        <h3 class="sg-side-title">公告</h3>
        <p>欢迎来到空璃。本站正在持续更新主题手感，若有建议欢迎留言。<br>夜间模式已全面压暗，祝你阅读愉快。</p>
      </section>
      <section class="sg-glass sg-toc" data-sg-toc hidden>
        <header class="sg-toc-head">
          <h3 class="sg-side-title">目录</h3>
          <span class="sg-toc-percent" data-sg-toc-percent>0%</span>
        </header>
        <span class="sg-toc-rail" aria-hidden="true"></span>
        <div data-sg-toc-list></div>
      </section>
    </div>
  </aside>
</main>
${foot(true)}`;

const pageHead = (title, sub, opts = {}) => `
<div class="sg-page-head">
  <a class="sg-back" href="#">${icons.arrowLeft}返回上一级</a>
  <div class="sg-page-head-row${opts.center ? ' is-centered' : ''}">
    <div>
      <h1 class="sg-page-title">${title}</h1>
      <p class="sg-page-sub">${sub}</p>
    </div>
  </div>
</div>`;

const noteCard = (title, desc, date) => `
<article class="sg-glass sg-note" data-sg-filter-item data-keywords="${title}">
  <time>${date}</time>
  <h3><a href="#">${title}</a></h3>
  <p>${desc}</p>
  <a class="sg-note-more" href="#">阅读全文 →</a>
</article>`;

const archivePage = `${head('归档 - Soraglass')}
<main class="sg-main sg-main--pad" data-sg-page="archive">
  <div class="sg-page-head">
    <div class="sg-page-head-row is-centered">
      <div>
        <h1 class="sg-page-title">分类 设计札记</h1>
        <p class="sg-page-sub">本页 6 篇记录</p>
      </div>
    </div>
  </div>
  <div class="sg-filter">
    <a class="sg-filter-btn" href="#">全部</a>
    <a class="sg-filter-btn is-active" href="#">CSS<span class="sg-filter-count">8</span></a>
    <a class="sg-filter-btn" href="#">Typecho<span class="sg-filter-count">6</span></a>
    <a class="sg-filter-btn" href="#">配色<span class="sg-filter-count">4</span></a>
  </div>
  <section class="sg-reading-list sg-reading-surface" aria-label="文章列表">
    ${featureCard('把毛玻璃调成呼吸的频率', '背景渐变 15 秒一轮，卡片模糊固定在 12px——再多就糊，再少就透。', '3 天前', '设计札记', 'CSS · 设计')}
    ${featureCard('Typecho 主题里那些会踩的坑', '自定义模板必须写 @package custom；themeUrl 属性可能是空串。', '5 天前', 'Typecho', 'Typecho · PHP')}
    ${featureCard('夜里把深空调成 #020617', '夜间模式不是简单反色，卡片透明度与光晕都要重新配。', '1 周前', '配色', 'CSS · 配色')}
    ${featureCard('用 unicode-range 把中文字体切薄', '自托管思源宋体，按需加载，首屏只下拉几片。', '2 周前', 'CSS', '字体 · 性能')}
    ${featureCard('给博客加一条会呼吸的歌词栏', '五根声波随播放状态起伏，歌词逐字打出，暂停时全部静止。', '3 周前', '设计札记', '交互 · 动效')}
    ${featureCard('春节倒计时卡的轻量实现', '内置未来八年春节公历表，前端只做天数差，不引农历算法。', '1 个月前', 'Typecho', 'Typecho · 日历')}
  </section>
</main>
${foot()}`;

const friendsPage = `${head('友链 - Soraglass')}
<main class="sg-main sg-main--pad" data-sg-page="friends">
  ${pageHead('友链', '同行的人 · 共 3 位', { center: true })}
  <div class="sg-card-grid" data-sg-filter-grid>
    ${['星野', '海月', '木漏']
      .map(
        (n) => `
    <a class="sg-glass sg-friend" href="#" data-sg-filter-item data-keywords="${n}">
      <span class="sg-friend-avatar"><img src="${IMG.avatar}" alt="" loading="lazy"></span>
      <span class="sg-friend-main">
        <span class="sg-friend-head">
          <span class="sg-friend-name">${n}</span>
          <span class="sg-friend-status"><i style="background:#6366f1"></i>ONLINE</span>
        </span>
        <span class="sg-friend-desc">写代码、拍照片、把夜晚调成深空色。</span>
      </span>
    </a>`,
      )
      .join('')}
  </div>
</main>
${foot()}`;

const photowallPage = `${head('光影画廊 - Soraglass')}
<main class="sg-main sg-main--wide sg-main--pad" data-sg-page="photowall" data-sg-gallery data-sg-grid>
  ${pageHead('光影画廊', '定格时间，封存每一次心跳 · 共 9 张照片')}
  <div class="sg-gallery" data-sg-gallery-list>
    ${[
      ['sakura', '樱色纪行', '三月的风把整条街吹成粉色', '2026.03', 6],
      ['night', '深夜观测站', '城市熄灯之后，天空才肯把真正的颜色露出来', '2026.01', 5],
      ['room', '房间与光', '把日常拍成静物，是因为它们每天都在悄悄改变', '2025.11', 4],
    ]
      .map(
        ([id, title, desc, date, count]) => `
    <article class="sg-album-card" data-sg-album="${id}" data-keywords="${title} ${desc}">
      <button class="sg-album-open" type="button" data-sg-album-open="${id}" aria-label="打开专辑：${title}">
        <span class="sg-polaroid-stack">
          <img class="sg-polaroid" src="${IMG.cover}" alt="" loading="lazy">
          <img class="sg-polaroid" src="${IMG.bg}" alt="" loading="lazy">
          <img class="sg-polaroid" src="${IMG.bg}" alt="${title}" loading="lazy">
        </span>
      </button>
      <div class="sg-album-meta">
        <h2>${title}</h2>
        <span>${date} · ${count} 张照片</span>
        <p>${desc}</p>
      </div>
    </article>`,
      )
      .join('')}
  </div>
  ${[
    ['sakura', '樱色纪行', '2026.03', 6],
    ['night', '深夜观测站', '2026.01', 5],
    ['room', '房间与光', '2025.11', 4],
  ]
    .map(
      ([id, title, date, count]) => `
  <section class="sg-gallery-detail" data-sg-gallery-detail="${id}" hidden>
    <div class="sg-gallery-detail-head">
      <button class="sg-back" type="button" data-sg-gallery-back>${icons.arrowLeft}返回画廊</button>
      <h2>${title}</h2>
      <span>${date} · 共 ${count} 个瞬间</span>
    </div>
    <div class="sg-gallery-masonry" data-sg-photo-group>
      ${Array.from({ length: count }, (_, i) => `
      <a class="sg-photo" href="${IMG.bg}" data-sg-photo>
        <img src="${i % 2 ? IMG.bg : IMG.bg}" alt="图 ${i + 1}" loading="lazy">
        <span class="sg-photo-cap">图注 ${i + 1}</span>
      </a>`).join('')}
    </div>
  </section>`,
    )
    .join('')}
</main>
${foot()}`;

const aboutPage = `${head('关于 - Soraglass')}
<main class="sg-main sg-main--narrow" data-sg-page="about" data-sg-grid>
  <article class="sg-glass sg-about">
    <div class="sg-about-banner">
      <img src="${IMG.bg}" alt="关于">
      <div class="sg-about-veil"></div>
      <a class="sg-about-back" href="#">${icons.arrowLeft}返回首页</a>
    </div>
    <div class="sg-about-inner">
      <div class="sg-about-avatar"><img src="${IMG.avatar}" alt="Xinghuisama"></div>
      <div class="sg-about-head">
        <div class="sg-about-title">
          <h1>关于我</h1>
          <p class="sg-about-eyebrow">Hello World, I'm Xinghuisama</p>
        </div>
        <div class="sg-seg" data-sg-seg="tab" role="tablist" aria-label="关于页分区">
          <button class="sg-seg-btn is-active" type="button" role="tab" data-sg-seg-btn="intro" aria-selected="true">自我介绍</button>
          <button class="sg-seg-btn" type="button" role="tab" data-sg-seg-btn="activity" aria-selected="false">研究动态</button>
        </div>
      </div>
      <hr class="sg-about-rule">
      <div class="sg-about-panel" data-sg-seg-panel="tab:intro">
        <div class="sg-prose" data-sg-article>
          <h2>个人简介</h2>
          <p>在空璃里写代码、拍照片、记碎碎念。白天做工程，夜里调颜色。</p>
          <h2>研究方向</h2>
          <p>前端渲染性能、毛玻璃层级、中文自托管字体分片。</p>
          <ul><li>Typecho 主题工程化</li><li>Vite 构建与资源哈希</li><li>无障碍与 reduced-motion</li></ul>
        </div>
      </div>
      <div class="sg-about-panel" data-sg-seg-panel="tab:activity" hidden>
        <section class="sg-heat">
          <h3 class="sg-heat-title">${icons.clock}15 次更新 <span>· 近一年</span></h3>
          <div class="sg-heat-scroll">
            <div class="sg-heat-grid">
              ${Array.from({ length: 371 }, (_, i) => `<i class="sg-heat-cell" data-level="${i % 37 === 0 ? 3 : i % 11 === 0 ? 2 : i % 5 === 0 ? 1 : 0}"></i>`).join('')}
            </div>
          </div>
          <div class="sg-heat-legend"><span>少</span><i class="sg-heat-cell" data-level="0"></i><i class="sg-heat-cell" data-level="1"></i><i class="sg-heat-cell" data-level="2"></i><i class="sg-heat-cell" data-level="3"></i><i class="sg-heat-cell" data-level="4"></i><span>多</span></div>
        </section>
        <ul class="sg-about-feed">
          <li class="sg-about-feed-item"><time>2026-01-12 21:40</time><a href="#">把毛玻璃调成呼吸的频率</a><p>背景渐变 15 秒一轮，卡片模糊固定在 12px。</p></li>
          <li class="sg-about-feed-item"><time>2026-01-10 18:02</time><a href="#">Typecho 主题里那些会踩的坑</a><p>自定义模板必须写 @package custom。</p></li>
        </ul>
      </div>
    </div>
  </article>
</main>
${foot()}`;

const timelinePage = `${head('知识档案 - Soraglass')}
<main class="sg-main sg-main--wide sg-main--pad sg-archive" data-sg-page="timeline" data-sg-archive data-view="matrix" data-sg-grid>
  ${pageHead('知识档案', '总计 4 篇研究记录')}
  <div class="sg-archive-toolbar">
    <div class="sg-filter sg-archive-tags">
      <button class="sg-filter-btn is-active" type="button" data-sg-archive-tag="全部">全部档案</button>
      <button class="sg-filter-btn" type="button" data-sg-archive-tag="css">CSS<span class="sg-filter-count">2</span></button>
      <button class="sg-filter-btn" type="button" data-sg-archive-tag="typecho">Typecho<span class="sg-filter-count">2</span></button>
      <button class="sg-filter-btn" type="button" data-sg-archive-tag="color">配色<span class="sg-filter-count">1</span></button>
    </div>
    <div class="sg-seg sg-archive-switch" role="group" aria-label="切换阅读视图">
      <button class="sg-seg-btn" type="button" data-sg-archive-view="chain" aria-pressed="false">中枢链路</button>
      <button class="sg-seg-btn is-active" type="button" data-sg-archive-view="matrix" aria-pressed="true">矩阵网格</button>
    </div>
  </div>
  <div class="sg-archive-list">
    ${[
      ['把毛玻璃调成呼吸的频率', '背景渐变 15 秒一轮，卡片模糊固定在 12px——再多就糊，再少就透。', '2026.01.12', 'css|color'],
      ['Typecho 主题里那些会踩的坑', '自定义模板必须写 @package custom；themeUrl 属性可能是空串。', '2026.01.10', 'typecho'],
      ['夜里把深空调成 #020617', '夜间模式不是简单反色，卡片透明度与光晕都要重新配。', '2026.01.08', 'css|color'],
      ['用 unicode-range 把中文字体切薄', '自托管思源宋体，按需加载，首屏只下拉几片。', '2026.01.05', 'css|typecho'],
    ]
      .map(
        ([title, desc, date, tags]) => `
    <article class="sg-glass sg-archive-card" data-sg-archive-card data-keywords="${title} ${desc}" data-tags="${tags}">
      <a class="sg-archive-cover" href="#" tabindex="-1" aria-hidden="true"><img src="${IMG.bg}" alt="" loading="lazy"></a>
      <div class="sg-archive-body">
        <div class="sg-archive-date"><time>${date}</time></div>
        <h3><a href="#">${title}</a></h3>
        <p>${desc}</p>
        <div class="sg-archive-tags">${tags.split('|').map((t) => `<span class="sg-archive-tag">#${t}</span>`).join('')}</div>
      </div>
    </article>`,
      )
      .join('')}
  </div>
  <p class="sg-filter-empty" data-sg-archive-empty hidden>这个频段没有接收到任何信号，换个关键词或标签试试。</p>
</main>
${foot()}`;

const notfoundPage = `${head('404 - Soraglass')}
<main class="sg-main sg-main--pad" data-sg-page="404">
  <section class="sg-glass sg-notfound">
    <span class="sg-notfound-code">404</span>
    <h1>这片天空下好像什么都没有</h1>
    <p>链接可能已经失效，或者页面被移到了别处。不如回到首页重新出发。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="#">${icons.arrowLeft}回到首页</a>
      <a class="sg-btn sg-btn--ghost" href="#">${icons.folder}浏览全部归档</a>
    </div>
  </section>
</main>
${foot()}`;

/* 时光机（page-time.php）：年报式页头 + 里程碑/模型面板 + 大年份分区（轴 + 轮次 + 子项，里程碑已并入时间轴） */
const sgTimeMsRound = (lead, desc) => `<li class="sg-time-round">
  <span class="sg-time-round-num" aria-hidden="true">1</span>
  <div class="sg-time-round-body">
    <p class="sg-time-round-title">${lead} <span class="sg-time-ms-tag">里程碑</span></p>
    <p class="sg-time-round-text">${desc}</p>
  </div>
</li>`;
const sgTimeRound = (n, lead, subs) => `<li class="sg-time-round">
  <span class="sg-time-round-num" aria-hidden="true">${n}</span>
  <div class="sg-time-round-body">
    <p class="sg-time-round-title">${lead}</p>
    <ol class="sg-time-subs">
      ${subs.map((t, i) => `<li><span class="sg-time-sub-num" aria-hidden="true">${i + 1}</span><div class="sg-time-sub-text"><strong>小节标题</strong>：${t}</div></li>`).join('')}
    </ol>
  </div>
</li>`;
const sgTimeYearSection = (year, meta, inner) => `
  <section class="sg-time-year" data-sg-time-year="${year}">
    <div class="sg-time-year-head">
      <h2 class="sg-time-year-num">${year}</h2>
      <span class="sg-time-year-meta">${meta}</span>
    </div>
    <ol class="sg-time">${inner}</ol>
  </section>`;
const timePage = `${head('时光机 - Soraglass')}
<main class="sg-main sg-main--pad" data-sg-page="time">
  <div class="sg-time-sheet">
  <header class="sg-time-hero">
    <span class="sg-time-badge">SITE CHANGELOG</span>
    <h1 class="sg-time-title">时光机</h1>
    <p class="sg-time-stats">
      <span>从 <b>2023-11-02</b> 记到 <b>2026-11-22</b></span><i>·</i>
      <span>历时 <b>1116</b> 天（约 3 年 20 天）</span><i>·</i>
      <span>更新于<b>今天</b></span><i>·</i>
      <span>共 <b>73</b> 条记录</span><i>·</i>
      <span>横跨 <b>3</b> 个年份</span>
    </p>
    <div class="sg-time-yearpills" role="group" aria-label="按年份筛选" data-sg-time-pills>
      <button class="sg-time-yearpill" type="button" data-sg-time-pill="2026" aria-pressed="false">
        <span class="sg-time-yearpill-year">2026</span><span class="sg-time-yearpill-count">71 条</span>
        <span class="sg-time-yearpill-bar" aria-hidden="true"><span style="width:100%"></span></span>
      </button>
      <button class="sg-time-yearpill" type="button" data-sg-time-pill="2025" aria-pressed="false">
        <span class="sg-time-yearpill-year">2025</span><span class="sg-time-yearpill-count">1 条</span>
        <span class="sg-time-yearpill-bar" aria-hidden="true"><span style="width:4%"></span></span>
      </button>
      <button class="sg-time-yearpill" type="button" data-sg-time-pill="2023" aria-pressed="false">
        <span class="sg-time-yearpill-year">2023</span><span class="sg-time-yearpill-count">1 条</span>
        <span class="sg-time-yearpill-bar" aria-hidden="true"><span style="width:4%"></span></span>
      </button>
    </div>
  </header>
  <section class="sg-time-milestones" aria-label="站史里程碑">
    <h2 class="sg-time-section-title">站史 · 里程碑</h2>
    <ol class="sg-time-ms-list">
      <li class="sg-time-ms"><time class="sg-time-ms-date">2023-11-02</time><div class="sg-time-ms-body"><h3>拥有属于自己的博客</h3><p>大二这年，用 WordPress 搭起了自己的第一台博客，主题 Astra</p></div></li>
      <li class="sg-time-ms"><time class="sg-time-ms-date">2025-12-18</time><div class="sg-time-ms-body"><h3>博客迁移到 Typecho</h3><p>从 WordPress 整体迁移，主题换用 Butterfly 移植版</p></div></li>
      <li class="sg-time-ms"><time class="sg-time-ms-date">2026-11-06</time><div class="sg-time-ms-body"><h3>开始开发自己的主题</h3><p>决定基于 Typecho 框架，开发一个属于自己的博客主题 —— Soraglass（空璃）</p></div></li>
    </ol>
  </section>
  <section class="sg-time-models" aria-label="使用过的模型与占比" data-sg-model-chart>
    <h2 class="sg-time-section-title">使用过的模型</h2>
    <ul class="sg-time-model-list">
      <li class="sg-time-model"><span class="sg-time-model-name">Claude Sonnet</span><span class="sg-time-model-bar" aria-hidden="true"><span style="width:60%"></span></span><span class="sg-time-model-ratio">60%</span></li>
      <li class="sg-time-model"><span class="sg-time-model-name">GLM</span><span class="sg-time-model-bar" aria-hidden="true"><span style="width:40%"></span></span><span class="sg-time-model-ratio">40%</span></li>
    </ul>
  </section>
  <section class="sg-time-year" data-sg-time-year="2026">
    <div class="sg-time-year-head">
      <h2 class="sg-time-year-num">2026</h2>
      <span class="sg-time-year-meta">70 条记录 · 7 天</span>
    </div>
    <ol class="sg-time">
      <li class="sg-time-item" data-sg-time-latest>
        <h3 class="sg-time-when"><span class="sg-time-when-year">2026</span><span class="sg-time-when-day">11-22</span></h3>
        <span class="sg-time-latest">最新记录</span>
        <div class="sg-time-card">
          <ol class="sg-time-rounds">
            ${sgTimeMsRound('开始开发自己的主题', '决定基于 Typecho 框架，开发一个属于自己的博客主题 —— Soraglass（空璃）')}
          </ol>
        </div>
      </li>
      <li class="sg-time-item">
        <h3 class="sg-time-when"><span class="sg-time-when-year">2026</span><span class="sg-time-when-day">11-21</span></h3>
        <div class="sg-time-card">
          <ol class="sg-time-rounds">
            ${sgTimeRound(1, '第五十三轮（用户七项）', ['标签云背景、封面文字可读性、分类芯片 hover、页头网格、网站图标、精选卡宽。'])}
          </ol>
        </div>
      </li>
    </ol>
  </section>
  ${sgTimeYearSection('2025', '1 条记录 · 1 天', `
    <li class="sg-time-item">
      <h3 class="sg-time-when"><span class="sg-time-when-year">2025</span><span class="sg-time-when-day">12-18</span></h3>
      <div class="sg-time-card"><ol class="sg-time-rounds">${sgTimeMsRound('博客迁移到 Typecho', '从 WordPress 整体迁移，主题换用 Butterfly 移植版')}</ol></div>
    </li>`)}
  ${sgTimeYearSection('2023', '1 条记录 · 1 天', `
    <li class="sg-time-item">
      <h3 class="sg-time-when"><span class="sg-time-when-year">2023</span><span class="sg-time-when-day">11-02</span></h3>
      <div class="sg-time-card"><ol class="sg-time-rounds">${sgTimeMsRound('拥有属于自己的博客', '大二这年，用 WordPress 搭起了自己的第一台博客，主题 Astra')}</ol></div>
    </li>`)}
  </div>
</main>
${foot()}`;

/* ============================================================
   岁轮预览（镜像 page-season.php + partials/season-wheel.php / season-range.php / season-table.php）
   几何、类名、data 挂点与模板逐一对应；日期取 data/season-terms.php 的 2026 行，
   元数据镜像 lib/season.php（静态文化数据，不随构建变化）。
   生成 1 个当令页 + 24 节气页 + 12 宫页，互相链接，供 PJAX / 拖动 / 求签验证。
   ============================================================ */
const SG_YEAR = 2026;
const sgRow = fs.readFileSync(path.join(root, 'data/season-terms.php'), 'utf8').match(new RegExp(`${SG_YEAR} => '(\\d{96})'`))?.[1];
if (!sgRow) { console.error('岁轮预览：data/season-terms.php 缺 2026 行'); process.exit(1); }
const SG_DATES = Array.from({ length: 24 }, (_, i) => sgRow.slice(i * 4, i * 4 + 4));
const SG_META = [
  ['chunfen', '春分'], ['qingming', '清明'], ['guyu', '谷雨'], ['lixia', '立夏'],
  ['xiaoman', '小满'], ['mangzhong', '芒种'], ['xiazhi', '夏至'], ['xiaoshu', '小暑'],
  ['dashu', '大暑'], ['liqiu', '立秋'], ['chushu', '处暑'], ['bailu', '白露'],
  ['qiufen', '秋分'], ['hanlu', '寒露'], ['shuangjiang', '霜降'], ['lidong', '立冬'],
  ['xiaoxue', '小雪'], ['daxue', '大雪'], ['dongzhi', '冬至'], ['xiaohan', '小寒'],
  ['dahan', '大寒'], ['lichun', '立春'], ['yushui', '雨水'], ['jingzhe', '惊蛰'],
];
const SG_HOU = [
  ['玄鸟至', '雷乃发声', '始电'], ['桐始华', '田鼠化为鴽', '虹始见'], ['萍始生', '鸣鸠拂其羽', '戴胜降于桑'],
  ['蝼蝈鸣', '蚯蚓出', '王瓜生'], ['苦菜秀', '靡草死', '麦秋至'], ['螳螂生', '鵙始鸣', '反舌无声'],
  ['鹿角解', '蜩始鸣', '半夏生'], ['温风至', '蟋蟀居壁', '鹰始击'], ['腐草为萤', '土润溽暑', '大雨时行'],
  ['凉风至', '白露降', '寒蝉鸣'], ['鹰乃祭鸟', '天地始肃', '禾乃登'], ['鸿雁来', '玄鸟归', '群鸟养羞'],
  ['雷始收声', '蛰虫坯户', '水始涸'], ['鸿雁来宾', '雀入大水为蛤', '菊有黄华'], ['豺乃祭兽', '草木黄落', '蛰虫咸俯'],
  ['水始冰', '地始冻', '雉入大水为蜃'], ['虹藏不见', '天气上升地气下降', '闭塞而成冬'], ['鹖鴠不鸣', '虎始交', '荔挺出'],
  ['蚯蚓结', '麋角解', '水泉动'], ['雁北乡', '鹊始巢', '雉始雊'], ['鸡始乳', '征鸟厉疾', '水泽腹坚'],
  ['东风解冻', '蛰虫始振', '鱼陟负冰'], ['獭祭鱼', '候雁北', '草木萌动'], ['桃始华', '仓庚鸣', '鹰化为鸠'],
];
const SG_GLOSS = [
  '昼夜对半，风里开始有土腥气。', '雨多了，桐花开，天也洗干净了。', '最后一场春雨，秧苗扎下根。',
  '日头开始咬人，蛙声从夜里起来。', '麦粒灌浆未满，雨水却先满了。', '一边收一边种，最忙的几天。',
  '白天最长，之后一天短过一天。', '热得发闷，蟋蟀躲进墙缝。', '一年最热，雷雨说来就来。',
  '还热，但风里有了一丝凉。', '暑气退场，早晚开始有凉意。', '草叶结露，天亮时一片白。',
  '昼夜再对半，天高得厉害。', '露水凉透了，菊花开了。', '夜里结霜，最后一茬叶子落尽。',
  '水面初结薄冰，冬天从这天算起。', '不再见虹，天光短，万物收进土里。', '雪落得住了，夜最长前的一段。',
  '白天最短，数九从这天开始。', '冷到骨头里，但白天在变长。', '一年最冷，河面冻实。',
  '土松了，风转暖，一切重新起头。', '雪化成雨，泥里冒出新芽。', '一声雷，虫子都醒了。',
];
const SG_GONGS = [
  ['aries', '白羊'], ['taurus', '金牛'], ['gemini', '双子'], ['cancer', '巨蟹'],
  ['leo', '狮子'], ['virgo', '处女'], ['libra', '天秤'], ['scorpio', '天蝎'],
  ['sagittarius', '射手'], ['capricorn', '摩羯'], ['aquarius', '水瓶'], ['pisces', '双鱼'],
];
const SG_COUNTS = [2, 1, 0, 3, 1, 2, 4, 1, 0, 2, 1, 3, 2, 0, 1, 2, 3, 1, 2, 0, 1, 1, 2, 1];
const SG_NOW = 16; /* 2026-11-25 → 小雪；dayInTerm = 4 → 一候 */
const SG_NOW_HOU = 0;
const SG_HOU_ORD = ['一候', '二候', '三候'];
const sgMD = (mmdd) => `${mmdd.slice(0, 2)}.${mmdd.slice(2)}`;
const sgEndMD = (i) => {
  const cur = +SG_DATES[i], next = +SG_DATES[(i + 1) % 24];
  const nm = SG_DATES[(i + 1) % 24];
  const d = new Date(next < cur ? SG_YEAR + 1 : SG_YEAR, +nm.slice(0, 2) - 1, +nm.slice(2));
  d.setDate(d.getDate() - 1);
  return `${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
};
const sgTermURL = (i) => `./preview-season-t${i}.html`;
const sgGongURL = (g) => `./preview-season-g${g}.html`;
const sgGongCount = (g) => SG_COUNTS[g * 2] + SG_COUNTS[g * 2 + 1];
const sgSeasonOf = (i) => (i >= 3 && i < 9 ? 'summer' : i >= 9 && i < 15 ? 'autumn' : i >= 15 && i < 21 ? 'winter' : 'spring');

/* 盘面几何（与 partials/season-wheel.php 同一套公式，viewBox 520 / 圆心 260） */
const sgPt = (r, deg) => { const a = (deg * Math.PI) / 180; return [260 + r * Math.sin(a), 260 - r * Math.cos(a)]; };
const sgF = (n, p = 2) => Number(n.toFixed(p));
const sgArc = (rIn, rOut, a0, a1) => {
  const [x0, y0] = sgPt(rOut, a0), [x1, y1] = sgPt(rOut, a1), [x2, y2] = sgPt(rIn, a1), [x3, y3] = sgPt(rIn, a0);
  return `M${sgF(x0)} ${sgF(y0)} A${rOut} ${rOut} 0 0 1 ${sgF(x1)} ${sgF(y1)} L${sgF(x2)} ${sgF(y2)} A${rIn} ${rIn} 0 0 0 ${sgF(x3)} ${sgF(y3)} Z`;
};
/* 日轨：与 PHP 同式，dayLon 取「今天」（244°），所有 2026 页共用 */
const sgDayLon = SG_NOW * 15 + 15 * (4 / 15);
const sgSinDec = Math.sin((23.44 * Math.PI) / 180) * Math.sin((sgDayLon * Math.PI) / 180);
const sgSunH = sgF(88 + 28 * sgSinDec, 1);
const sgShadowLen = sgF(96 - 26 * sgSinDec, 1);
const sgSunR = 47;
const sgSunArcR = (sgSunR * sgSunR + sgSunH * sgSunH) / (2 * sgSunH);
const sgSunArcCy = 260 + sgSunArcR - sgSunH;
const sgSunHalf = (Math.asin(sgSunR / sgSunArcR) * 180) / Math.PI;
const sgSunDeg0 = 90 - sgSunHalf, sgSunDeg1 = 90 + sgSunHalf;
const sgSunDotDeg = sgSunDeg0 + (sgSunDeg1 - sgSunDeg0) * ((SG_NOW_HOU + 0.5) / 3);
const sgSunDotAt = (deg) => [260 + sgSunArcR * Math.sin((deg * Math.PI) / 180), sgSunArcCy - sgSunArcR * Math.cos((deg * Math.PI) / 180)];
const [sgSDx, sgSDy] = sgSunDotAt(sgSunDotDeg);
const [sgSunX0, sgSunY0] = sgSunDotAt(sgSunDeg0);
const [sgSunX1, sgSunY1] = sgSunDotAt(sgSunDeg1);
const sgMNow = Math.floor(SG_NOW / 2) * 2;
const [sgYx, sgYy] = sgPt(248, 315);

function sgWheelHTML(selTerm, selGong) {
  const focus = selGong >= 0 ? selGong * 30 + 15 : selTerm * 15 + 7.5;
  const rot = sgF(-focus);
  const centerName = selGong >= 0
    ? `${SG_GONGS[selGong][1]}宫`
    : `${SG_META[selTerm][1]} · ${SG_GONGS[Math.floor(selTerm / 2)][1]}宫`;
  const centerSub = selGong >= 0
    ? `${SG_META[selGong * 2][1]} – ${SG_META[selGong * 2 + 1][1]} · ${sgGongCount(selGong)} 篇`
    : `${sgMD(SG_DATES[selTerm])} – ${sgEndMD(selTerm)}`;

  const gongs = SG_GONGS.map((g, i) => {
    const mid = i * 30 + 15;
    const [lx, ly] = sgPt(164, mid);
    const span = `${SG_META[i * 2][1]} – ${SG_META[i * 2 + 1][1]}`;
    const count = sgGongCount(i);
    const cls = `sg-wheel-gong is-${i % 2 === 0 ? 'yang' : 'yin'}${i === selGong ? ' is-sel' : ''}${count > 0 ? '' : ' is-empty'}`;
    return `<a class="${cls}" href="${sgGongURL(i)}" data-sg-gong-i="${i}" data-sg-label="${g[1]}宫 · ${span}" tabindex="-1"${i === selGong ? ' aria-current="true"' : ''}>
      <title>${g[1]}宫 · ${span} · ${count === 0 ? '未落笔' : count + ' 篇'}</title>
      <path class="sg-wheel-gong-arc" d="${sgArc(148, 180, i * 30 + 0.8, i * 30 + 29.2)}" aria-hidden="true"></path>
      <text class="sg-wheel-gong-label" x="${sgF(lx, 1)}" y="${sgF(ly, 1)}" text-anchor="middle" dominant-baseline="middle" style="transform-origin: ${sgF(lx, 1)}px ${sgF(ly, 1)}px" aria-hidden="true">${g[1]}</text>
    </a>`;
  }).join('\n');

  const terms = SG_META.map((t, i) => {
    const mid = i * 15 + 7.5;
    const [lx, ly] = sgPt(210, mid);
    const [ldx0, ldy0] = sgPt(206, mid);
    const gong = Math.floor(i / 2);
    const count = SG_COUNTS[i];
    const isCurrent = i === SG_NOW;
    const sub = isCurrent ? '12 天后是大雪' : `${sgMD(SG_DATES[i])} – ${sgEndMD(i)}`;
    let cls = `sg-wheel-term is-${sgSeasonOf(i)}`;
    if (isCurrent) cls += ' is-current';
    if (i === selTerm) cls += ' is-sel';
    if (count === 0) cls += ' is-empty';
    return `<a class="${cls}" href="${sgTermURL(i)}" data-sg-term-i="${i}" data-sg-gong="${gong}" data-sg-label="${t[1]} · ${SG_GONGS[gong][1]}宫" data-sg-sub="${i * 15}° · ${sub}" tabindex="${i === selTerm ? '0' : '-1'}"${i === selTerm ? ' aria-current="true"' : ''}>
      <title>${t[1]} · ${sgMD(SG_DATES[i])} · ${count === 0 ? '未落笔' : count + ' 篇'}</title>
      <path class="sg-wheel-term-arc" d="${sgArc(182, 238, i * 15 + 0.9, i * 15 + 14.1)}" aria-hidden="true"></path>
      <text class="sg-wheel-term-label" x="${sgF(lx, 1)}" y="${sgF(ly, 1)}" text-anchor="middle" dominant-baseline="middle" style="transform-origin: ${sgF(lx, 1)}px ${sgF(ly, 1)}px" aria-hidden="true">${t[1]}</text>
      <line class="sg-wheel-term-leader" x1="${sgF(ldx0, 1)}" y1="${sgF(ldy0, 1)}" x2="260" y2="260" aria-hidden="true"></line>
    </a>`;
  }).join('\n');

  const houTicks = Array.from({ length: 24 }, (_, i) => [5, 10].map((h) => {
    const [x0, y0] = sgPt(240, i * 15 + h), [x1, y1] = sgPt(246, i * 15 + h);
    return `<line x1="${sgF(x0)}" y1="${sgF(y0)}" x2="${sgF(x1)}" y2="${sgF(y1)}"></line>`;
  }).join('')).join('\n');

  const marks = Array.from({ length: 12 }, (_, k) => {
    const m = k * 2;
    const [mx, my] = sgPt(248, m * 15);
    return `<circle class="sg-wheel-mark${m === sgMNow ? ' is-now' : ''}" cx="${sgF(mx)}" cy="${sgF(my)}" r="${m === 0 ? 3.4 : 2.1}"></circle>`;
  }).join('\n');
  const [px, py] = sgPt(248, sgMNow * 15);

  return `<div class="sg-wheel" data-sg-wheel>
  <nav class="sg-wheel-disc" aria-label="二十四节气与黄道十二宫">
    <div class="sg-wheel-plot" data-sg-wheel-plot style="--sg-rot: ${rot}deg">
      <svg class="sg-wheel-svg" viewBox="0 0 520 520" role="group" aria-label="双刻度轮盘">
        <g class="sg-wheel-rotor">
          ${gongs}
          ${terms}
          <g class="sg-wheel-hou" aria-hidden="true">${houTicks}</g>
          <g class="sg-wheel-etch" aria-hidden="true"><circle cx="260" cy="260" r="141"></circle><circle cx="260" cy="260" r="136" stroke-dasharray="1 5"></circle></g>
          <g class="sg-wheel-marks" aria-hidden="true">
            ${marks}
            <circle class="sg-wheel-pulse" cx="${sgF(px)}" cy="${sgF(py)}" r="3"></circle>
            <path class="sg-wheel-year" d="M${sgF(sgYx, 1)} ${sgF(sgYy, 1)} L${sgF(sgYx + 5, 1)} ${sgF(sgYy - 9, 1)}" aria-hidden="true"></path>
            <text class="sg-wheel-year-label" x="${sgF(sgYx + 12, 1)}" y="${sgF(sgYy - 12, 1)}" text-anchor="middle" style="transform-origin: ${sgF(sgYx + 12, 1)}px ${sgF(sgYy - 12, 1)}px">岁首</text>
          </g>
        </g>
        <g class="sg-wheel-needle" aria-hidden="true">
          <line class="sg-wheel-needle-tail" x1="260" y1="36" x2="260" y2="47"></line>
          <path class="sg-wheel-needle-body" d="M260 34 L265.5 12 L260 2 L254.5 12 Z"></path>
          <circle class="sg-wheel-needle-tip" cx="260" cy="32" r="2.2"></circle>
        </g>
        <g class="sg-wheel-sun" aria-hidden="true">
          <path class="sg-wheel-sun-track" d="M${sgF(sgSunX0)} ${sgF(sgSunY0)} A${sgF(sgSunArcR)} ${sgF(sgSunArcR)} 0 0 1 ${sgF(sgSunX1)} ${sgF(sgSunY1)}" style="--sg-sun-h: ${sgSunH};"></path>
          <circle class="sg-wheel-sun-dot" r="2.5" cx="${sgF(sgSDx)}" cy="${sgF(sgSDy)}" data-sg-arc-cx="260" data-sg-arc-cy="${sgF(sgSunArcCy)}" data-sg-arc-r="${sgF(sgSunArcR, 3)}" data-sg-arc-deg0="${sgF(sgSunDeg0, 4)}" data-sg-arc-deg1="${sgF(sgSunDeg1, 4)}"></circle>
        </g>
        <g class="sg-wheel-sweep" aria-hidden="true">
          <defs><linearGradient id="sg-sweep-grad" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="var(--sg-warm)" stop-opacity="0"></stop><stop offset="100%" stop-color="var(--sg-warm)" stop-opacity=".08"></stop></linearGradient></defs>
          <path class="sg-wheel-sweep-wedge" d="M260 260 L260 40 L300 48 Z"></path>
          <path d="M260 260 L260 40 L300 48 Z" fill="url(#sg-sweep-grad)"></path>
        </g>
        <g class="sg-wheel-needle-shadow" aria-hidden="true"><path class="sg-wheel-needle-shadow-path" d="M260 32 L260 ${sgF(32 + sgShadowLen, 1)}"></path></g>
      </svg>
      <div class="sg-wheel-center" data-sg-wheel-center>
        <span class="sg-wheel-center-name" data-sg-wheel-name>${centerName}</span>
        <span class="sg-wheel-center-sub" data-sg-wheel-sub>${centerSub}</span>
        <span class="sg-wheel-hou-dots" aria-hidden="true">
          <span class="sg-wheel-hou-dots-row">${[0, 1, 2].map((h) => `<i class="${h === SG_NOW_HOU ? 'is-now' : ''}"></i>`).join('')}</span>
          <span class="sg-wheel-hou-caption">${SG_HOU_ORD[SG_NOW_HOU]} · ${SG_HOU[SG_NOW][SG_NOW_HOU]}</span>
        </span>
      </div>
      <span class="sg-seal sg-seal--draw" aria-hidden="true">求签</span>
    </div>
    <ul class="sg-sr-only">${SG_META.map((t, i) => `<li><a href="${sgTermURL(i)}">${t[1]}，${sgMD(SG_DATES[i])}，${SG_COUNTS[i]} 篇</a></li>`).join('')}</ul>
  </nav>
  <div class="sg-wheel-foot">
    <button type="button" class="sg-wheel-draw" data-sg-draw aria-disabled="false"><span class="sg-wheel-draw-cn">求签</span><span class="sg-wheel-draw-en">draw a lot</span></button>
  </div>
</div>
<div class="sg-season-strip" data-sg-strip>
  <ol class="sg-strip-terms" aria-label="节气滑条">${SG_META.map((t, i) => `<li><a class="sg-strip-term${i === SG_NOW ? ' is-current' : ''}${i === selTerm ? ' is-sel' : ''}${SG_COUNTS[i] === 0 ? ' is-empty' : ''}" href="${sgTermURL(i)}"${i === selTerm ? ' aria-current="true"' : ''}><span>${t[1]}</span><em>${SG_COUNTS[i] === 0 ? '–' : SG_COUNTS[i]}</em></a></li>`).join('')}</ol>
  <ol class="sg-strip-gongs" aria-label="十二宫">${SG_GONGS.map((g, i) => `<li><a class="sg-strip-gong${i === selGong ? ' is-sel' : ''}${sgGongCount(i) > 0 ? ' has-content' : ''}" href="${sgGongURL(i)}" title="${g[1]}宫 · ${sgGongCount(i)} 篇" aria-label="${g[1]}宫，${sgGongCount(i)} 篇">${i === 0 || i === 11 ? `<span>${g[1]}</span>` : '<i></i>'}</a></li>`).join('')}</ol>
</div>`;
}

const SG_POST_TITLES = ['物候记', '夜航书', '玻璃与雪', '给远人的信', '炉边札记', '晴窗抄', '风土小识', '迟来的雨'];
function sgChronicleHTML(selTerm, selGong) {
  const baseTerm = selGong >= 0 ? selGong * 2 : selTerm;
  const title = selGong >= 0 ? `${SG_GONGS[selGong][1]}宫` : SG_META[selTerm][1];
  const eyebrow = selGong >= 0 ? '这一宫' : '这一段';
  const total = selGong >= 0 ? sgGongCount(selGong) : SG_COUNTS[selTerm];
  const sub = selGong >= 0
    ? `${SG_META[baseTerm][1]} – ${SG_META[baseTerm + 1][1]}`
    : `${sgMD(SG_DATES[selTerm])} – ${sgEndMD(selTerm)} · ${SG_GLOSS[selTerm]}`;
  const rail = [0, 1, 2].map((h) => {
    const d0 = new Date(SG_YEAR, +SG_DATES[baseTerm].slice(0, 2) - 1, +SG_DATES[baseTerm].slice(2));
    const fmt = (d) => `${String(d.getMonth() + 1).padStart(2, '0')}.${String(d.getDate()).padStart(2, '0')}`;
    const from = new Date(d0); from.setDate(from.getDate() + h * 5);
    const to = new Date(d0); to.setDate(to.getDate() + h * 5 + 4);
    return `<button type="button" class="sg-range-hou-btn${h === 0 ? ' is-now' : ''}" data-sg-hou-index="${h}"${h === 0 ? ' aria-current="true"' : ''}>
      <span class="sg-range-hou-ord">${SG_HOU_ORD[h]}</span>
      <span class="sg-range-hou-name">${SG_HOU[baseTerm][h]}</span>
      <span class="sg-range-hou-date">${fmt(from)} – ${fmt(to)}</span>
    </button>`;
  }).join('');
  /* 文章按候轮转分组；0 篇走空态（与模板一致） */
  const groups = [[], [], []];
  for (let k = 0; k < total; k++) groups[k % 3].push(k);
  const groupsHTML = total === 0
    ? `<div class="sg-range-empty"><p>这一段还没有落笔。</p><p class="sg-range-near"><a href="./preview-season.html">回到当前这一段 →</a></p></div>`
    : groups.map((posts, h) => posts.length === 0 ? '' : `<section class="sg-range-hou" id="hou-${baseTerm}-${h}">
      <h3 class="sg-range-hou-title">${SG_META[baseTerm][1]} · ${SG_HOU_ORD[h]} · ${SG_HOU[baseTerm][h]}</h3>
      <div class="sg-range-grid">${posts.map((k) => `<a class="sg-range-card is-plain" href="./preview-post.html"><span class="sg-range-card-body"><span class="sg-range-card-title">${SG_POST_TITLES[(baseTerm + h + k) % SG_POST_TITLES.length]} · ${SG_META[baseTerm][1]}手记</span><span class="sg-range-card-date">${sgMD(SG_DATES[baseTerm])} 第 ${h * 5 + k + 1} 天</span></span></a>`).join('')}</div>
    </section>`).join('');
  const gongRows = SG_GONGS.map((g, i) => `<tr${i === selGong ? ' class="is-sel"' : i === Math.floor(SG_NOW / 2) ? ' class="is-now"' : ''}>
    <th scope="row">${g[1]}宫</th><td>${SG_META[i * 2][1]} ${sgMD(SG_DATES[i * 2])}</td><td>${SG_META[i * 2][1]} → ${SG_META[i * 2 + 1][1]}</td><td class="is-num">${sgGongCount(i)}</td><td class="is-link"><a href="${sgGongURL(i)}" aria-label="查看${g[1]}宫的 30 天">→</a></td>
  </tr>`).join('');
  const termRows = SG_META.map((t, i) => `<tr${i === selTerm ? ' class="is-sel"' : ''}>
    <th scope="row">${t[1]}</th><td>${sgMD(SG_DATES[i])}</td><td class="is-num">${i * 15}°</td><td>${SG_GONGS[Math.floor(i / 2)][1]}宫</td><td class="is-num">${SG_COUNTS[i]}</td><td class="is-link"><a href="${sgTermURL(i)}" aria-label="查看${t[1]}的 15 天">→</a></td>
  </tr>`).join('');
  return `<section class="sg-season-chronicle" data-sg-reveal>
  <section class="sg-season-range" aria-label="这一段时间的内容" data-sg-range>
    <div class="sg-range-hou-rail" aria-label="三候尺">${rail}</div>
    <header class="sg-range-head">
      <span class="sg-range-eyebrow">${eyebrow}</span>
      <h2 class="sg-range-title" id="sg-range-title">${title}</h2>
      <p class="sg-range-sub">${sub}</p>
      <span class="sg-range-count">${total} 篇</span>
    </header>
    ${groupsHTML}
    <div class="sg-range-notes" aria-label="这一段写下的碎语"><p class="sg-range-note">风把日子吹薄了，字还留在纸上。</p></div>
  </section>
  <section class="sg-season-table" aria-label="二十四节气与黄道十二宫对照表">
    <header class="sg-table-head"><h2 class="sg-table-title">对照表</h2><p class="sg-table-note">宫界落在中气实日上，随年份浮动；篇数按发表日期统计。</p></header>
    <table class="sg-table sg-table-gong">
      <caption class="sg-sr-only">十二宫及其覆盖的节气</caption>
      <thead><tr><th scope="col">宫</th><th scope="col">起于</th><th scope="col">覆盖</th><th scope="col">篇数</th><th scope="col"><span class="sg-sr-only">打开</span></th></tr></thead>
      <tbody>${gongRows}</tbody>
    </table>
    <details class="sg-table-more"><summary>展开 24 节气</summary>
      <table class="sg-table sg-table-term">
        <caption class="sg-sr-only">二十四节气及其所属宫</caption>
        <thead><tr><th scope="col">节气</th><th scope="col">日期</th><th scope="col">黄经</th><th scope="col">宫</th><th scope="col">篇数</th><th scope="col"><span class="sg-sr-only">打开</span></th></tr></thead>
        <tbody>${termRows}</tbody>
      </table>
    </details>
  </section>
</section>`;
}

function sgSeasonPage(selTerm, selGong) {
  const isGong = selGong >= 0;
  const term = isGong ? selGong * 2 : selTerm;
  const t = SG_META[term];
  const docTitle = isGong ? `${SG_GONGS[selGong][1]}宫 · ${t[1]} · 岁候` : `${t[1]} · ${SG_GONGS[Math.floor(term / 2)][1]}宫 · 岁候`;
  const readings = isGong
    ? [['黄经', `${term * 15}°`, '春分起算 · λ'], ['起于', sgMD(SG_DATES[term]), t[1]], ['跨度', '30 天', '两节气 · 一宫'], ['止于', sgEndMD(term + 1), '这一段最后一天']]
    : term === SG_NOW
      ? [['黄经', `${term * 15}°`, '春分起算 · λ'], ['候', '一候 · 虹藏不见', '小雪 第 1 候'], ['跨度', '15 天', '一节气的长度'], ['距大雪', '12 天', '太阳走完这一段']]
      : [['黄经', `${term * 15}°`, '春分起算 · λ'], ['起于', sgMD(SG_DATES[term]), t[1]], ['跨度', '15 天', '一节气的长度'], ['止于', sgEndMD(term), '这一段最后一天']];
  const prev = (term + 23) % 24, next = (term + 1) % 24;
  return `${head(docTitle)}
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad sg-season" data-sg-page="season">
  <div class="sg-season-sheet">
  <section class="sg-season-stage" data-sg-stage data-sg-term-counts="${SG_COUNTS.join(',')}">
    <header class="sg-stage-copy">
      <p class="sg-stage-kicker">SEASONAL ALMANAC · 岁时记</p>
      <h1 class="sg-season-title">岁轮</h1>
      <p class="sg-season-sub">日子的两种读法</p>
      <p class="sg-stage-romance">${SG_GLOSS[term]}</p>
      <details class="sg-stage-manual"><summary>这一页怎么读</summary><p>一条黄道，两种读法：节气把黄道分成二十四段，宫分成十二段。宫不是天上的星座，是同一条黄道的十二等分。</p></details>
    </header>
    <div class="sg-stage-instrument">
      ${sgWheelHTML(selTerm, selGong)}
      <aside class="sg-stage-readings" aria-label="这一段读数">${readings.map(([k, v, u]) => `<div class="sg-reading"><span class="sg-reading-k">${k}</span><b class="sg-reading-v">${v}</b><span class="sg-reading-u">${u}</span></div>`).join('')}</aside>
    </div>
    <span class="sg-seal sg-seal--stage" aria-label="当前节气 ${t[1]}">${t[1]}</span>
  </section>
  ${sgChronicleHTML(selTerm, selGong)}
  </div>
  <footer class="sg-season-colophon" data-sg-reveal>
    <nav class="sg-season-years" aria-label="年份与相邻节气">
      <div class="sg-years-row"><span class="sg-years-label">年份</span>${[2024, 2025, 2026, 2027, 2028].map((y) => `<a class="sg-year${y === SG_YEAR ? ' is-current' : ''}" href="./preview-season.html"${y === SG_YEAR ? ' aria-current="true"' : ''}>${y}</a>`).join('')}</div>
      <div class="sg-season-nav">
        <a href="${sgTermURL(prev)}">← 上一节气</a>
        <a href="./preview-season.html">回到当令</a>
        <a href="${sgTermURL(next)}">下一节气 →</a>
      </div>
    </nav>
    <div class="sg-season-note">
      <p>采用回归黄道，春分点为白羊宫 0°，与节气同源；不使用恒星黄道。</p>
      <p>宫界按中气实日逐年计算，不采用固定月日的通行星座表。</p>
      <p>此处为黄道十二宫（signs），非天文星座；黄道带上实际有 13 个星座，含蛇夫座。宫名取通行译名，古译作室女 / 人马 / 磨羯 / 宝瓶。</p>
      <p>刻度原点为春分，故 1 月的节气在表盘左上（太阳黄经 285°–345° 在春分之前），这是黄道顺序，不是排版错误；纪年仍用公历年，立春只作「岁首」刻度。</p>
    </div>
  </footer>
</main>
${foot()}`;
}

fs.writeFileSync(path.join(dist, 'preview-season.html'), sgSeasonPage(SG_NOW, -1), 'utf8');
for (let i = 0; i < 24; i++) fs.writeFileSync(path.join(dist, `preview-season-t${i}.html`), sgSeasonPage(i, -1), 'utf8');
for (let g = 0; g < 12; g++) fs.writeFileSync(path.join(dist, `preview-season-g${g}.html`), sgSeasonPage(-1, g), 'utf8');

fs.writeFileSync(path.join(dist, 'preview-index.html'), indexPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-post.html'), postPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-archive.html'), archivePage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-friends.html'), friendsPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-photowall.html'), photowallPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-about.html'), aboutPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-timeline.html'), timelinePage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-time.html'), timePage, 'utf8');
/* 碎语页（notes-stream.php）：sg_page_head 页头 + 行优先响应式卡片网格 */
const notesPage = `${head('碎语 - Soraglass')}
<main class="sg-main sg-main--pad" data-sg-page="notes" data-sg-notes>
  <div class="sg-page-head">
    <div class="sg-page-head-row is-centered">
      <div>
        <h1 class="sg-page-title">碎语</h1>
        <p class="sg-page-sub">短句与随想 · 想到什么写什么</p>
      </div>
    </div>
  </div>
  <div class="sg-notes-bar">
    <div class="sg-notes-tags" role="group" aria-label="按标签筛选">
      <button class="sg-notes-tag is-active" type="button" data-sg-notes-tag="">全部<span>6</span></button>
      <button class="sg-notes-tag" type="button" data-sg-notes-tag="瞎语">瞎语<span>2</span></button>
      <button class="sg-notes-tag" type="button" data-sg-notes-tag="日常">日常<span>2</span></button>
      <button class="sg-notes-tag" type="button" data-sg-notes-tag="随想">随想<span>2</span></button>
    </div>
    <div class="sg-notes-sort" role="group" aria-label="排序">
      <button class="is-active" type="button" data-sg-notes-sort="desc" aria-pressed="true">最新</button>
      <button type="button" data-sg-notes-sort="asc" aria-pressed="false">最早</button>
    </div>
  </div>

  <section class="sg-moments-section" data-sg-tl-month="all">
    <div class="sg-moments-grid" data-sg-tl-items>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-11-22T10:00:00+08:00') / 1000}" data-tag="瞎语">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">3 天前</span></div>
        <time class="sg-moment-day" datetime="2026-11-22"><b aria-hidden="true">22</b><span class="sg-sr-only">2026 年 11 月 22 日</span></time>
      </header>
      <div class="sg-moment-bubble">把喜欢的文字留在夜里，把不确定的事交给明天。毛玻璃的呼吸频率刚刚好，再多一分就糊了。</div>
      <div class="sg-moment-media is-many" data-sg-photo-group>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
      </div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#瞎语</span><span class="sg-moment-loc">📍 杭州</span></footer>
    </article>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-11-18T10:00:00+08:00') / 1000}" data-tag="日常">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">7 天前</span></div>
        <time class="sg-moment-day" datetime="2026-11-18"><b aria-hidden="true">18</b><span class="sg-sr-only">2026 年 11 月 18 日</span></time>
      </header>
      <div class="sg-moment-bubble">背景渐变标题一样流畅，毛玻璃调好了吗？</div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#日常</span><span class="sg-moment-loc">📍 杭州</span></footer>
    </article>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-11-09T10:00:00+08:00') / 1000}" data-tag="随想">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">16 天前</span></div>
        <time class="sg-moment-day" datetime="2026-11-09"><b aria-hidden="true">9</b><span class="sg-sr-only">2026 年 11 月 9 日</span></time>
      </header>
      <div class="sg-moment-bubble">夜里把深空色调成了 #020617，字像掉进银河。</div>
      <div class="sg-moment-media is-one" data-sg-photo-group>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
      </div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#随想</span><span class="sg-moment-mood">安静</span></footer>
    </article>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-10-27T10:00:00+08:00') / 1000}" data-tag="日常">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">1 个月前</span></div>
        <time class="sg-moment-day" datetime="2026-10-27"><b aria-hidden="true">27</b><span class="sg-sr-only">2026 年 10 月 27 日</span></time>
      </header>
      <div class="sg-moment-bubble">把今天的碎语留在这里：改了行优先网格之后，卡片终于按写下的时间从左到右、从上到下出现，不再先沿着第一列往下跑。正文也重新回到舒服的阅读字号，短句不再挤成一小团。标签筛选和最早、最新切换仍然只移动真实卡片，键盘顺序与眼睛看到的顺序保持一致。至于那些还没有想好的事，就交给明天，交给下一次打开这个页面时，窗外刚好吹过的一阵风。也想把绕过的弯路和偶然捡到的好天气记下来：清晨六点的云、雨停后路边的一小块积水、夜里亮着的一扇窗。等以后再回头看，大概会发现，真正留下来的从来不是结论，而是当时认真生活过的心情。</div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#日常</span><span class="sg-moment-loc">📍 杭州</span></footer>
    </article>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-10-12T10:00:00+08:00') / 1000}" data-tag="瞎语">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">上个月</span></div>
        <time class="sg-moment-day" datetime="2026-10-12"><b aria-hidden="true">12</b><span class="sg-sr-only">2026 年 10 月 12 日</span></time>
      </header>
      <div class="sg-moment-bubble">云很轻，风很慢，适合把没说完的话再写一行。</div>
      <div class="sg-moment-media is-two" data-sg-photo-group>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
        <a class="sg-photo" href="${IMG.cover}" data-sg-photo><img src="${IMG.cover}" alt=""></a>
      </div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#瞎语</span><span class="sg-moment-mood">松弛</span></footer>
    </article>
    <article class="sg-moment" data-sg-tl-item data-ts="${Date.parse('2026-09-02T10:00:00+08:00') / 1000}" data-tag="随想">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="${IMG.avatar}" alt="">
        <div class="sg-moment-who"><b>Xinghuisama</b><span class="sg-moment-ago">2 个月前</span></div>
        <time class="sg-moment-day" datetime="2026-09-02"><b aria-hidden="true">2</b><span class="sg-sr-only">2026 年 9 月 2 日</span></time>
      </header>
      <div class="sg-moment-bubble">秋天适合整理桌面，也适合整理那些迟迟没有答案的念头。</div>
      <footer class="sg-moment-foot"><span class="sg-tl-tag">#随想</span><span class="sg-moment-loc">📍 杭州</span></footer>
    </article>
    </div>
  </section>
  <p class="sg-filter-empty" data-sg-notes-empty hidden>没有匹配的碎语，换个词试试。</p>
</main>
`;

/* 网址导航（page-nav.php）：分组书签盒 + 通讯录锚点栏 */
const navData = [
  { name: '算法之家', sites: [
    { name: '代码随想录', url: 'https://programmercarl.com', desc: '一套完整的刷题计划' },
    { name: '小林 coding', url: 'https://xiaolincoding.com', desc: '图解系列作者' },
    { name: 'LeetCode', url: 'https://leetcode.cn', desc: '全球经典算法题库' },
    { name: 'CodeTop', url: 'https://codetop.cc', desc: '大厂面试高频考题汇总' },
    { name: 'Can I use', url: 'https://caniuse.com', desc: '浏览器兼容性速查表' },
    { name: 'MDN', url: 'https://developer.mozilla.org', desc: 'Web 技术文档' },
    { name: 'TypeScript 手册', url: 'https://www.tslang.cn', desc: '官方中文手册' },
    { name: '正则在线测试', url: 'https://regex101.com', desc: '正则表达式调试' },
  ] },
  { name: '实用工具', sites: [
    { name: 'TinyPNG', url: 'https://tinypng.com', desc: '图片无损压缩' },
    { name: 'iLovePDF', url: 'https://www.ilovepdf.com', desc: '在线 PDF 工具箱' },
    { name: 'CloudConvert', url: 'https://cloudconvert.com', desc: '在线格式转换' },
    { name: 'iconfont', url: 'https://www.iconfont.cn', desc: '矢量图标管理平台' },
    { name: 'Excalidraw', url: 'https://excalidraw.com', desc: '手绘风白板画图' },
    { name: 'Speedtest', url: 'https://www.speedtest.cn', desc: '网速测试' },
  ] },
  { name: '观星日常', sites: [
    { name: 'Windy', url: 'https://www.windy.com', desc: '全球可视化天气预报' },
    { name: 'earth', url: 'https://earth.nullschool.net', desc: '全球风场可视化' },
    { name: 'Stellarium', url: 'https://stellarium-web.org', desc: '在线星图' },
    { name: '光污染地图', url: 'https://www.lightpollutionmap.info', desc: '观星必备' },
  ] },
];
const navCardHtml = (s) => `<a class="sg-navbox-card" data-keywords="${s.name} ${s.desc} ${s.url}" href="${s.url}" target="_blank" rel="noopener noreferrer">
      <span class="sg-navbox-icowrap"><span class="sg-navbox-fallback" aria-hidden="true">${s.name.slice(0, 1)}</span><img class="sg-navbox-ico" src="https://favicon.im/${new URL(s.url).host}?larger=true" alt="" loading="lazy" onerror="this.classList.add('is-broken')"></span>
      <span class="sg-navbox-text"><b>${s.name}</b><span>${s.desc}</span></span>
    </a>`;
const navPage = `${head('网址导航 - Soraglass')}
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="nav">
  ${pageHead('网址导航', '收藏的网站与工具，一页打尽。', { center: true })}
  <div class="sg-navbox-wrap">
    <aside class="sg-navbox-side" data-sg-nav-side aria-label="分组锚点">
      ${navData.map((g, i) => `<a class="sg-navbox-side-link${i === 0 ? ' is-active' : ''}" href="#sg-nav-g${i}" data-sg-nav-anchor="sg-nav-g${i}"><span>${g.name}</span><i>${g.sites.length}</i></a>`).join('\n      ')}
    </aside>
    <div class="sg-navbox-main" data-sg-nav-filter>
      ${navData.map((g, i) => `<section class="sg-navbox-group" id="sg-nav-g${i}" data-sg-nav-group>
        <h2 class="sg-navbox-group-title"><i>${String(i + 1).padStart(2, '0')}</i>${g.name}<span>${g.sites.length} 站</span></h2>
        <div class="sg-navbox-grid">${g.sites.map(navCardHtml).join('')}</div>
      </section>`).join('\n      ')}
    </div>
  </div>
</main>
`;

fs.writeFileSync(path.join(dist, 'preview-notes.html'), notesPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-nav.html'), navPage, 'utf8');
fs.writeFileSync(path.join(dist, 'preview-404.html'), notfoundPage, 'utf8');

console.log('✓ 已生成 dist/preview-{index,post,archive,friends,photowall,about,timeline,time,notes,nav,404}.html');
console.log('  这些文件仅供本地核对视觉，release 时会随 dist 一并清理。');
