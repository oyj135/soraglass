/**
 * 每日选刊：期号切换 + 展开目录 + 收藏过滤 + 条目阅读弹窗。
 *
 * DOM 契约（全部由 partials/briefing.php 服务端渲染，这里只切类名/文本）：
 *   根        [data-sg-briefing]
 *   横幅      [data-sg-briefing-toggle]（点击展开/收起）
 *   横幅文案  [data-sg-briefing-date|label|kicker|title|desc|meta|unread|more-text]
 *   封面      [data-sg-briefing-cover="<i>"]
 *   翻页      [data-sg-briefing-prev|next|index]
 *   目录      [data-sg-briefing-issue="<i>"] + data-date/title/desc/notice/counts
 *   条目      [data-sg-briefing-item] + data-title/summary/source/source-date/url/note/channel
 *   弹窗      [data-sg-briefing-dialog] 内 [data-sg-reader-*]
 *
 * 「已读 / 收藏」存 localStorage：Typecho 是纯服务端渲染，没有登录态时
 * 不能指望把阅读状态写进数据库，浏览器本地存是最省事又不打扰用户的方案。
 */

const READ_KEY = 'sg-briefing-read';
const SAVED_KEY = 'sg-briefing-saved';
const AUTO_MS = 8000;

const loadSet = (key: string): Set<string> => {
  try {
    const raw = window.localStorage.getItem(key);
    const data = raw ? JSON.parse(raw) : [];
    return new Set(Array.isArray(data) ? data.filter((item) => typeof item === 'string') : []);
  } catch {
    return new Set();
  }
};

const parseList = (raw?: string): string[] => {
  try {
    const value = JSON.parse(raw ?? '[]');
    return Array.isArray(value) ? value.map((item) => String(item)).filter(Boolean) : [];
  } catch {
    return [];
  }
};

const saveSet = (key: string, value: Set<string>): void => {
  try {
    window.localStorage.setItem(key, JSON.stringify([...value]));
  } catch {
    /* 隐私模式忽略 */
  }
};

