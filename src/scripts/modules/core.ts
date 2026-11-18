import { pageOn,pageTimeout,pageFrame,onPageDispose,pageSignal } from './lifecycle';
import { readStore,writeStore } from './runtime';
import { toast } from './feedback';
import { initTheme } from './theme';
import { initNav,initNavMore } from './navigation-ui';
import { initBackground,initImageFallback } from './media';

const SPLASH_MIN = 600;

/** 所有现有检索入口共用清除行为；派发原 input/search 事件复用各页筛选。 */
function initSearchControls(): void {
  document.querySelectorAll<HTMLInputElement>('.sg-search-input[type="search"], [data-sg-playlist-search]').forEach((input) => {
    const host = input.parentElement;
    if (!host || host.querySelector('[data-sg-search-clear]')) return;
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'sg-search-clear';
    clear.setAttribute('data-sg-search-clear', '');
    clear.setAttribute('aria-label', '清除搜索');
    clear.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>';
    const sync = (): void => { clear.hidden = input.value.length === 0; };
    sync();
    host.classList.add('sg-search-field');
    host.appendChild(clear);
    pageOn(input, 'input', sync);
    pageOn(input, 'search', sync);
    pageOn(clear, 'click', () => {
      input.value = '';
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('search', { bubbles: true }));
      input.focus();
    });
    input.form && pageOn(input.form, 'reset', () => pageFrame(sync));
    pageOn(window, 'pageshow', sync);
  });
}


/**
 * P1-9：阅读模式。
 *
 * 隐藏侧栏、封面、文末导航与装饰层，只留正文；状态写 localStorage，
 * 只在存在切换按钮的页面（文章 / 独立页）生效，不影响首页。
 */
function initReadMode(): void {
  const buttons = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-readmode-toggle]'));
  if (!buttons.length) {
    document.documentElement.classList.remove('sg-readmode');
    return;
  }

  const KEY = 'sg-readmode';
  const apply = (on: boolean): void => {
    document.documentElement.classList.toggle('sg-readmode', on);
    buttons.forEach((btn) => btn.setAttribute('aria-pressed', String(on)));
  };

  apply(readStore(KEY, false));
  buttons.forEach((btn) => {
    pageOn(btn, 'click', () => {
      const on = !document.documentElement.classList.contains('sg-readmode');
      apply(on);
      writeStore(KEY, on);
      toast(on ? '已进入阅读模式' : '已退出阅读模式');
    });
  });
}

/**
 * 分段控件（关于页「自我介绍 / 研究动态」、知识档案「中枢链路 / 矩阵网格」）。
 *
 * DOM 契约：
 *   <div class="sg-seg" data-sg-seg="<组名>">
 *     <button class="sg-seg-btn" data-sg-seg-btn="<值>">…</button>
 *   面板：<div data-sg-seg-panel="<组名>:<值>">
 *
 * 指示器宽度与位移都靠 CSS 变量驱动，只改 transform/width，不触发重排。
 * 找不到按钮就整体空跑，其他页面零开销。
 */
function initSegments(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-seg]').forEach((seg) => {
    const name = seg.dataset.sgSeg ?? '';
    const buttons = Array.from(seg.querySelectorAll<HTMLElement>('[data-sg-seg-btn]'));
    if (!buttons.length) return;

    const scope: ParentNode = seg.closest('main') ?? document;

    const syncIndicator = (): void => {
      const active = buttons.find((btn) => btn.classList.contains('is-active')) ?? buttons[0];
      if (!active) return;
      // offsetLeft 相对 .sg-seg（它是 position:relative），已含内边距，直接可用
      seg.style.setProperty('--sg-seg-x', `${active.offsetLeft}px`);
      seg.style.setProperty('--sg-seg-w', `${active.offsetWidth}px`);
      seg.setAttribute('data-sg-seg-ready', '');
    };

    const activate = (value: string, writeUrl: boolean): void => {
      buttons.forEach((btn) => {
        const on = btn.dataset.sgSegBtn === value;
        btn.classList.toggle('is-active', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      scope.querySelectorAll<HTMLElement>('[data-sg-seg-panel]').forEach((panel) => {
        const target = (panel.dataset.sgSegPanel ?? '').split(':');
        if (name !== '' && target[0] !== name) return;
        panel.hidden = target[target.length - 1] !== value;
      });
      syncIndicator();
      if (writeUrl) {
        try {
          const url = new URL(window.location.href);
          url.searchParams.set(name || 'tab', value);
          window.history.replaceState(window.history.state, '', url.toString());
        } catch {
          /* file:// 或受限环境下静默跳过 */
        }
      }
    };

    buttons.forEach((btn) => {
      pageOn(btn, 'click', () => activate(btn.dataset.sgSegBtn ?? '', true));
    });

    // 支持 ?tab=activity 直达，和源站一致
    let initial = '';
    try {
      initial = new URL(window.location.href).searchParams.get(name || 'tab') ?? '';
    } catch {
      initial = '';
    }
    if (!initial || !buttons.some((btn) => btn.dataset.sgSegBtn === initial)) {
      initial = buttons.find((btn) => btn.classList.contains('is-active'))?.dataset.sgSegBtn ?? '';
    }
    if (initial) activate(initial, false);

    // 字体加载完宽度会变，指示器要跟着重算，否则停在半截
    if (document.fonts?.ready) {
      const signal = pageSignal();
      void document.fonts.ready.then(() => { if (!signal.aborted) syncIndicator(); });
    }
    pageOn(window, 'resize', syncIndicator, { passive: true });
  });
}


