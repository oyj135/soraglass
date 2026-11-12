/**
 * 全局核心：运行时配置、提示条、昼夜切换、导航抽屉、背景栈、开屏。
 * 这里不做 SPA 化，Typecho 保持整页导航，只做观感补偿。
 */

const DEFAULTS: SGConfig = {
  themeUrl: '',
  bgImages: [],
  themeColors: ['#a18cd1', '#fbc2eb', '#a1c4fd', '#c2e9fb'],
  danmaku: [],
  effects: true,
  clickEffect: true,
  season: 'none',
  playlist: [],
  nav: [],
  placeholder: '',
};

const THEME_KEY = 'sg-theme';
const SPLASH_MIN = 600;

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

let toastTimer = 0;

export function toast(message: string): void {
  const el = document.querySelector<HTMLElement>('[data-sg-toast]');
  if (!el) return;
  el.textContent = message;
  el.classList.add('is-visible');
  window.clearTimeout(toastTimer);
  toastTimer = window.setTimeout(() => el.classList.remove('is-visible'), 2200);
}

const isDark = (): boolean => document.documentElement.classList.contains('dark');

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

function initTheme(): void {
  syncThemeCopy();
  document.querySelectorAll<HTMLElement>('[data-sg-theme-toggle]').forEach((btn) => {
    btn.addEventListener('click', toggleTheme);
  });
}

function initNav(): void {
  const nav = document.querySelector<HTMLElement>('[data-sg-nav]');
  const sheet = document.querySelector<HTMLElement>('[data-sg-sheet]');

  const setSheet = (open: boolean): void => {
    if (!sheet) return;
    sheet.classList.toggle('is-open', open);
    sheet.setAttribute('aria-hidden', open ? 'false' : 'true');
    document.documentElement.classList.toggle('sg-lock', open);
  };

  document.querySelectorAll<HTMLElement>('[data-sg-sheet-open]').forEach((btn) => {
    btn.addEventListener('click', () => setSheet(!sheet?.classList.contains('is-open')));
  });

  sheet?.addEventListener('click', (event) => {
    if (event.target === sheet) {
      setSheet(false);
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setSheet(false);
    }
  });

  if (!nav) return;
  let last = window.scrollY;
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(() => {
      const y = window.scrollY;
      nav.classList.toggle('is-hidden', y > last && y > 120);
      last = y;
      ticking = false;
    });
  }, { passive: true });
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
          window.history.replaceState(null, '', url.toString());
        } catch {
          /* file:// 或受限环境下静默跳过 */
        }
      }
    };

    buttons.forEach((btn) => {
      btn.addEventListener('click', () => activate(btn.dataset.sgSegBtn ?? '', true));
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
      void document.fonts.ready.then(syncIndicator);
    }
    window.addEventListener('resize', syncIndicator, { passive: true });
  });
}

/**
 * 指针聚光描边：只写两个 CSS 变量，绘制全交给 CSS 的 radial-gradient。
 * 触屏设备（hover: none）与 reduced-motion 直接不挂监听。
 */
function initSpotlight(): void {
  if (reducedMotion() || window.matchMedia('(hover: none)').matches) return;

  document.querySelectorAll<HTMLElement>('[data-sg-spotlight]').forEach((el) => {
    el.addEventListener('pointermove', (event) => {
      const rect = el.getBoundingClientRect();
      el.style.setProperty('--sg-mx', `${event.clientX - rect.left}px`);
      el.style.setProperty('--sg-my', `${event.clientY - rect.top}px`);
    }, { passive: true });
  });
}

