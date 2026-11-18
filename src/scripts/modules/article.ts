import { pageOn,pageTimeout,pageFrame,onPageDispose } from './lifecycle';
/**
 * 文章增强：目录高亮、代码高亮、公式渲染、图片灯箱。
 * highlight.js / katex 走动态 import，只会在真的有代码块 / 公式的页面下载。
 */
import { reducedMotion } from './runtime';
import { toast } from './feedback';

function initTOC(): void {
  const article = document.querySelector<HTMLElement>('[data-sg-article]');
  const toc = document.querySelector<HTMLElement>('[data-sg-toc]');
  const holder = toc?.querySelector<HTMLElement>('[data-sg-toc-list]');
  if (!article || !toc || !holder) return;

  const heads = Array.from(article.querySelectorAll<HTMLElement>('h2, h3'));
  if (heads.length < 2) return;

  toc.hidden = false;
  if (!toc.id) toc.id = 'sg-toc-panel';

  /* 移动端目录开关：目录在窄屏默认收起，由右下角浮动按钮打开；
     桌面端按钮由 CSS 隐藏，目录保持吸顶。 */
  const fab = document.createElement('button');
  fab.type = 'button';
  fab.className = 'sg-toc-fab';
  fab.setAttribute('aria-controls', toc.id);
  fab.setAttribute('aria-expanded', 'false');
  fab.textContent = '目录';
  document.body.appendChild(fab);
  onPageDispose(() => fab.remove());

  const setOpen = (open: boolean): void => {
    toc.classList.toggle('is-mobile-open', open);
    fab.setAttribute('aria-expanded', String(open));
    fab.textContent = open ? '收起目录' : '目录';
    if (open) {
      toc.querySelector<HTMLAnchorElement>('.sg-toc-list a')?.focus({ preventScroll: true });
    } else {
      fab.focus({ preventScroll: true });
    }
  };
  pageOn(fab, 'click', () => setOpen(!toc.classList.contains('is-mobile-open')));
  pageOn(document, 'keydown', (event) => {
    if (event.key === 'Escape' && toc.classList.contains('is-mobile-open')) setOpen(false);
  });

  /* 阅读百分比：与左侧阅读轴的算法保持一致，正文不足一屏时按 0/100 处理。 */
  const percent = toc.querySelector<HTMLElement>('[data-sg-toc-percent]');
  if (percent) {
    let raf = 0;
    const paint = (): void => {
      raf = 0;
      const rect = article.getBoundingClientRect();
      const span = rect.height - window.innerHeight;
      const p = span < 160 ? (rect.top < 0 ? 1 : 0) : Math.min(1, Math.max(0, -rect.top / span));
      percent.textContent = `${Math.round(p * 100)}%`;
    };
    const schedule = (): void => {
      if (!raf) raf = pageFrame(paint);
    };
    paint();
    pageOn(window, 'scroll', schedule, { passive: true });
    pageOn(window, 'resize', schedule, { passive: true });
  }

  const list = document.createElement('ul');
  list.className = 'sg-toc-list';

  heads.forEach((head, i) => {
    if (!head.id) head.id = `sg-toc-${i}`;
    const item = document.createElement('li');
    if (head.tagName === 'H3') item.className = 'sg-toc-lv3';
    const link = document.createElement('a');
    link.href = `#${head.id}`;
    link.textContent = head.textContent?.trim() ?? '';
    link.addEventListener('click', (event) => {
      event.preventDefault();
      head.tabIndex = -1;
      head.focus({ preventScroll: true });
      head.scrollIntoView({ behavior: reducedMotion() ? 'instant' : 'smooth', block: 'start' });
      window.history.replaceState(null, '', `#${head.id}`);
      if (window.matchMedia('(max-width: 1023.98px)').matches) setOpen(false);
    });
    item.appendChild(link);
    list.appendChild(item);
  });
  holder.appendChild(list);

  const items = Array.from(list.querySelectorAll('li'));
  const activate = (id: string): void => {
    items.forEach((item) => {
      const link = item.querySelector('a');
      const on = link?.hash === `#${id}`;
      item.classList.toggle('is-active', on);
      if (on) link?.setAttribute('aria-current', 'location');
      else link?.removeAttribute('aria-current');
    });
  };
  activate(heads[0].id);
  const observer = new IntersectionObserver((entries) => {
    const visible = entries.filter((entry) => entry.isIntersecting);
    if (!visible.length) return;
    const target = visible[0].target as HTMLElement;
    activate(target.id);
  }, { rootMargin: '-88px 0px -70% 0px' });

  heads.forEach((head) => observer.observe(head));
  onPageDispose(() => observer.disconnect());
}

