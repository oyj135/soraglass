import '../styles/main.css';
import { initCore } from './modules/core';
import { initEffects } from './modules/effects';
import { initCarousels } from './modules/carousel';
import { initPlayer } from './modules/player';
import { initBriefing } from './modules/briefing';
import { initFilters } from './modules/filter';
import { initArchive } from './modules/archive';
import { initGallery } from './modules/gallery';
import { initArticle } from './modules/article';

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

function boot(): void {
  // 全局骨架先跑，保证首屏与交互不依赖下面任何可选模块
  safe('core', initCore);
  safe('effects', initEffects);

  // 各模块自己判断 DOM 是否存在，缺失就空跑，不需要按页面分发
  safe('carousel', initCarousels);
  safe('player', initPlayer);
  safe('briefing', initBriefing);
  safe('filter', initFilters);
  safe('archive', initArchive);
  safe('gallery', initGallery);
  safe('article', initArticle);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