/** 壁纸：多帧交叉淡入（对应源站 2s 交叉淡出），首帧由 header.php 直接输出避免闪白 */
function initBackground(): void {
  const cfg = config();
  const host = document.querySelector<HTMLElement>('[data-sg-bg]');

  /* 帧全部由 header.php 服务端渲染（这样才能对每个 URL 跑 sg_bg_url()，
     相对路径也能正确解析）；这里只负责按间隔切 is-active。
     间隔来自后台设置，0 = 不轮播（单张壁纸时服务端也会直接给 0）。 */
  if (host) {
    const frames = Array.from(host.querySelectorAll<HTMLElement>('.sg-bg-frame'));
    const interval = Number(host.dataset.sgBgInterval ?? '0') || 0;

    if (frames.length > 1 && interval > 0) {
      let index = 0;
      window.setInterval(() => {
        if (document.hidden) return;
        frames[index]?.classList.remove('is-active');
        index = (index + 1) % frames.length;
        frames[index]?.classList.add('is-active');
      }, interval * 1000);
    }
  }

  const gradient = document.querySelector<HTMLElement>('[data-sg-gradient]');
  if (gradient && cfg.themeColors.length >= 2) {
    gradient.style.backgroundImage = `linear-gradient(-45deg, ${cfg.themeColors.join(', ')})`;
  }
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
    window.setTimeout(() => {
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
    document.addEventListener('DOMContentLoaded', reveal, { once: true });
  } else {
    reveal();
  }

  // 兜底：DOM 卡住也在 2.5s 内揭开，绝不让遮罩成为墙
  window.setTimeout(() => {
    root.classList.add('sg-ready');
    root.classList.remove('sg-splash-pending');
  }, 2500);

  // 前进/后退命中 bfcache 时直接恢复可见
  window.addEventListener('pageshow', (event) => {
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

    window.requestAnimationFrame(toEnd);

    if (typeof ResizeObserver !== 'undefined') {
      const ro = new ResizeObserver(() => {
        toEnd();
        if (done) ro.disconnect();
      });
      ro.observe(el);
    }

    document.querySelectorAll<HTMLElement>('[data-sg-seg-btn]').forEach((btn) => {
      btn.addEventListener('click', () => window.setTimeout(toEnd, 80));
    });

    // 兜底：不支持 ResizeObserver 的老浏览器，滚动容器一旦能滚就补一次
    el.addEventListener('scroll', () => { done = true; }, { passive: true, once: true });
  });
}

/**
 * 图片兜底：外链图挂了 / 悬住不回就换成内置占位图。
 *
 * 2026-11-12 第九轮（用户报「内容都出来了，标签页还在转圈」）：
 * 根因不是主题的加载状态没重置 —— 浏览器要等文档里**所有** <img> 的请求结束
 * 才派发 load，标签页的转圈动画就是 load 的指示灯。外链图床（picsum）「连上了
 * 但不回数据」时请求会一直挂着，load 永远不来，转圈也就永远不停。
 * 上一版看门狗只处理「视口内」的图，而首页那几张图全挂在首屏下方
 * （实测 top 1083~1505px），被 `rect.top > viewport` 直接跳过 —— 于是内容看得见、
 * 转圈停不下来。实测佐证：把这几张图的 src 换成本地占位图后 readyState 立刻
 * 由 interactive 变 complete、load 补发。
 *
 * 现在的三级止损：
 *   ① 判定「请求已发出」（armed）：非 lazy 图解析即发；lazy 图进入 3000px 预取距离
 *      才发（Chrome 的懒加载预取上限是 1250px，留了余量）。只有已发出的请求才拖得住
 *      load，所以先只救这一批，不误伤还没轮到的懒加载图。
 *   ② 6s 看门狗：仍未完成就换占位图。换 src 会让浏览器中止旧请求，load 立刻走完。
 *   ③ 12s 死线：文档还是 interactive 就全量换（含还没开始下载的），保证转圈有尽头。
 * 换掉不等于放弃：原地址记在 data-sg-origin，图滚回视口时用游离 Image 探一次，
 * 成功再换回来 —— 那时 load 已结束，游离请求不影响任何计时。
 *
 * 例外：带 data-sg-fallback 的图片（播放器封面）有自己的专用兜底图，交给 player.ts。
 */
const RESCUE_MS = 6000;
const DEADLINE_MS = 12000;
const ARM_DISTANCE = 3000;

function initImageFallback(): void {
  const { placeholder } = config();
  if (!placeholder) return;

  const sameOrigin = window.location.origin;
  const remote = (url: string): boolean =>
    /^https?:\/\//i.test(url) && !url.startsWith(`${sameOrigin}/`);

  /** 被换掉的图滚回视口时再试一次（只试一次，失败就认占位图） */
  const observer = typeof IntersectionObserver === 'undefined'
    ? null
    : new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          const img = entry.target as HTMLImageElement;
          if (!entry.isIntersecting) return;
          observer?.unobserve(img);
          const origin = img.dataset.sgOrigin ?? '';
          if (origin === '' || img.dataset.sgRetry === '1') return;
          // 刚换掉的那几秒还在超时窗口里，别立刻又打一枪；图本来就在视口内时
          // IO 不会再回调，所以这里用定时器补齐，不能只靠「再次进入视口」。
          const wait = Math.max(0, 4000 - (Date.now() - Number(img.dataset.sgRescueAt ?? '0')));
          window.setTimeout(() => {
            if (img.dataset.sgRetry === '1') return;
            img.dataset.sgRetry = '1';
            // 游离 Image 先探路：成功再写回，失败就安静地留着占位图（不会破图）
            const probe = new Image();
            probe.decoding = 'async';
            probe.onload = () => { img.src = origin; };
            probe.src = origin;
          }, wait);
        });
      }, { rootMargin: '400px 0px' });

  /**
   * 换占位图 + 记住原地址。
   * retryable=false 用于「已经明确 error」的图：地址本身坏了，不用再试。
   */
  const rescue = (img: HTMLImageElement, retryable = true): void => {
    const src = img.getAttribute('src') ?? '';
    if (src === '' || src === placeholder) return;
    if (img.dataset.sgRescue === '1') return;
    img.dataset.sgRescue = '1';
    img.dataset.sgOrigin = src;
    img.dataset.sgRescueAt = String(Date.now());
    if (!retryable) img.dataset.sgRetry = '1';
    img.src = placeholder;
    if (retryable) observer?.observe(img);
  };

  /** 懒加载图是否已经发出请求：非 lazy 一律算已发；lazy 看是否进入预取距离 */
  const armed = (img: HTMLImageElement): boolean => {
    if (img.loading !== 'lazy') return true;
    return img.getBoundingClientRect().top < window.innerHeight + ARM_DISTANCE;
  };

  const sweep = (force: boolean): void => {
    document.querySelectorAll<HTMLImageElement>('img').forEach((img) => {
      if (img.hasAttribute('data-sg-fallback')) return;
      if (img.dataset.sgRescue === '1') return;
      if (img.complete && img.naturalWidth > 0) return;
      if (!remote(img.getAttribute('src') ?? '')) return;
      if (!force && !armed(img)) return;
      rescue(img);
    });
  };

  // ① 首屏已经失败的图（error 在脚本执行前就派发完了）
  document.querySelectorAll<HTMLImageElement>('img').forEach((img) => {
    if (img.hasAttribute('data-sg-fallback')) return;
    if (img.complete && img.naturalWidth === 0) rescue(img, false);
  });

  // ② 之后才失败的图（含灯箱、歌词封面等动态插入的节点）
  document.addEventListener('error', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLImageElement)) return;
    if (target.hasAttribute('data-sg-fallback')) return;
    rescue(target, false);
  }, true);

  // ③ 两级止损：常规 6s，卡死 12s
  window.setTimeout(() => sweep(false), RESCUE_MS);
  window.setTimeout(() => {
    if (document.readyState !== 'complete') sweep(true);
  }, DEADLINE_MS);
}

export function initCore(): void {
  initTheme();
  initNav();
  initBackground();
  initSplash();
  initSegments();
  initSpotlight();
  initHeat();
  initImageFallback();
}
