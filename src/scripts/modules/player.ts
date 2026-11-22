/**
 * 音乐：首页音乐卡、音乐页、右下角浮动播放器、全局 <audio> 共用一套状态。
 *
 * 三处 UI 的 DOM 契约统一成「根节点 + data-sg-* 挂点」，同一个函数驱动：
 *   根      [data-sg-player]（首页卡） / [data-sg-music]（音乐页）
 *   文本    [data-sg-track-title] [data-sg-track-artist] [data-sg-track-cover]
 *   歌词    [data-sg-lyric]（单行打字机） / [data-sg-lyrics]（整页滚动歌词）
 *   进度    [data-sg-progress] [data-sg-time-current] [data-sg-time-total]
 *   控制    [data-sg-play] [data-sg-prev] [data-sg-next] [data-sg-mode] [data-sg-volume] [data-sg-mute]
 *   歌单    [data-sg-track="<index>"]
 *
 * 站内导航保留音频节点，重新绑定页面控件；完整刷新仍用 localStorage 续播。
 */
import { config,readStore,writeStore } from './runtime';
import { toast } from './feedback';
import { navigate } from './navigation';
import { pageOn,onPageDispose } from './lifecycle';
import { createPlayerVisuals } from './player-visuals';
let mountPlayer: (() => void) | null = null;

const STATE_KEY = 'sg-player';

type Mode = 'loop' | 'single' | 'random';

interface PlayerState {
  index: number;
  time: number;
  playing: boolean;
  volume: number;
  mode: Mode;
}

function fmtTime(seconds: number): string {
  if (!Number.isFinite(seconds) || seconds < 0) return '00:00';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/** LRC → [{time, text}]；没有时间轴时整段按行铺开（time = -1）。 */
function parseLrc(raw: string): Array<{ time: number; text: string }> {
  if (!raw || typeof raw !== 'string') return [];
  const out: Array<{ time: number; text: string }> = [];
  const exp = /\[(\d{2,}):(\d{2})(?:[.:](\d{2,3}))?\]/g;

  raw.split(/\r?\n/).forEach((line) => {
    const text = line.replace(/\[\d{2,}:\d{2}(?:[.:]\d{2,3})?\]/g, '').trim();
    if (!text) return;
    let match: RegExpExecArray | null;
    let hit = false;
    exp.lastIndex = 0;
    while ((match = exp.exec(line)) !== null) {
      hit = true;
      const min = Number(match[1]);
      const sec = Number(match[2]);
      const ms = match[3] ? Number(`0.${match[3]}`) : 0;
      out.push({ time: min * 60 + sec + ms, text });
    }
    if (!hit) out.push({ time: -1, text });
  });

  return out.some((item) => item.time >= 0) ? out.sort((a, b) => a.time - b.time) : out;
}

/** 右侧标签独立于音频初始化：空歌单也必须能切到歌单面板。 */
function setupMusicSide(): void {
  const side = document.querySelector<HTMLElement>('[data-sg-music-side]');
  if (!side) return;

  const tabs = Array.from(side.querySelectorAll<HTMLButtonElement>('[data-sg-music-tab]'));
  const panels = Array.from(side.querySelectorAll<HTMLElement>('[data-sg-music-panel]'));
  const tabGroup = side.querySelector<HTMLElement>('.sg-mtab-group');
  const syncTabs = (): void => {
    const active = tabs.find((tab) => tab.classList.contains('is-active'));
    if (!tabGroup || !active || tabGroup.offsetWidth === 0) return;
    tabGroup.style.setProperty('--sg-seg-x', `${active.offsetLeft}px`);
    tabGroup.style.setProperty('--sg-seg-w', `${active.offsetWidth}px`);
    tabGroup.setAttribute('data-sg-seg-ready', '');
  };
  const selectTab = (tab: HTMLButtonElement): void => {
    const name = tab.dataset.sgMusicTab ?? '';
    tabs.forEach((item) => {
      const on = item === tab;
      item.classList.toggle('is-active', on);
      item.setAttribute('aria-selected', String(on));
      item.tabIndex = on ? 0 : -1;
    });
    panels.forEach((panel) => { panel.hidden = panel.dataset.sgMusicPanel !== name; });
    syncTabs();
  };
  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => selectTab(tab));
    tab.addEventListener('keydown', (event) => {
      if (event.altKey || event.ctrlKey || event.metaKey) return;
      let next = i;
      switch (event.key) {
        case 'ArrowLeft': next = (i - 1 + tabs.length) % tabs.length; break;
        case 'ArrowRight': next = (i + 1) % tabs.length; break;
        case 'Home': next = 0; break;
        case 'End': next = tabs.length - 1; break;
        default: return;
      }
      event.preventDefault();
      selectTab(tabs[next]);
      tabs[next].focus({ preventScroll: true });
    });
  });
  const active = tabs.find((tab) => tab.classList.contains('is-active')) ?? tabs[0];
  if (active) selectTab(active);
  pageOn(window, 'resize', syncTabs, { passive: true });
}