/**
 * 开屏收尾。
 *
 * 显隐判定已经由 header.php 的同步内联脚本做完（首访加 sg-splash-pending，
 * 其余情况直接加 sg-ready），这里只负责在合适时机把首访的遮罩揭开。
 *
 * 关键：等 DOMContentLoaded，不等 window.load —— load 要等 OSS 壁纸、字体分片
 * 等全部子资源，一张慢图就能让用户对着遮罩干等好几秒。
 */
function initSplash(): void {
  const root = document.documentElement;

  // 不是首访 / 已揭开 / 关掉了开屏：什么都不用做
  if (!root.classList.contains('sg-splash-pending') || root.classList.contains('sg-ready')) {
    return;
  }

  let done = false;
  const started = performance.now();

  const reveal = (): void => {
    if (done) return;
    done = true;
    const wait = Math.max(0, SPLASH_MIN - (performance.now() - started));
    pageTimeout(() => {
      root.classList.add('sg-ready');
      root.classList.remove('sg-splash-pending');
      try {
        sessionStorage.setItem('sg-splash-seen', '1');
      } catch {
        /* 隐私模式写不进去就算了，下次再开屏一次 */
      }
    }, wait);
  };

  if (document.readyState === 'loading') {
    pageOn(document, 'DOMContentLoaded', reveal, { once: true });
  } else {
    reveal();
  }

  // 兜底：DOM 卡住也在 2.5s 内揭开，绝不让遮罩成为墙
  pageTimeout(() => {
    root.classList.add('sg-ready');
    root.classList.remove('sg-splash-pending');
  }, 2500);

  // 前进/后退命中 bfcache 时直接恢复可见
  pageOn(window, 'pageshow', (event) => {
    if (event.persisted) {
      root.classList.add('sg-ready');
      root.classList.remove('sg-splash-pending');
    }
  });
}

/**
 * 热力图默认滚到最右侧（最近的日子）。
 *
 * 一年 53 列 × 14px 在窄屏放不下，源站也是直接 scrollLeft = scrollWidth。
 *
 * 坑：关于页把热力图放在「研究动态」面板里，首次加载时该面板是 hidden，
 * display:none 的子树没有布局，scrollWidth 恒为 0 —— 单靠 rAF 会滚不动，
 * 面板一显示就停在一年前那一端。所以这里用 ResizeObserver 监听容器，
 * 等它真正拿到宽度（面板被切出来）再滚一次；再不济还有标签按钮点击兜底。
 */
function initHeat(): void {
  document.querySelectorAll<HTMLElement>('[data-sg-heat]').forEach((el) => {
    let done = false;

    const toEnd = (): void => {
      if (done || el.clientWidth === 0) return;
      if (el.scrollWidth <= el.clientWidth) return;
      el.scrollLeft = el.scrollWidth;
      done = true;
    };

    pageFrame(toEnd);

    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver(() => {
        toEnd();
        if (done) ro.disconnect();
      });
      ro.observe(el); onPageDispose(() => ro.disconnect());
    }

    document.querySelectorAll<HTMLElement>('[data-sg-seg-btn]').forEach((btn) => {
      pageOn(btn, 'click', () => pageTimeout(toEnd, 80));
    });

    // 兜底：不支持 ResizeObserver 的老浏览器，滚动容器一旦能滚就补一次
    pageOn(el, 'scroll', () => { done = true; }, { passive: true, once: true });
  });
}


export function initCore(): void {
  initTheme();
  initSearchControls();
  initNav();
  initNavMore();
  initReadMode();
  initBackground();
  initSplash();
  initSegments();
  initHeat();
  initImageFallback();
}
