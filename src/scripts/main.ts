import '../styles/main.css';
import { initCore } from './modules/core';
import { initEffects } from './modules/effects';
import { initCarousels } from './modules/carousel';
import { initPlayer } from './modules/player';
import { initBriefing } from './modules/briefing';
import { initBriefingBg } from './modules/briefing-bg';
import { initArchive } from './modules/archive';
import { initGallery } from './modules/gallery';
import { initArticle } from './modules/article';
import { initNotes } from './modules/notes';
import { initNavBox } from './modules/nav-box';
import { initNavigation } from './modules/navigation';
import { initHome } from './modules/home';
import { initRightside } from './modules/rightside';
import { initNeko } from './modules/neko';
import { initScrollbar } from './modules/scrollbar';
import { initLazyload } from './modules/lazyload';
import { initReveal } from './modules/reveal';
import { initStatsCache } from './modules/stats-cache';
import { initSidebarData } from './modules/sidebar-data';
import { initTimeMachine } from './modules/time-machine';
import { pageSignal } from './modules/lifecycle';
import { initSeasonWheel } from './modules/season';
import { initSeasonSky } from './modules/season-sky';

/**
 * 单独兜住每个模块的异常。
 *
 * 之前是顺序裸调：任何一个模块在某个页面拿到意料之外的 DOM 抛出 TypeError，
 * 后面所有模块都不会执行 —— 表现出来就是「首页卡片点了没反应、播放器不初始化」，
 * 而且控制台只有一条报错，很难定位到是哪个模块。现在一个坏掉不影响其它。
 */
function safe(name: string, run: () => void): void {
  try {
    run();
  } catch (error) {
    if (window.console) window.console.warn(`[soraglass] ${name} 初始化失败：`, error);
  }
}

/**
 * ECharts 柱状图按需加载：只有时光机页存在挂点时才拉取图表 chunk，
 * 让 ~510KB 的 echarts 不进主包、不拖慢全站首屏（其余页面无需它）。
 * 关键点：import() 是异步的，PJAX 可能在 chunk 到达前就换页——
 *   用 pageSignal 记录本次页面生命周期，signal 已 abort 就放弃初始化旧 DOM；
 *   新页面若也是时光机，mountPage 会再调一次（chunk 已缓存，Promise 复用）。
 */
function loadTimeModelChart(): void {
  if (!document.querySelector('[data-sg-model-chart]')) return;
  const signal = pageSignal();
  void import('./modules/time-model-chart')
    .then(({ initTimeModelChart }) => {
      if (signal.aborted) return;
      safe('time-model-chart', initTimeModelChart);
    })
    .catch((error) => {
      if (!signal.aborted && window.console) {
        window.console.warn('[soraglass] time-model-chart 加载失败：', error);
      }
    });
}

function mountPage(): void {
  // 全局骨架先跑，保证首屏与交互不依赖下面任何可选模块
  safe('core', initCore);

  // 各模块自己判断 DOM 是否存在，缺失就空跑，不需要按页面分发
  safe('carousel', initCarousels);
  safe('player', initPlayer);
  /* 背景轮播要先监听，briefing 初始化时就会派发第一次「当前期」事件 */
  safe('briefing-bg', initBriefingBg);
  safe('briefing', initBriefing);
  safe('archive', initArchive);
  safe('gallery', initGallery);
  safe('article', initArticle);
  safe('notes', initNotes);
  safe('nav-box', initNavBox);
  safe('home', initHome);
  safe('sidebar-data', initSidebarData);
  safe('rightside', initRightside);
  safe('neko', initNeko);
  safe('scrollbar', initScrollbar);
  safe('lazyload', initLazyload);
  safe('reveal', initReveal);
  safe('stats-cache', initStatsCache);
  safe('time-machine', initTimeMachine);
  loadTimeModelChart();
  safe('season', initSeasonWheel);
  safe('season-sky', initSeasonSky);
}
function boot(): void {
  mountPage(); safe('effects', initEffects); initNavigation(mountPage);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