export function initBriefing(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-briefing]');
  if (!root) return;

  const body = root.querySelector<HTMLElement>('[data-sg-briefing-body]');
  const toggle = root.querySelector<HTMLButtonElement>('[data-sg-briefing-toggle]');
  const issues = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-briefing-issue]'));
  const covers = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-briefing-cover]'));
  const status = root.querySelector<HTMLElement>('[data-sg-briefing-status]');

  const text = (name: string): HTMLElement | null => root.querySelector<HTMLElement>(`[data-sg-briefing-${name}]`);
  const elDate = text('date');
  const elLabel = text('label');
  const elTheme = text('theme');
  const elKicker = text('kicker');
  const elTitle = text('title');
  const elDesc = text('desc');
  const elMeta = text('meta');
  const elUnread = text('unread');
  const elMore = text('more-text');
  const elIndex = text('index');
  const elSubline = text('subline');
  const elHeadline = text('headline');
  const elNotice = text('notice');
  const sign = root.querySelector<HTMLElement>('.sg-briefing-sign');

  const read = loadSet(READ_KEY);
  const saved = loadSet(SAVED_KEY);
  let current = 0;
  let savedOnly = false;

  const announce = (message: string): void => {
    if (status) status.textContent = message;
  };

  const pad = (value: number): string => String(value).padStart(2, '0');

  /* ---------------- 展开 / 收起 ---------------- */
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let closeTimer = 0;

  const setOpen = (open: boolean): void => {
    root.classList.toggle('is-open', open);
    if (body) {
      window.clearTimeout(closeTimer);
      if (open) {
        body.hidden = false;
        /* 先让浏览器认下「0fr」这一帧，再加 is-open，过渡才会真的跑起来 */
        void body.offsetHeight;
      } else if (reduced) {
        body.hidden = true;
      } else {
        /* 收起时等过渡走完再 hidden，否则内容会瞬间消失 */
        closeTimer = window.setTimeout(() => {
          if (!root.classList.contains('is-open')) body.hidden = true;
        }, 430);
      }
    }
    toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (elMore) elMore.textContent = open ? '收起这封来信' : '展开今日选刊';
    if (sign) sign.innerHTML = open
      ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M5 12h14"/></svg>'
      : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>';
  };

  setOpen(false);
  toggle?.addEventListener('click', () => setOpen(!root.classList.contains('is-open')));
  root.querySelector('[data-sg-briefing-collapse]')?.addEventListener('click', () => {
    setOpen(false);
    toggle?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  /* ---------------- 期号切换 ---------------- */
  const markUnread = (issue: HTMLElement): number => {
    const items = Array.from(issue.querySelectorAll<HTMLElement>('[data-sg-briefing-item]'));
    return items.filter((item) => !read.has(item.dataset.id ?? '')).length;
  };

  const paintIssue = (index: number): void => {
    current = (index + issues.length) % issues.length;
    const issue = issues[current];
    if (!issue) return;
    const data = issue.dataset;

    issues.forEach((item, i) => { item.hidden = i !== current; });
    /* 后台指定了「每日选刊背景图」时服务端只渲染 1 张封面（数量对不上期数），
       这时不能按下标切换，否则翻到第 2 期封面就被撤掉了。 */
    if (covers.length === issues.length) {
      covers.forEach((cover, i) => cover.classList.toggle('is-active', i === current));
    }

    if (elDate) elDate.textContent = data.date ?? '';
    if (elLabel) elLabel.textContent = `/ ${data.label ?? ''}`;
    if (elTheme) {
      const theme = data.theme ?? '';
      elTheme.textContent = theme;
      elTheme.hidden = theme === '';
    }
    if (elKicker) elKicker.textContent = data.kicker ?? '';
    if (elTitle) elTitle.textContent = data.title ?? '';
    if (elDesc) elDesc.textContent = data.desc ?? '';
    if (elNotice) elNotice.textContent = data.notice ?? '';
    if (elIndex) elIndex.textContent = `${current + 1}/${issues.length}`;

    let channels: Array<{ name: string; count: number }> = [];
    try {
      const parsed = JSON.parse(data.counts ?? '[]');
      if (Array.isArray(parsed)) channels = parsed;
    } catch {
      channels = [];
    }

    if (elMeta) {
      elMeta.innerHTML = '';
      channels.forEach((channel) => {
        const node = document.createElement('span');
        node.innerHTML = `${channel.name}<b>${pad(channel.count)}</b>`;
        elMeta.appendChild(node);
      });
      if (elUnread) elMeta.appendChild(elUnread);
    }

    if (elUnread) {
      elUnread.innerHTML = `<i></i>${markUnread(issue)} 条未读`;
    }

    const total = issue.querySelectorAll('[data-sg-briefing-item]').length;
    if (elSubline) {
      const weekday = data.weekday ? ` · ${data.weekday}` : '';
      elSubline.textContent = `${data.date ?? ''}${weekday} · ${total} 则选刊`;
    }
    if (elHeadline) elHeadline.textContent = current === 0 ? '本期来信' : '往期来信';

    applySavedFilter();
  };

  const select = root.querySelector<HTMLSelectElement>('[data-sg-briefing-select]');
  if (select) {
    select.addEventListener('change', () => {
      paintIssue(Number(select.value));
      announce(`已切换到第 ${Number(select.value) + 1} 期`);
    });
    select.value = '0';
  }

  /* issues 由服务端按「新 → 旧」渲染，下标越大越早。
     › 往更早翻（下标 +1）、‹ 往更新翻（下标 -1）。
     第三轮把三个方向整体写反了（› 反而回到最近一期），这里统一纠正。 */
  root.querySelector('[data-sg-briefing-prev]')?.addEventListener('click', () => {
    paintIssue(current - 1);
    if (select) select.value = String(current);
    startAuto();
  });
  root.querySelector('[data-sg-briefing-next]')?.addEventListener('click', () => {
    paintIssue(current + 1);
    if (select) select.value = String(current);
    startAuto();
  });

  /* ---------------- 自动轮播 ----------------
     原来旁边那颗「暂停 / 播放」按钮只切换自身图标，真实的切换要等 8 秒才发生，
     用户点了看不出反应（截图里就是这颗）。按钮整颗移除，轮播改为常开：
     悬停、展开来信、切到后台、系统要求 reduced-motion 时自动暂停。 */
  let timer = 0;
  let hovered = false;

  const stopAuto = (): void => { window.clearInterval(timer); };

  const startAuto = (): void => {
    stopAuto();
    if (issues.length < 2 || hovered) return;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    timer = window.setInterval(() => {
      if (hovered || root.classList.contains('is-open') || document.hidden) return;
      paintIssue(current + 1);
    }, AUTO_MS);
  };


  root.addEventListener('pointerenter', () => { hovered = true; });
  root.addEventListener('pointerleave', () => { hovered = false; });
  document.addEventListener('visibilitychange', () => { if (!document.hidden) startAuto(); });

  /* ---------------- 往期面板 ---------------- */
  const archiveToggle = root.querySelector<HTMLButtonElement>('[data-sg-briefing-archive-toggle]');
  const archive = root.querySelector<HTMLElement>('[data-sg-briefing-archive]');
  archiveToggle?.addEventListener('click', () => {
    const open = archiveToggle.getAttribute('aria-expanded') !== 'true';
    archiveToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (archive) archive.hidden = !open;
  });

  /* ---------------- 只看收藏 ---------------- */
  const savedBtn = root.querySelector<HTMLButtonElement>('[data-sg-briefing-saved]');

  function applySavedFilter(): void {
    const items = Array.from(root!.querySelectorAll<HTMLElement>('[data-sg-briefing-item]'));
    items.forEach((item) => {
      const isSaved = saved.has(item.dataset.id ?? '');
      item.classList.toggle('is-saved', isSaved);
      item.classList.toggle('sg-filtered-out', savedOnly && !isSaved);
    });
    if (savedBtn) {
      savedBtn.classList.toggle('is-active', savedOnly);
      savedBtn.setAttribute('aria-pressed', savedOnly ? 'true' : 'false');
    }
  }

  savedBtn?.addEventListener('click', () => {
    savedOnly = !savedOnly;
    applySavedFilter();
    announce(savedOnly ? '只看已收藏' : '显示全部选刊');
  });

  /* ---------------- 阅读弹窗 ---------------- */
  const dialog = root.parentElement?.querySelector<HTMLDialogElement>('[data-sg-briefing-dialog]')
    ?? document.querySelector<HTMLDialogElement>('[data-sg-briefing-dialog]');

  const readerTitle = dialog?.querySelector<HTMLElement>('[data-sg-reader-title]');
  const readerKind = dialog?.querySelector<HTMLElement>('[data-sg-reader-kind]');
  const readerMeta = dialog?.querySelector<HTMLElement>('[data-sg-reader-meta]');
  const readerSummary = dialog?.querySelector<HTMLElement>('[data-sg-reader-summary]');
  const readerHighlights = dialog?.querySelector<HTMLElement>('[data-sg-reader-highlights]');
  const readerNote = dialog?.querySelector<HTMLElement>('[data-sg-reader-note]');
  const readerSub = dialog?.querySelector<HTMLElement>('[data-sg-reader-sub]');
  const readerTags = dialog?.querySelector<HTMLElement>('[data-sg-reader-tags]');
  const readerLink = dialog?.querySelector<HTMLAnchorElement>('[data-sg-reader-link]');
  const readerSave = dialog?.querySelector<HTMLButtonElement>('[data-sg-reader-save]');
  let activeId = '';

  const paintSave = (): void => {
    if (!readerSave) return;
    const on = saved.has(activeId);
    readerSave.setAttribute('aria-pressed', on ? 'true' : 'false');
    readerSave.classList.toggle('is-active', on);
    readerSave.innerHTML = `<svg viewBox="0 0 24 24" fill="${on ? 'currentColor' : 'none'}" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/></svg>${on ? '已收藏' : '留着慢慢读'}`;
  };

  root.querySelectorAll<HTMLElement>('[data-sg-briefing-item]').forEach((item) => {
    item.addEventListener('click', () => {
      const data = item.dataset;
      activeId = data.id ?? '';
      read.add(activeId);
      saveSet(READ_KEY, read);

      const minutes = Number(data.minutes ?? 0) || 1;
      const points = parseList(data.points);
      const tags = parseList(data.tags);

      if (readerKind) {
        readerKind.textContent = [data.channel, document.querySelector<HTMLElement>('[data-sg-briefing-date]')?.textContent ?? '', `约 ${minutes} 分钟`]
          .filter(Boolean)
          .join(' · ');
      }
      /* 中文标题当主标题，原文标题退成一行小字（源站就是这种排版） */
      if (readerTitle) readerTitle.textContent = data.titleZh || data.title || '';
      if (readerSub) {
        const sub = data.titleZh ? data.title ?? '' : '';
        readerSub.hidden = sub === '';
        readerSub.textContent = sub;
      }
      if (readerMeta) readerMeta.textContent = [data.source, data.sourceDate && `原文 ${data.sourceDate}`].filter(Boolean).join(' · ');
      if (readerSummary) readerSummary.textContent = data.summary ?? '';
      if (readerHighlights) {
        readerHighlights.innerHTML = '';
        points.forEach((point, i) => {
          const row = document.createElement('p');
          row.className = 'sg-reader-point';
          const num = document.createElement('span');
          num.textContent = String(i + 1).padStart(2, '0');
          row.append(num, document.createTextNode(point));
          readerHighlights.append(row);
        });
        readerHighlights.hidden = points.length === 0;
      }
      if (readerNote) {
        readerNote.hidden = !data.note;
        readerNote.textContent = data.note ?? '';
      }
      if (readerTags) {
        readerTags.innerHTML = '';
        tags.forEach((tag) => {
          const chip = document.createElement('span');
          chip.textContent = `#${tag}`;
          readerTags.append(chip);
        });
        readerTags.hidden = tags.length === 0;
      }
      if (readerLink) {
        const url = data.url ?? '';
        readerLink.hidden = url === '';
        readerLink.href = url;
      }

      paintSave();

      if (dialog && typeof dialog.showModal === 'function') {
        try { dialog.showModal(); } catch { dialog.setAttribute('open', ''); }
      }
      paintIssue(current);
    });
  });

  const closeReader = (): void => {
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
  };

  dialog?.querySelector('[data-sg-reader-close]')?.addEventListener('click', closeReader);
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog) closeReader();
  });
  dialog?.addEventListener('close', () => {
    document.body.removeAttribute('data-sg-lock');
  });

  readerSave?.addEventListener('click', () => {
    if (!activeId) return;
    if (saved.has(activeId)) saved.delete(activeId);
    else saved.add(activeId);
    saveSet(SAVED_KEY, saved);
    paintSave();
    applySavedFilter();
  });

  paintIssue(0);
  startAuto();
}