export function initPlayer(): void {
  setupMusicSide();
  if (mountPlayer) { mountPlayer(); return; }
  const audio = document.querySelector<HTMLAudioElement>('[data-sg-audio]');
  if (!audio) return;

  let roots = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-player], [data-sg-music]'));
  let home = document.querySelector<HTMLElement>('[data-sg-player]');
  let music = document.querySelector<HTMLElement>('[data-sg-music]');
  const tracks = config().playlist.filter((track) => typeof track.url === 'string' && track.url !== '');

  const q = <T extends HTMLElement>(root: HTMLElement | null, sel: string): T | null =>
    root ? root.querySelector<T>(sel) : null;

  /* 取色不改变显示封面的 CORS 属性，也不阻塞显示；成功缓存、进行中的请求去重。
     每张显示图片单独计版本，旧请求即使晚到，也只能缓存，不能回写新封面的卡片。 */
  const tintCache = new Map<string, string>();
  const tintRequests = new Map<string, Promise<string | null>>();
  const tintVersions = new WeakMap<HTMLImageElement, number>();
  const recordCover = (img: HTMLImageElement): void => {
    img.closest<HTMLElement>('[data-sg-album-art]')?.style.setProperty('--sg-record-cover', `url(${JSON.stringify(img.src)})`);
  };
  const clearCoverTint = (img: HTMLImageElement): void => {
    tintVersions.set(img, (tintVersions.get(img) ?? 0) + 1);
    img.closest<HTMLElement>('.sg-player-card, .sg-music-panel')?.style.removeProperty('--sg-cover-tint');
  };
  const sampleTint = async (src: string): Promise<string | null> => {
    const controller = new AbortController();
    const probe = new Image();
    let url = '';
    let timeout = 0;
    // 截止时间覆盖 fetch、body 和 decode，不能只中止已结束的网络请求。
    const deadline = new Promise<never>((_, reject) => {
      timeout = window.setTimeout(() => {
        controller.abort();
        reject(new Error('封面取色超时'));
      }, 8000);
    });
    const sample = async (): Promise<string | null> => {
      const res = await fetch(src, { mode: 'cors', signal: controller.signal });
      if (!res.ok || controller.signal.aborted) return null;
      const blob = await res.blob();
      if (controller.signal.aborted) return null;
      url = URL.createObjectURL(blob);
      probe.src = url;
      await probe.decode();
      if (controller.signal.aborted) return null;
      const cvs = document.createElement('canvas');
      cvs.width = cvs.height = 12;
      const ctx = cvs.getContext('2d');
      if (!ctx) return null;
      ctx.drawImage(probe, 0, 0, 12, 12);
      const data = ctx.getImageData(0, 0, 12, 12).data;
      let r = 0, g = 0, b = 0;
      for (let i = 0; i < data.length; i += 4) { r += data[i]; g += data[i + 1]; b += data[i + 2]; }
      const n = data.length / 4;
      // 保留原取色算法：饱和拉伸 1.6 倍、60–230 截断，不重新设计配色。
      const mid = (Math.max(r, g, b) / n + Math.min(r, g, b) / n) / 2;
      const push = (v: number): number => Math.max(60, Math.min(230, mid + (v - mid) * 1.6));
      return `rgb(${Math.round(push(r / n))} ${Math.round(push(g / n))} ${Math.round(push(b / n))})`;
    };
    try {
      return await Promise.race([sample(), deadline]);
    } catch {
      return null; // CORS / 网络 / 解码失败均交给卡片原有青瓷兜底。
    } finally {
      window.clearTimeout(timeout);
      probe.removeAttribute('src');
      if (url) URL.revokeObjectURL(url); // 失败、超时同样释放 Object URL。
    }
  };
  const requestTint = (src: string): Promise<string | null> => {
    const pending = tintRequests.get(src);
    if (pending) return pending;
    const request = sampleTint(src)
      .then((tint) => {
        if (tint) tintCache.set(src, tint);
        return tint;
      })
      .finally(() => { tintRequests.delete(src); });
    tintRequests.set(src, request);
    return request;
  };
  const tintCard = (img: HTMLImageElement): void => {
    const card = img.closest<HTMLElement>('.sg-player-card, .sg-music-panel');
    if (!card) return;
    clearCoverTint(img);
    const version = tintVersions.get(img);
    // 当前模板没有 srcset；currentSrc 在刚换图时可能仍是上一张，必须读新 src。
    const src = img.src;
    if (!src || src.startsWith('data:')) return;
    const hit = tintCache.get(src);
    if (hit) { card.style.setProperty('--sg-cover-tint', hit); return; }
    void requestTint(src).then((tint) => {
      if (tintVersions.get(img) !== version || img.src !== src || !img.isConnected) return;
      if (tint) card.style.setProperty('--sg-cover-tint', tint);
      else card.style.removeProperty('--sg-cover-tint');
    });
  };

  /**
   * 封面兜底。
   *
   * 网易云 / 外部图床这类外链封面会 403 或超时，而 `<img alt="">` 加载失败时浏览器什么都不画 ——
   * 唱片就只剩一圈描边，看着像「图片没渲染」。统一在 error 时退到本地默认封面。
   * dataset 打标是为了防止默认图自己也 404 时无限循环。
   */
  const watchCover = (img: HTMLImageElement | null): void => {
    if (!img) return;
    const fallback = img.dataset.sgFallback ?? '';
    if (!fallback) return;
    const revert = (): void => {
      clearCoverTint(img);
      if (img.dataset.sgFallbackDone === '1') return;
      img.dataset.sgFallbackDone = '1';
      img.src = fallback;
      recordCover(img);
    };
    /* 换歌时 apply() 会给 img 加 .is-swap（opacity: 0），图片 load 后再淡入。
       放在这里而不是 apply 里，是因为 src 可能走缓存、也可能 404 后回落本地图，
       两条路都是 load 收尾。 */
    img.addEventListener('load', () => { img.classList.remove('is-swap'); recordCover(img); tintCard(img); });
    img.addEventListener('error', () => { img.classList.remove('is-swap'); revert(); });
    // 已经加载完且宽度为 0，说明在绑定监听之前就失败了
    if (img.complete && img.naturalWidth === 0) revert();
    // 已经加载完成的（走缓存），立即取一次色 —— load 事件不会再发
    if (img.complete && img.naturalWidth > 0) tintCard(img);
    recordCover(img);
  };

  document
    .querySelectorAll<HTMLImageElement>('[data-sg-track-cover], [data-sg-cover]')
    .forEach(watchCover);

  /* 首页有两处歌词：音乐卡里的小字 [data-sg-lyric]，和 7:5 行下方的歌词栏
     [data-sg-lyric-text]（源站 CloudPlayer + LyricBar 也是两份）。一份文本喂两处，
     两边的打字进度才不会一前一后。 */
  let lyricTargets = Array.from(
    document.querySelectorAll<HTMLElement>('[data-sg-lyric], [data-sg-lyric-text]'),
  );
  let lyricBar = document.querySelector<HTMLElement>('[data-sg-lyric-bar]');
  const typeTimers: number[] = [];
  /* 歌词地址（Meting type=lrc 返回的是纯文本 URL）按次缓存；
     applySeq 用来判断「异步拿回歌词时是不是已经切歌了」。 */
  const lrcCache = new Map<string, string>();
  let applySeq = 0;

  if (!tracks.length) {
    roots.forEach((root) => {
      const title = q(root, '[data-sg-track-title]');
      if (title) title.textContent = '歌单为空';
    });
    lyricTargets.forEach((el) => { el.textContent = '歌单暂未准备好'; });
    roots.forEach((root) => { const status = q(root, '[data-sg-playback-status]'); if (status) status.textContent = '暂无曲目'; });
    roots.forEach((root) => {
      root.classList.add('is-empty');
      root.querySelectorAll<HTMLButtonElement>('button').forEach((btn) => {
        btn.disabled = true;
      });
    });
    return;
  }

  const saved = readStore<PlayerState>(STATE_KEY, { index: 0, time: 0, playing: false, volume: 0.8, mode: 'loop' });
  let index = Number.isInteger(saved.index) && saved.index >= 0 && saved.index < tracks.length ? saved.index : 0;
  let mode: Mode = saved.mode === 'single' || saved.mode === 'random' ? saved.mode : 'loop';
  const volume = typeof saved.volume === 'number' && saved.volume >= 0 && saved.volume <= 1 ? saved.volume : 0.8;

  audio.volume = volume;
  audio.preload = 'metadata';
  const visuals = createPlayerVisuals(audio);
  onPageDispose(visuals.unmount);

  /* 歌词面板在右侧那块卡片里，是 [data-sg-music] 的**兄弟节点**，不在它的子树里。
     以前这里写成 q(music, '[data-sg-lyrics]')，永远拿到 null，右侧歌词区就一直停在
     「正在等待旋律…」——功能其实一次都没跑起来过。全文档查一次即可（只有一份）。 */
  let lyricBox = document.querySelector<HTMLElement>('[data-sg-lyrics]');
  let lyricEmpty = document.querySelector<HTMLElement>('[data-sg-lyrics-empty]');
  let lines: Array<{ time: number; text: string; el: HTMLElement | null }> = [];
  let activeLine = -1;
  /* 没有歌词面板时（首页只有一个歌词栏），buildLyrics 不再建 DOM，
     但「当前这一句」仍要留着给 paintLyric 用。 */
  let currentLyricText = '';

  let trackEls = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-track]'));
  let songEls = Array.from(document.querySelectorAll<HTMLElement>('.sg-song[data-sg-track]'));
  const persist = (): void => {
    writeStore(STATE_KEY, {
      index,
      time: audio.currentTime,
      playing: !audio.paused,
      volume: audio.volume,
      mode,
    } satisfies PlayerState);
  };

  const syncTime = (): void => {
    const pct = Number.isFinite(audio.duration) && audio.duration > 0
      ? Math.max(0, Math.min(1, audio.currentTime / audio.duration))
      : 0;
    roots.forEach((root) => {
      const cur = q(root, '[data-sg-time-current]');
      const total = q(root, '[data-sg-time-total]');
      const bar = q<HTMLInputElement>(root, '[data-sg-progress]');
      if (cur) cur.textContent = fmtTime(audio.currentTime);
      if (total) total.textContent = fmtTime(audio.duration);
      if (bar) {
        bar.setAttribute('aria-valuetext', `${fmtTime(audio.currentTime)}，共 ${fmtTime(audio.duration)}`);
        /* 轨道本身就是进度条：已播放段靠 --sg-p 切开（见 components.css 的
           input.sg-progress）。只写一个自定义属性，浏览器自己算渐变位置。 */
        bar.style.setProperty('--sg-p', `${(pct * 100).toFixed(2)}%`);
        if (audio.duration > 0 && document.activeElement !== bar) {
          bar.value = String(Math.round(pct * 1000));
        }
      }
    });
  };

  const paintLyric = (text: string): void => {
    typeTimers.forEach((timer) => window.clearInterval(timer));
    typeTimers.length = 0;
    lyricTargets.forEach((el) => { el.textContent = ''; });
    if (!text) return;
    /* 单行打字机：源站 CloudPlayer / LyricBar 的逐字效果，纯 JS，不用 CSS 动画。
       切歌时必须先清掉上一轮的定时器，否则两段文字会交替往同一个节点里写。

       每字间隔 45ms 对短句刚好，但 LRC 里常有 30–40 字的整句（含翻译括注），
       45ms 就是 1.4–1.8s，而一句歌词往往只唱 2–3s —— 文字永远追不上歌，
       看着像「卡住不动」。改成按长度反推：整句必须在 0.5s 内打完，
       短句仍保留 45ms/字的观感，下限 10ms 防止超长句变成闪光。 */
    const step = Math.min(45, Math.max(10, 500 / Math.max(1, text.length)));
    let i = 0;
    const timer = window.setInterval(() => {
      i += 1;
      const slice = text.slice(0, i);
      lyricTargets.forEach((el) => { el.textContent = slice; });
      if (i >= text.length) window.clearInterval(timer);
    }, step);
    typeTimers.push(timer);
  };

  const buildLyrics = (raw: string, fallbackText = ''): void => {
    const parsed = parseLrc(raw);
    /* 纯音乐没有 LRC：把 track.lyric 当成一句话铺进去，
       否则右侧歌词区永远停在「正在等待旋律…」，看着像加载失败。 */
    if (!parsed.length && fallbackText) {
      parsed.push({ time: -1, text: fallbackText });
    }
    currentLyricText = parsed.length ? parsed[0].text : '';
    activeLine = -1;

    /* 行数据先无条件建好，DOM 之后再说。
       2026-11-14 修：这里原来紧跟着一句 `if (!lyricBox) return;`，
       本意是「首页没有滚动歌词面板就不建列表」，但它把下面的
       lines.push 一起跳过了 —— 首页的 lines 永远是空数组，
       syncLyricLine 开头 `if (!lines.length) return` 直接返回，
       于是歌词栏只显示 applyLyrics 里 paint 的第一句，之后再也不变。
       音乐页有面板所以看着正常，问题只出在首页那条歌词栏。 */
    lines = parsed.map((line) => ({ time: line.time, text: line.text, el: null }));
    if (!lyricBox) return;   // 首页只有一行歌词栏，不需要建列表

    lyricBox.querySelectorAll('.sg-lyric-line').forEach((el) => el.remove());
    if (lyricEmpty) lyricEmpty.hidden = parsed.length > 0;

    /* 歌词行套一层 .sg-lyrics-inner：CSS 用 margin-block:auto 让「只有几行」时
       整块竖直居中（对齐演示站那种歌词停在面板中间的观感）；
       行数多到溢出时 auto 归零，照旧从头开始滚动。 */
    let inner = lyricBox.querySelector<HTMLElement>('.sg-lyrics-inner');
    if (!inner) {
      inner = document.createElement('div');
      inner.className = 'sg-lyrics-inner';
      lyricBox.appendChild(inner);
    }
    inner.textContent = '';

    parsed.forEach((line, i) => {
      const el = document.createElement('button');
      el.type = 'button';
      el.className = 'sg-lyric-line';
      el.textContent = line.text;
      el.addEventListener('click', () => {
        if (line.time >= 0 && Number.isFinite(audio.duration) && audio.duration > 0) {
          audio.currentTime = line.time;
          syncTime();
        }
        void i;
      });
      inner.appendChild(el);
      lines[i].el = el;
    });
  };

  /* Meting 的 lrc 有两种：歌词正文，或一个纯文本地址（`type=lrc`）。
     是地址就按需 fetch 一次并缓存；拿不到就按「没有歌词」处理，不打断播放。 */
  const applyLyrics = (raw: string, fallbackText: string): void => {
    const seq = (applySeq += 1);
    const paint = (text: string): void => {
      buildLyrics(text, fallbackText);
      paintLyric(lines.length ? lines[0].text : (currentLyricText || '——'));
    };

    if (!/^https?:\/\//i.test(raw)) {
      paint(raw);
      return;
    }

    const cached = lrcCache.get(raw);
    if (cached !== undefined) {
      paint(cached);
      return;
    }

    paint('');   // 先把兜底那句铺上，面板不至于空着
    void fetch(raw, { mode: 'cors' })
      .then((res) => (res.ok ? res.text() : ''))
      .then((text) => {
        lrcCache.set(raw, text);
        if (seq !== applySeq) return;   // 已经把歌切走了，别拿旧歌词盖住新的
        paint(text.replace(/\r/g, ''));
      })
      .catch(() => { /* 歌词拿不到不影响播放 */ });
  };

  const syncLyricLine = (): void => {
    if (!lines.length) return;
    let next = 0;
    let hit = false;
    for (let i = 0; i < lines.length; i += 1) {
      if (lines[i].time >= 0 && lines[i].time <= audio.currentTime + 0.25) {
        next = i;
        hit = true;
      }
    }
    /* 整篇没有一个时间轴（纯文本歌词 / 纯音乐）：这不是「进度还没到」，
       而是根本无从对齐。钉在第一句上不动是正确行为，别去刷打字机。 */
    if (!hit) {
      if (activeLine !== 0) {
        activeLine = 0;
        paintLyric(lines[0].text);
      }
      return;
    }
    if (next === activeLine) return;
    lines.forEach((line, i) => line.el?.classList.toggle('is-active', i === next));
    activeLine = next;
    // 首页两处歌词跟着滚动歌词一起换行（源站 currentLyric 由 MusicProvider 逐行推送）
    paintLyric(lines[next].text);
    const el = lines[next]?.el;
    if (el && lyricBox) {
      const top = el.offsetTop - lyricBox.offsetHeight / 2 + el.offsetHeight / 2;
      lyricBox.scrollTo({ top, behavior: 'smooth' });
    }
  };

  const markTrack = (): void => {
    trackEls.forEach((btn) => {
      const on = Number(btn.dataset.sgTrack ?? -1) === index;
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-current', on ? 'true' : 'false');
      let wave = btn.querySelector<HTMLElement>('.sg-song-wave');
      if (on && !wave && btn.classList.contains('sg-song')) {
        wave = document.createElement('span');
        wave.className = 'sg-song-wave';
        wave.innerHTML = '<i></i><i></i><i></i>';
        btn.appendChild(wave);
      } else if (!on && wave) {
        wave.remove();
      }
    });
  };

  const apply = (next: number, preserveAudio = false): void => {
    index = (next + tracks.length) % tracks.length;
    const track = tracks[index];
    if (!track?.url) return;

    if (!preserveAudio) { audio.src = track.url; audio.load(); visuals.track(); }

    const cover = track.cover || '';
    roots.forEach((root) => {
      const title = q(root, '[data-sg-track-title]');
      const artist = q(root, '[data-sg-track-artist]');
      const img = q<HTMLImageElement>(root, '[data-sg-track-cover]');
      if (title) title.textContent = track.title ?? '未命名';
      if (artist) artist.textContent = track.artist ?? '空璃音乐盒';
      /* 封面交叉淡入：先置透明，load 后再淡回来（watchCover 收尾）。
         src 没变时不动 —— 否则同一首重复 apply 会一直停在透明态。 */
      const nextCover = cover || img?.dataset.sgFallback || '';
      if (img && nextCover && img.getAttribute('src') !== nextCover) {
        clearCoverTint(img);                // 换图即清旧色、作废旧回调，不等新图 load。
        delete img.dataset.sgFallbackDone;   // 换歌要允许重新触发兜底
        img.classList.add('is-swap');
        img.src = nextCover;
        recordCover(img);
      }
      const bar = q<HTMLInputElement>(root, '[data-sg-progress]');
      if (bar) bar.value = '0';
      const cur = q(root, '[data-sg-time-current]');
      const total = q(root, '[data-sg-time-total]');
      if (cur) cur.textContent = '00:00';
      if (total) total.textContent = '00:00';
    });

    // 有 LRC 用逐行歌词，没有就退化成一句静态说明（不要空着）
    // 歌词可能是 URL（Meting 的 type=lrc），拿回来之前先显示歌名，别让歌词栏空着
    applyLyrics(String(track.lrc ?? ''), String(track.lyric ?? '') || String(track.title ?? ''));
    markTrack();
  };

  const setPlaying = (on: boolean): void => {
    music?.classList.toggle('is-playing', on);
    home?.classList.toggle('is-playing', on);
    // 歌词栏的声波 / 音符动画只认这一个开关（源站 isPlaying 驱动 safeWave 与 animate-bounce）
    lyricBar?.classList.toggle('is-playing', on);
    roots.forEach((root) => {
      const playButton = q(root, '[data-sg-play]');
      playButton?.setAttribute('aria-pressed', on ? 'true' : 'false');
      playButton?.setAttribute('aria-label', on ? '暂停' : '播放');
      const status = q(root, '[data-sg-playback-status]');
      if (status) status.textContent = on ? '正在播放' : '已暂停';
    });
    visuals.sync();
    if (on) syncLyricLine();
  };

  /* 「被浏览器拦截」这件事以前一律报成一句 toast，真实原因分三种，处理方式完全不同：
       1. NotAllowedError  → 真的是自动播放策略，等一次点击就好（不要反复报错）；
       2. NotSupportedError / 网络错误 → 音频本身打不开（外链 404、CORS、格式不对），
          这种必须跳到下一首，否则用户会以为「点了没反应」；
       3. AbortError       → 切歌时上一次 play() 被主动取消，属于正常现象，不能报错。 */
  let errorStreak = 0;
  let armed = false;

  const armResume = (): void => {
    if (armed) return;
    armed = true;
    roots.forEach((root) => { root.classList.add('is-blocked'); });
    const resume = (): void => {
      document.removeEventListener('pointerdown', resume, true);
      armed = false;
      roots.forEach((root) => { root.classList.remove('is-blocked'); });
      play();
    };
    // capture 阶段挂载：卡片整块可点跳转，冒泡阶段可能到不了 document
    document.addEventListener('pointerdown', resume, true);
  };

  const play = (quiet = false): void => {
    visuals.resume();
    const attempt = audio.play();
    if (!attempt || typeof attempt.then !== 'function') return;
    void attempt.catch((error: unknown) => {
      const name = error instanceof Error ? error.name : '';
      if (name === 'AbortError') return;
      if (name === 'NotAllowedError') {
        armResume();
        if (!quiet) toast('浏览器要求先点一下页面才能出声，点任意位置继续');
        return;
      }
      errorStreak += 1;
      if (errorStreak <= tracks.length) {
        if (!quiet) toast('这首的音频打不开，已跳到下一首');
        apply(index + 1);
        play(true);
        return;
      }
      errorStreak = 0;
      toast('歌单里的音频暂时无法播放，请检查 Meting 接口返回的音源');
    });
  };

  const toggle = (): void => {
    if (!audio.getAttribute('src')) {
      toast('这首歌还没有音频地址，请检查歌单配置');
      return;
    }
    if (audio.paused) play();
    else audio.pause();
  };

  const step = (dir: number): void => {
    if (mode === 'random' && tracks.length > 2) {
      let next = index;
      while (next === index) next = Math.floor(Math.random() * tracks.length);
      apply(next);
    } else {
      apply(index + dir);
    }
    play();
  };

  /* 模式图标 + 文案一起换：原来只换了 dataset 和 aria-label，视觉上永远是同一个小圆圈，
     用户根本看不出「循环 / 单曲 / 随机」的差别。 */
  const MODE_META: Record<Mode, { icon: string; label: string }> = {
    loop: {
      label: '列表循环',
      icon: '<path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
    },
    single: {
      label: '单曲循环',
      icon: '<path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/><path d="M11 10h1v5"/>',
    },
    random: {
      label: '随机播放',
      icon: '<path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="m15 15 6 6"/><path d="M4 4l5 5"/>',
    },
  };

  /* 模式按钮 2026-11-14 起首页卡也有（华为那一行左侧的胶囊）。
     所以这里不再只认 [data-sg-music]，而是遍历所有播放器根节点 ——
     首页与音乐页的模式图标/文案必须同步，否则同一个状态两处显示不一致。 */
  const paintMode = (): void => {
    const meta = MODE_META[mode] ?? MODE_META.loop;
    roots.forEach((root) => {
      const btn = q(root, '[data-sg-mode]');
      if (!btn) return;
      btn.dataset.mode = mode;
      btn.setAttribute('aria-label', `播放模式：${meta.label}`);
      btn.classList.toggle('is-active', mode !== 'loop');
      const icon = q<HTMLElement>(root, '[data-sg-mode-icon]');
      if (icon) {
        icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${meta.icon}</svg>`;
      }
      const label = q<HTMLElement>(root, '[data-sg-mode-label]');
      if (label) label.textContent = meta.label;
    });
  };

  apply(index);
  markTrack();
  setPlaying(false);
  paintMode();

  // 进度恢复：metadata 未就绪时 duration 是 NaN，必须等 loadedmetadata
  if (saved.time > 0) {
    audio.addEventListener('loadedmetadata', () => {
      audio.currentTime = Math.min(saved.time, Number.isFinite(audio.duration) ? audio.duration : saved.time);
      syncTime();
      persist();
    }, { once: true });
  }

  // 上一页在播的，回来自动续播；被浏览器拦下就等第一次点击（安静处理，不弹 toast）
  if (saved.playing) {
    play(true);
  }

  audio.addEventListener('play', () => { errorStreak = 0; armed = false; roots.forEach(root => root.classList.remove('is-blocked','is-error')); setPlaying(true); persist(); });
  audio.addEventListener('pause', () => { setPlaying(false); persist(); });
  audio.addEventListener('timeupdate', () => { syncTime(); syncLyricLine(); });
  audio.addEventListener('loadedmetadata', syncTime);
  audio.addEventListener('durationchange', syncTime);
  audio.addEventListener('playing', () => {
    roots.forEach(root => { const status = q(root,'[data-sg-playback-status]'); if (status) status.textContent = '正在播放'; });
    visuals.sync();
  });
  audio.addEventListener('waiting', () => {
    if (audio.paused) return;
    roots.forEach(root => { const status = q(root,'[data-sg-playback-status]'); if (status) status.textContent = '音频缓冲中'; });
  });
  audio.addEventListener('ended', () => {
    if (mode === 'single') {
      audio.currentTime = 0;
      play();
      return;
    }
    step(1);
  });
  // 具体错误文案统一由 play() 的 catch 给出，这里只做标记，避免两条 toast 叠在一起
  audio.addEventListener('error', () => {
    roots.forEach((root) => {
      root.classList.toggle('is-error', Boolean(audio.error));
      const status = q(root,'[data-sg-playback-status]'); if (status) status.textContent = '音频暂不可用';
    });
  });

  const bindControls = (): void => {
  roots.forEach((root) => {
    q(root, '[data-sg-play]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      toggle();
    });
    q(root, '[data-sg-prev]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      step(-1);
    });
    q(root, '[data-sg-next]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      step(1);
    });
  });

  // 播放模式：首页卡与音乐页的胶囊都绑（同一份 state，paintMode 负责两边一起刷）
  roots.forEach((root) => {
    q(root, '[data-sg-mode]')?.addEventListener('click', (event) => {
      event.stopPropagation();   // 首页整卡可点跳转，按钮必须拦住
      mode = mode === 'loop' ? 'single' : mode === 'single' ? 'random' : 'loop';
      paintMode();
      persist();
      toast(mode === 'single' ? '单曲循环' : mode === 'random' ? '随机播放' : '列表循环');
    });
  });

  /* 静音 UI 只读同一个 audio.muted；音量输入解除静音时也必须同步图标/高亮/ARIA。 */
  const paintMute = (): void => {
    roots.forEach((root) => {
      const btn = q(root, '[data-sg-mute]');
      if (!btn) return;
      btn.classList.toggle('is-active', audio.muted);
      btn.setAttribute('aria-label', audio.muted ? '取消静音' : '静音');
      // volume / volume-x 沿用原图标，尺寸仍由既有 CSS 控制。
      btn.innerHTML = audio.muted
        ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m23 9-6 6"/><path d="m17 9 6 6"/></svg>'
        : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.54 8.46a5 5 0 0 1 0 7.07"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14"/></svg>';
    });
  };

  const volumeBar = q<HTMLInputElement>(music, '[data-sg-volume]');
  if (volumeBar) {
    volumeBar.value = String(Math.round(audio.volume * 100));
    /* 音量条复用 .sg-progress 皮肤，轨道填充也读 --sg-p。
       它跟播放进度不是一回事，所以自己写一份。 */
    const paintVolume = (): void => {
      volumeBar.style.setProperty('--sg-p', `${Math.max(0, Math.min(1, audio.volume)) * 100}%`);
      const value = q(music, '[data-sg-volume-value]');
      if (value) value.textContent = `${Math.round(audio.volume * 100)}%`;
    };
    paintVolume();
    volumeBar.addEventListener('input', () => {
      audio.volume = Math.max(0, Math.min(1, Number(volumeBar.value) / 100));
      audio.muted = false;
      paintVolume();
      paintMute();
      persist();
    });
  }

  roots.forEach((root) => {
    q(root, '[data-sg-mute]')?.addEventListener('click', (event) => {
      event.stopPropagation();
      audio.muted = !audio.muted;
      paintMute();
    });
  });
  paintMute();

  // 整张卡可点跳音乐页（按钮/进度条/链接上不跳）
  roots.forEach((root) => {
    const href = root.dataset.href ?? '';
    if (!href) return;
    root.addEventListener('click', (event) => {
      const target = event.target as HTMLElement | null;
      if (target?.closest('button, input, a, label')) return;
      void navigate(href);
    });
  });

  // 歌单点歌
  songEls.forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.stopPropagation();
      apply(Number(btn.dataset.sgTrack ?? 0));
      play();
    });
  });

  // 拖动进度条：按下时先暂停 timeupdate 回写，避免手指和滑块打架
  roots.forEach((root) => {
    const bar = q<HTMLInputElement>(root, '[data-sg-progress]');
    if (!bar) return;
    let seeking = false;
    const seek = (): void => {
      if (!Number.isFinite(audio.duration) || audio.duration <= 0) return;
      audio.currentTime = (Number(bar.value) / 1000) * audio.duration;
      syncTime();
    };
    bar.addEventListener('pointerdown', (event) => { event.stopPropagation(); seeking = true; });
    bar.addEventListener('input', () => { if (seeking) seek(); });
    bar.addEventListener('change', () => { seeking = false; seek(); persist(); });
    bar.addEventListener('click', (event) => event.stopPropagation());
  });
  };
  bindControls();
  mountPlayer = (): void => {
    roots = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-player], [data-sg-music]'));
    home = document.querySelector<HTMLElement>('[data-sg-player]'); music = document.querySelector<HTMLElement>('[data-sg-music]');
    lyricTargets = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-lyric], [data-sg-lyric-text]'));
    lyricBar = document.querySelector<HTMLElement>('[data-sg-lyric-bar]'); lyricBox = document.querySelector<HTMLElement>('[data-sg-lyrics]'); lyricEmpty = document.querySelector<HTMLElement>('[data-sg-lyrics-empty]');
    trackEls = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-track]')); songEls = Array.from(document.querySelectorAll<HTMLElement>('.sg-song[data-sg-track]'));
    document.querySelectorAll<HTMLImageElement>('[data-sg-track-cover], [data-sg-cover]').forEach(watchCover);
    bindControls(); apply(index, true); setPlaying(!audio.paused); paintMode(); syncTime();
    visuals.mount(); onPageDispose(visuals.unmount);
  };

  window.setInterval(() => { if (!audio.paused) persist(); }, 5000);
  window.addEventListener('pagehide', persist);
}
