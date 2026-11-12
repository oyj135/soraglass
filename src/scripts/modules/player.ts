/**
 * 音乐：首页音乐卡、音乐页、右下角浮动播放器、全局 <audio> 共用一套状态。
 *
 * 三处 UI 的 DOM 契约统一成「根节点 + data-sg-* 挂点」，同一个函数驱动：
 *   根      [data-sg-player]（首页卡） / [data-sg-music]（音乐页）
 *   文本    [data-sg-track-title] [data-sg-track-artist] [data-sg-track-cover]
 *   歌词    [data-sg-lyric]（单行打字机） / [data-sg-lyrics]（整页滚动歌词）
 *   进度    [data-sg-progress] [data-sg-time-current] [data-sg-time-total]
 *   控制    [data-sg-play] [data-sg-prev] [data-sg-next] [data-sg-mode] [data-sg-volume] [data-sg-mute]
 *   歌单    [data-sg-track="<index>"] [data-sg-playlist-search]
 *
 * Typecho 是整页刷新，状态写 localStorage，切页后接着放（浏览器拦截自动播放时
 * 等第一次 pointerdown 再续播）。
 */
import { config, readStore, writeStore, toast } from './core';

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

export function initPlayer(): void {
  const audio = document.querySelector<HTMLAudioElement>('[data-sg-audio]');
  if (!audio) return;

  const roots = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-player], [data-sg-music]'));
  const home = document.querySelector<HTMLElement>('[data-sg-player]');
  const music = document.querySelector<HTMLElement>('[data-sg-music]');
  const tracks = config().playlist.filter((track) => typeof track.url === 'string' && track.url !== '');

  const q = <T extends HTMLElement>(root: HTMLElement | null, sel: string): T | null =>
    root ? root.querySelector<T>(sel) : null;

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
      if (img.dataset.sgFallbackDone === '1') return;
      img.dataset.sgFallbackDone = '1';
      img.src = fallback;
    };
    img.addEventListener('error', revert);
    // 已经加载完且宽度为 0，说明在绑定监听之前就失败了
    if (img.complete && img.naturalWidth === 0) revert();
  };

  document
    .querySelectorAll<HTMLImageElement>('[data-sg-track-cover], [data-sg-cover]')
    .forEach(watchCover);

  /* 首页有两处歌词：音乐卡里的小字 [data-sg-lyric]，和 7:5 行下方的歌词栏
     [data-sg-lyric-text]（源站 CloudPlayer + LyricBar 也是两份）。一份文本喂两处，
     两边的打字进度才不会一前一后。 */
  const lyricTargets = Array.from(
    document.querySelectorAll<HTMLElement>('[data-sg-lyric], [data-sg-lyric-text]'),
  );
  const lyricBar = document.querySelector<HTMLElement>('[data-sg-lyric-bar]');
  const typeTimers: number[] = [];

  if (!tracks.length) {
    roots.forEach((root) => {
      const title = q(root, '[data-sg-track-title]');
      if (title) title.textContent = '歌单为空';
    });
    lyricTargets.forEach((el) => { el.textContent = '到后台「歌单 JSON」或「网易云歌曲 ID」里加几首'; });
    roots.forEach((root) => {
      root.querySelectorAll<HTMLButtonElement>('button').forEach((btn) => {
        btn.addEventListener('click', () => toast('还没有配置音频，先到后台填歌单'));
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

  // 音乐页歌词容器：整首歌词滚动 + 当前行高亮
  const lyricBox = q(music, '[data-sg-lyrics]');
  const lyricEmpty = q(music, '[data-sg-lyrics-empty]');
  let lines: Array<{ time: number; text: string; el: HTMLElement }> = [];
  let activeLine = -1;

  const trackEls = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-track]'));
  const songEls = Array.from(document.querySelectorAll<HTMLElement>('.sg-song[data-sg-track]'));

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
    roots.forEach((root) => {
      const cur = q(root, '[data-sg-time-current]');
      const total = q(root, '[data-sg-time-total]');
      const bar = q<HTMLInputElement>(root, '[data-sg-progress]');
      if (cur) cur.textContent = fmtTime(audio.currentTime);
      if (total) total.textContent = fmtTime(audio.duration);
      if (bar && audio.duration > 0 && document.activeElement !== bar) {
        bar.value = String(Math.round((audio.currentTime / audio.duration) * 1000));
      }
    });
  };

  const paintLyric = (text: string): void => {
    typeTimers.forEach((timer) => window.clearInterval(timer));
    typeTimers.length = 0;
    lyricTargets.forEach((el) => { el.textContent = ''; });
    if (!text) return;
    // 单行打字机：源站 CloudPlayer / LyricBar 的逐字效果，纯 JS，不用 CSS 动画。
    // 切歌时必须先清掉上一轮的定时器，否则两段文字会交替往同一个节点里写。
    let i = 0;
    const timer = window.setInterval(() => {
      i += 1;
      const slice = text.slice(0, i);
      lyricTargets.forEach((el) => { el.textContent = slice; });
      if (i >= text.length) window.clearInterval(timer);
    }, 45);
    typeTimers.push(timer);
  };

  const buildLyrics = (raw: string): void => {
    if (!lyricBox) return;
    const parsed = parseLrc(raw);
    lines = [];
    activeLine = -1;
    lyricBox.querySelectorAll('.sg-lyric-line').forEach((el) => el.remove());
    if (lyricEmpty) lyricEmpty.hidden = parsed.length > 0;

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
      lyricBox?.appendChild(el);
      lines.push({ time: line.time, text: line.text, el });
    });
  };

  const syncLyricLine = (): void => {
    if (!lines.length) return;
    let next = 0;
    for (let i = 0; i < lines.length; i += 1) {
      if (lines[i].time >= 0 && lines[i].time <= audio.currentTime + 0.25) next = i;
    }
    if (next === activeLine) return;
    lines.forEach((line, i) => line.el.classList.toggle('is-active', i === next));
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

  const apply = (next: number): void => {
    index = (next + tracks.length) % tracks.length;
    const track = tracks[index];
    if (!track?.url) return;

    audio.src = track.url;
    audio.load();

    const cover = track.cover || '';
    roots.forEach((root) => {
      const title = q(root, '[data-sg-track-title]');
      const artist = q(root, '[data-sg-track-artist]');
      const img = q<HTMLImageElement>(root, '[data-sg-track-cover]');
      if (title) title.textContent = track.title ?? '未命名';
      if (artist) artist.textContent = track.artist ?? '空璃音乐盒';
      if (img && cover) {
        delete img.dataset.sgFallbackDone;   // 换歌要允许重新触发兜底
        img.src = cover;
      }
      const bar = q<HTMLInputElement>(root, '[data-sg-progress]');
      if (bar) bar.value = '0';
      const cur = q(root, '[data-sg-time-current]');
      const total = q(root, '[data-sg-time-total]');
      if (cur) cur.textContent = '00:00';
      if (total) total.textContent = '00:00';
    });

    // 有 LRC 用逐行歌词，没有就退化成一句静态说明（不要空着）
    buildLyrics(String(track.lrc ?? ''));
    paintLyric(lines.length ? lines[0].text : (track.lyric ? String(track.lyric) : '——'));
    markTrack();
  };

  const setPlaying = (on: boolean): void => {
    music?.classList.toggle('is-playing', on);
    home?.classList.toggle('is-playing', on);
    // 歌词栏的声波 / 音符动画只认这一个开关（源站 isPlaying 驱动 safeWave 与 animate-bounce）
    lyricBar?.classList.toggle('is-playing', on);
    roots.forEach((root) => q(root, '[data-sg-play]')?.setAttribute('aria-pressed', on ? 'true' : 'false'));
    if (on) syncLyricLine();
  };

  const play = (): void => {
    void audio.play().catch(() => toast('浏览器拦截了自动播放，请再点一次播放'));
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

  const paintMode = (): void => {
    const btn = q(music, '[data-sg-mode]');
    if (!btn) return;
    const meta = MODE_META[mode] ?? MODE_META.loop;
    btn.dataset.mode = mode;
    btn.setAttribute('aria-label', `播放模式：${meta.label}`);
    btn.classList.toggle('is-active', mode !== 'loop');
    const icon = q<HTMLElement>(music, '[data-sg-mode-icon]');
    if (icon) {
      icon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${meta.icon}</svg>`;
    }
    const label = q<HTMLElement>(music, '[data-sg-mode-label]');
    if (label) label.textContent = meta.label;
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

  // 上一页在播的，回来自动续播；被浏览器拦下就等第一次点击
  if (saved.playing) {
    const resume = (): void => {
      document.removeEventListener('pointerdown', resume);
      play();
    };
    void audio.play().catch(() => document.addEventListener('pointerdown', resume, { once: true }));
  }

  audio.addEventListener('play', () => { setPlaying(true); persist(); });
  audio.addEventListener('pause', () => { setPlaying(false); persist(); });
  audio.addEventListener('timeupdate', () => { syncTime(); syncLyricLine(); });
  audio.addEventListener('loadedmetadata', syncTime);
  audio.addEventListener('durationchange', syncTime);
  audio.addEventListener('ended', () => {
    if (mode === 'single') {
      audio.currentTime = 0;
      play();
      return;
    }
    step(1);
  });
  audio.addEventListener('error', () => {
    if (audio.getAttribute('src')) toast('音频加载失败：外链可能被 403 拦截，换成直链音频再试');
  });

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

  // 音乐页：播放模式 / 音量 / 静音
  q(music, '[data-sg-mode]')?.addEventListener('click', () => {
    mode = mode === 'loop' ? 'single' : mode === 'single' ? 'random' : 'loop';
    paintMode();
    persist();
    toast(mode === 'single' ? '单曲循环' : mode === 'random' ? '随机播放' : '列表循环');
  });

  const volumeBar = q<HTMLInputElement>(music, '[data-sg-volume]');
  if (volumeBar) {
    volumeBar.value = String(Math.round(volume * 100));
    volumeBar.addEventListener('input', () => {
      audio.volume = Math.max(0, Math.min(1, Number(volumeBar.value) / 100));
      audio.muted = false;
      persist();
    });
  }

  q(music, '[data-sg-mute]')?.addEventListener('click', () => {
    audio.muted = !audio.muted;
    const btn = q(music, '[data-sg-mute]');
    btn?.classList.toggle('is-active', audio.muted);
    btn?.setAttribute('aria-label', audio.muted ? '取消静音' : '静音');
  });

  // 整张卡可点跳音乐页（按钮/进度条/链接上不跳）
  roots.forEach((root) => {
    const href = root.dataset.href ?? '';
    if (!href) return;
    root.addEventListener('click', (event) => {
      const target = event.target as HTMLElement | null;
      if (target?.closest('button, input, a, label')) return;
      window.location.href = href;
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

  // 歌单搜索
  const search = q<HTMLInputElement>(music, '[data-sg-playlist-search]');
  if (search) {
    search.addEventListener('input', () => {
      const query = search.value.trim().toLowerCase();
      songEls.forEach((btn) => {
        const text = (btn.textContent ?? '').toLowerCase();
        btn.hidden = query !== '' && !text.includes(query);
      });
    });
  }

  // 歌词 / 歌单切换（选中态由 CSS 的滑动指示器表现，这里负责把刻度写进自定义属性）
  const tabs = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-music-tab]'));
  const panels = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-music-panel]'));
  const tabGroup = document.querySelector<HTMLElement>('.sg-mtab-group');

  const syncTabs = (): void => {
    if (!tabGroup || !tabs.length) return;
    const active = tabs.find((tab) => tab.classList.contains('is-active')) ?? tabs[0];
    if (!active || tabGroup.offsetWidth === 0) return;
    tabGroup.style.setProperty('--sg-seg-x', `${active.offsetLeft}px`);
    tabGroup.style.setProperty('--sg-seg-w', `${active.offsetWidth}px`);
    tabGroup.setAttribute('data-sg-seg-ready', '');
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const name = tab.dataset.sgMusicTab ?? '';
      tabs.forEach((item) => {
        const on = item === tab;
        item.classList.toggle('is-active', on);
        item.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      panels.forEach((panel) => {
        panel.hidden = panel.dataset.sgMusicPanel !== name;
      });
      syncTabs();
    });
  });

  syncTabs();
  window.addEventListener('resize', syncTabs, { passive: true });

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

  window.setInterval(() => { if (!audio.paused) persist(); }, 5000);
}