/**
 * P1-8：代码块工具条（2026-11-16 改 mac 风格 + 折叠）。
 *
 * 每个 pre 顶部加一条 mac 工具条：左侧红黄绿三色圆点 + 语言标签，
 * 右侧复制按钮 + 折叠按钮。与语法高亮解耦，highlight.js 加载失败时仍可用。
 * 折叠态按代码块序号存 localStorage，刷新后保持；不影响复制与高亮。
 */
function initCodeBlocks(): void {
  const foldKey = (i: number): string => `sg-codefold-${i}`;
  const readFold = (i: number): boolean => {
    try {
      return window.localStorage.getItem(foldKey(i)) === '1';
    } catch {
      return false;
    }
  };
  const writeFold = (i: number, folded: boolean): void => {
    try {
      if (folded) window.localStorage.setItem(foldKey(i), '1');
      else window.localStorage.removeItem(foldKey(i));
    } catch {
      /* 隐私模式忽略 */
    }
  };

  document.querySelectorAll<HTMLElement>('[data-sg-article] pre').forEach((pre, index) => {
    if (pre.querySelector('.sg-code-bar')) return;
    const code = pre.querySelector('code');
    if (!code) return;

    pre.classList.add('sg-codeblock');
    const match = code.className.match(/language-([\w+#-]+)/);
    const bar = document.createElement('div');
    bar.className = 'sg-code-bar';

    /* 左侧：mac 三色圆点（纯装饰） */
    const dots = document.createElement('span');
    dots.className = 'sg-code-dots';
    dots.setAttribute('aria-hidden', 'true');
    for (const color of ['red', 'yellow', 'green']) {
      const dot = document.createElement('span');
      dot.className = `sg-code-dot sg-code-dot--${color}`;
      dots.appendChild(dot);
    }

    const lang = document.createElement('span');
    lang.className = 'sg-code-lang';
    lang.textContent = match?.[1] ?? 'text';

    const actions = document.createElement('span');
    actions.className = 'sg-code-actions';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'sg-code-copy';
    btn.textContent = '复制';
    pageOn(btn, 'click', () => {
      const text = code.innerText;
      const done = (ok: boolean): void => {
        btn.textContent = ok ? '已复制' : '复制失败';
        pageTimeout(() => {
          btn.textContent = '复制';
        }, 1800);
      };
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(() => done(true)).catch(() => done(false));
        return;
      }
      /* 非安全上下文（http 等）兜底 */
      const area = document.createElement('textarea');
      area.value = text;
      area.style.position = 'fixed';
      area.style.opacity = '0';
      document.body.appendChild(area);
      area.select();
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch {
        ok = false;
      }
      area.remove();
      done(ok);
    });

    /* 折叠按钮：切换 .is-collapsed（CSS 隐藏 code），状态本地记忆 */
    const fold = document.createElement('button');
    fold.type = 'button';
    fold.className = 'sg-code-fold';
    const applyFold = (folded: boolean): void => {
      pre.classList.toggle('is-collapsed', folded);
      fold.setAttribute('aria-expanded', folded ? 'false' : 'true');
      fold.textContent = folded ? '展开' : '折叠';
    };
    applyFold(readFold(index));
    pageOn(fold, 'click', () => {
      const folded = !pre.classList.contains('is-collapsed');
      applyFold(folded);
      writeFold(index, folded);
    });

    actions.append(btn, fold);
    bar.append(dots, lang, actions);
    pre.appendChild(bar);
  });
}

/**
 * P1-7：正文图片灯箱与图注。
 *
 * 把正文里的裸 <img> 包成 figure + [data-sg-photo] 链接，复用照片墙灯箱；
 * 有 alt 的图在下方输出图注。图片独占一个 <p> 时把 figure 提到 p 外，
 * 避免 figure 嵌在 p 里的非法结构。必须在 initLightbox 之前跑。
 */
function initProseImages(): void {
  const article = document.querySelector<HTMLElement>('[data-sg-article]');
  if (!article) return;

  article.querySelectorAll('img').forEach((img) => {
    if (img.closest('[data-sg-photo], .sg-code-bar, pre')) return;

    const src = img.currentSrc || img.src;
    if (!src) return;

    /* 先记住原位置：appendChild 会把 img 从文档里移走 */
    const origin = img.parentElement;
    const next = img.nextSibling;
    const soloParagraph =
      !!origin && origin.tagName === 'P' && origin.children.length === 1 && (origin.textContent ?? '').trim() === '';

    const figure = document.createElement('figure');
    figure.className = 'sg-prose-figure';
    const link = document.createElement('a');
    link.className = 'sg-prose-zoom';
    link.setAttribute('data-sg-photo', '');
    link.href = src;
    link.appendChild(img);
    figure.appendChild(link);

    const alt = (img.alt ?? '').trim();
    if (alt) {
      const cap = document.createElement('figcaption');
      cap.className = 'sg-photo-cap';
      cap.textContent = alt;
      figure.appendChild(cap);
    }

    if (!origin) return;
    if (soloParagraph) {
      /* 图片是段落里唯一内容：整段替换为 figure，避免 figure 嵌在 p 里 */
      origin.replaceWith(figure);
    } else {
      origin.insertBefore(figure, next);
    }
  });
}

/**
 * P1-12：文章分享。
 *
 * 系统分享（navigator.share）+ 复制链接，不引第三方 SDK；
 * 浏览器不支持系统分享时隐藏该按钮，只留复制链接。
 */
function initShare(): void {
  const url = window.location.href;
  const title = document.querySelector<HTMLElement>('.sg-article-head h1')?.textContent?.trim() || document.title;

  const share = document.querySelector<HTMLButtonElement>('[data-sg-share]');
  if (share) {
    if (typeof navigator.share !== 'function') {
      share.hidden = true;
    } else {
      pageOn(share, 'click', () => {
        navigator.share({ title, url }).catch(() => {
          /* 用户取消分享不提示 */
        });
      });
    }
  }

  const copy = document.querySelector<HTMLButtonElement>('[data-sg-copy-link]');
  if (copy) {
    pageOn(copy, 'click', () => {
      if (navigator.clipboard?.writeText) {
        navigator.clipboard
          .writeText(url)
          .then(() => toast('链接已复制'))
          .catch(() => toast('复制失败，请从地址栏复制'));
      } else {
        toast('当前环境不支持复制，请从地址栏复制');
      }
    });
  }
}

async function initHighlight(): Promise<void> {
  const blocks = document.querySelectorAll<HTMLElement>('[data-sg-article] pre code');
  if (!blocks.length) return;
  try {
    const hljs = await import('highlight.js/lib/common');
    await import('highlight.js/styles/atom-one-dark.css');
    blocks.forEach((block) => {
      try {
        hljs.default.highlightElement(block);
      } catch {
        /* 语法不认识就保留原文 */
      }
    });
  } catch {
    /* 资源加载失败不影响正文 */
  }
}

async function initMath(): Promise<void> {
  const article = document.querySelector<HTMLElement>('[data-sg-article]');
  if (!article) return;

  const nodes = Array.from(article.querySelectorAll<HTMLElement>('p'))
    .filter((p) => /^\$\$[\s\S]+\$\$$/.test((p.textContent ?? '').trim()));
  if (!nodes.length) return;

  try {
    const katex = await import('katex');
    await import('katex/dist/katex.min.css');
    nodes.forEach((p) => {
      const tex = (p.textContent ?? '').trim().replace(/^\$\$/, '').replace(/\$\$$/, '').trim();
      try {
        p.innerHTML = katex.default.renderToString(tex, { displayMode: true, throwOnError: false });
        p.classList.add('sg-math');
      } catch {
        /* 公式错误保留原文，方便作者排查 */
      }
    });
  } catch {
    /* 资源加载失败不影响正文 */
  }
}

/**
 * 图片灯箱。
 *
 * 单张照片点开就只看自己；同一组（同一个 [data-sg-photo-group] /
 * 瀑布流 / 网格）里的照片自动串成一组，支持左右键与两侧按钮翻页。
 * 相册详情页因此不需要额外写一套弹层逻辑。
 */
export function initLightbox(): void {
  const links = Array.from(document.querySelectorAll<HTMLAnchorElement>('[data-sg-photo]')).filter(link => !link.dataset.sgLightboxReady);
  if (!links.length) return;

  const groupOf = (link: HTMLAnchorElement): HTMLAnchorElement[] => {
    const scope = link.closest<HTMLElement>('[data-sg-photo-group], .sg-gallery-masonry, .sg-photo-grid');
    if (!scope) return [link];
    return Array.from(scope.querySelectorAll<HTMLAnchorElement>('[data-sg-photo]'));
  };

  links.forEach((link) => {
    link.dataset.sgLightboxReady = '1';
    link.addEventListener('click', (event) => {
      event.preventDefault();
      const group = groupOf(link);
      let index = Math.max(0, group.indexOf(link));

      const box = document.createElement('div');
      box.className = 'sg-lightbox';
      box.setAttribute('role', 'dialog');
      box.setAttribute('aria-modal', 'true');

      const img = document.createElement('img');
      img.alt = '';
      /* 原图多半挂在图床/OSS 上，带防盗链的站点只放行空 Referer 或指定域名，
         这里跟模板里的 <img referrerpolicy="no-referrer"> 保持一致。 */
      img.referrerPolicy = 'no-referrer';

      const counter = document.createElement('span');
      counter.className = 'sg-lightbox-count';

      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'sg-lightbox-close';
      closeBtn.setAttribute('aria-label', '关闭');
      closeBtn.textContent = '×';

      const prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'sg-lightbox-nav is-prev';
      prev.setAttribute('aria-label', '上一张');
      prev.textContent = '‹';

      const next = document.createElement('button');
      next.type = 'button';
      next.className = 'sg-lightbox-nav is-next';
      next.setAttribute('aria-label', '下一张');
      next.textContent = '›';

      const paint = (): void => {
        const current = group[index];
        if (!current) return;
        img.src = current.getAttribute('href') ?? '';
        img.alt = current.querySelector('img')?.alt ?? '';
        const caption = current.querySelector('.sg-photo-cap')?.textContent?.trim() ?? '';
        img.alt = caption || img.alt;
        counter.textContent = group.length > 1 ? `${index + 1} / ${group.length}` : '';
        prev.hidden = group.length < 2;
        next.hidden = group.length < 2;
      };

      const step = (delta: number): void => {
        index = (index + delta + group.length) % group.length;
        paint();
      };

      const close = (): void => {
        box.remove();
        document.removeEventListener('keydown', onKey);
        document.documentElement.classList.remove('sg-lock');
      };

      const onKey = (e: KeyboardEvent): void => {
        if (e.key === 'Escape') close();
        if (e.key === 'ArrowLeft') step(-1);
        if (e.key === 'ArrowRight') step(1);
      };

      box.append(img, counter, closeBtn, prev, next);
      paint();

      // 只有点在空白处才关；点图/按钮不关
      box.addEventListener('click', (e) => {
        if (e.target === box) close();
      });
      closeBtn.addEventListener('click', close);
      img.addEventListener('click', (e) => e.stopPropagation());
      prev.addEventListener('click', (e) => {
        e.stopPropagation();
        step(-1);
      });
      next.addEventListener('click', (e) => {
        e.stopPropagation();
        step(1);
      });

      pageOn(document, 'keydown', onKey);
      document.documentElement.classList.add('sg-lock');
      document.body.appendChild(box);
      onPageDispose(close);
    });
  });
}

/** 侧栏整列吸顶：列高超出视口时改底对齐（top 为负），目录尾部始终够得着。 */
function initAsidePin(): void {
  const pin = document.querySelector<HTMLElement>('[data-sg-aside-pin]');
  if (!pin) return;
  const mq = window.matchMedia('(min-width: 1024px)');
  const fit = (): void => {
    if (!mq.matches) { pin.style.top = ''; return; }
    const h = pin.offsetHeight;
    pin.style.top = h > window.innerHeight - 104 ? `calc(100vh - ${h}px - 1rem)` : '';
  };
  fit();
  pageOn(window, 'resize', fit, { passive: true });
  const ro = new ResizeObserver(fit);
  ro.observe(pin);
  onPageDispose(() => ro.disconnect());
}

export function initArticle(): void {
  initTOC();
  initAsidePin();
  initProseImages(); // 先把正文裸图包成灯箱链接，再统一绑定灯箱
  initLightbox();
  initCodeBlocks(); // 工具条与高亮解耦，先出条再等语法着色
  void initHighlight();
  void initMath();
  initShare();
}
