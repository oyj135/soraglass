/**
 * 背景特效：季节粒子（樱花 / 飘雪 / 萤火 / 落叶）、背景弹幕、点击涟漪。
 * 全部合到单个 canvas + 单个 rAF，避免多层 DOM 叠加导致滚动掉帧。
 * 移动端与 prefers-reduced-motion 下整体关闭（CSS 也同步隐藏）。
 *
 * 2026-11-21 墨分五色：按用户要求移除全部星海 / 流星元素（全屏星空另见已删除的
 * universe.ts），季节粒子配色降级为「墨粉 / 素白 / 淡竹青 / 枯叶赭」低饱和色域；
 * 夜间专用判断与「切主题重置」逻辑随之删除，只剩 reducedMotion / 小屏 / 可见性降级。
 */
import { config,isSmallScreen,reducedMotion } from './runtime';

interface Particle {
  kind: Season;
  x: number;
  y: number;
  vx: number;
  vy: number;
  size: number;
  rot: number;
  vr: number;
  alpha: number;
  phase: number;
}

export type Season = 'sakura' | 'snow' | 'fireflies' | 'leaf';

let stopSeason: (() => void) | null = null;


const DENSITY: Record<Season, number> = {
  sakura: 24,   // 墨分五色：降低密度，樱花粉只作少量点缀
  snow: 48,
  fireflies: 18,
  leaf: 20,     // 枯叶赭：缓慢飘落，低密点缀
};

function createParticle(kind: Season, width: number, height: number, seed = false): Particle {
  const base: Particle = {
    kind,
    x: Math.random() * width,
    y: seed ? Math.random() * height : -12,
    vx: (Math.random() - 0.5) * 0.5,
    vy: 0.4 + Math.random() * 0.7,
    size: 3 + Math.random() * 4,
    rot: Math.random() * Math.PI * 2,
    vr: (Math.random() - 0.5) * 0.02,
    alpha: 0.35 + Math.random() * 0.5,
    phase: Math.random() * Math.PI * 2,
  };

  if (kind === 'snow') {
    base.vy = 0.3 + Math.random() * 0.5;
    base.size = 1.2 + Math.random() * 2.2;
    base.alpha = 0.4 + Math.random() * 0.5;
  }

  if (kind === 'fireflies') {
    base.vy = (Math.random() - 0.5) * 0.3;
    base.size = 1.4 + Math.random() * 1.6;
    base.alpha = 0.2 + Math.random() * 0.35;
  }

  if (kind === 'leaf') {
    base.vy = 0.3 + Math.random() * 0.5;   // 比樱花慢，飘落更悠然
    base.size = 4 + Math.random() * 4;
    base.alpha = 0.3 + Math.random() * 0.4;
    base.vr = (Math.random() - 0.5) * 0.05; // 翻转稍快，落叶打旋
  }

  return base;
}

function startSeason(kinds: Season[], ambient: boolean): void {
  const canvas = document.querySelector<HTMLCanvasElement>('[data-sg-effects]');
  const ctx = canvas?.getContext('2d');
  if (!canvas || !ctx) return;

  /* PJAX 换页可能带来新的天气：先停掉上一轮 rAF 与监听，再按新档位重启。 */
  stopSeason?.();
  if (kinds.length === 0) return;

  let width = 0;
  let height = 0;
  let raf = 0;
  let running = true;

  const resize = (): void => {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  };
  resize();

  /* 每种粒子按自己的密度单独取数量，再合到一个数组里 —— 组合档位里两类粒子
     互不挤占名额，也不会因为种类变多而把同屏粒子数翻倍。 */
  /* 密度：全站配置按视口自适应；岁候页天气覆盖档整体乘 0.6，小屏再减半 ——
     窗外只需一点天气，铺满就抢戏了。 */
  const densityScale = ambient ? (isSmallScreen() ? 0.3 : 0.6) : 1;
  const particles: Particle[] = [];
  for (const kind of kinds) {
    const full = Math.min(DENSITY[kind], Math.round((width * height) / 26000) + 12);
    const count = Math.max(4, Math.round(full * densityScale));
    for (let i = 0; i < count; i += 1) particles.push(createParticle(kind, width, height, true));
  }

  const drawSakura = (p: Particle): void => {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rot);
    ctx.beginPath();
    ctx.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(216, 167, 158, ${p.alpha * 0.55})`; // 樱灰粉，仅点缀
    ctx.fill();
    ctx.restore();
  };

  const drawSnow = (p: Particle): void => {
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(249, 242, 224, ${p.alpha})`; // 宣纸素白
    ctx.fill();
  };

  /* 枯叶赭：偏暖的低饱和褐，压在宣纸 / 玄墨底上都不跳色；
     两段二次贝塞尔勾出叶片轮廓，中脉一笔，偶尔翻面时整体收暗。 */
  const drawLeaf = (p: Particle): void => {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rot);
    const flip = 0.55 + Math.abs(Math.cos(p.rot)) * 0.45; // 翻面明暗
    ctx.beginPath();
    ctx.moveTo(0, -p.size);
    ctx.quadraticCurveTo(p.size * 0.85, 0, 0, p.size);
    ctx.quadraticCurveTo(-p.size * 0.85, 0, 0, -p.size);
    ctx.fillStyle = `rgba(154, 118, 92, ${p.alpha * 0.6 * flip})`; // 枯叶赭
    ctx.fill();
    ctx.beginPath();
    ctx.moveTo(0, -p.size * 0.8);
    ctx.lineTo(0, p.size * 0.8);
    ctx.strokeStyle = `rgba(120, 88, 68, ${p.alpha * 0.5 * flip})`;
    ctx.lineWidth = 0.6;
    ctx.stroke();
    ctx.restore();
  };

  const drawFirefly = (p: Particle): void => {
    const glow = p.size * 3.2;
    const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, glow);
    gradient.addColorStop(0, `rgba(168, 174, 178, ${p.alpha})`); // 淡竹青微光
    gradient.addColorStop(1, 'rgba(168, 174, 178, 0)');
    ctx.beginPath();
    ctx.arc(p.x, p.y, glow, 0, Math.PI * 2);
    ctx.fillStyle = gradient;
    ctx.fill();
  };

  const step = (): void => {
    if (!running) return;

    ctx.clearRect(0, 0, width, height);

    for (const p of particles) {
      p.phase += 0.01;

      if (p.kind === 'fireflies') {
        p.x += p.vx + Math.sin(p.phase * 1.4) * 0.25;
        p.y += p.vy + Math.cos(p.phase) * 0.25;
        if (p.x < -20) p.x = width + 20;
        if (p.x > width + 20) p.x = -20;
        if (p.y < -20) p.y = height + 20;
        if (p.y > height + 20) p.y = -20;
        p.alpha = 0.12 + Math.abs(Math.sin(p.phase * 0.8)) * 0.45;
        drawFirefly(p);
        continue;
      }

      const sway = p.kind === 'sakura' ? 0.55 : p.kind === 'leaf' ? 0.8 : 0.2;
      p.x += p.vx + Math.sin(p.y / 70 + p.phase) * sway;
      p.y += p.vy;
      p.rot += p.vr;

      if (p.y > height + 12) {
        Object.assign(p, createParticle(p.kind, width, height));
        p.y = -12;
      }
      if (p.x > width + 20) p.x = -20;
      if (p.x < -20) p.x = width + 20;

      if (p.kind === 'sakura') {
        drawSakura(p);
      } else if (p.kind === 'leaf') {
        drawLeaf(p);
      } else {
        drawSnow(p);
      }
    }

    raf = window.requestAnimationFrame(step);
  };

  step();

  const onVisibility = (): void => {
    if (document.hidden) {
      running = false;
      window.cancelAnimationFrame(raf);
    } else if (!running) {
      running = true;
      step();
    }
  };
  window.addEventListener('resize', resize, { passive: true });
  document.addEventListener('visibilitychange', onVisibility);

  /* 停机钩子：换页重启时先摘掉旧监听与 rAF，避免双层粒子叠加滚动掉帧 */
  stopSeason = () => {
    running = false;
    window.cancelAnimationFrame(raf);
    window.removeEventListener('resize', resize);
    document.removeEventListener('visibilitychange', onVisibility);
    ctx.clearRect(0, 0, width, height);
    stopSeason = null;
  };
}

function initDanmaku(list: string[]): void {
  const host = document.querySelector<HTMLElement>('[data-sg-danmaku]');
  if (!host || !list.length) return;

  let index = 0;
  const spawn = (): void => {
    if (document.hidden) return;
    const span = document.createElement('span');
    span.textContent = list[index % list.length] ?? '';
    span.style.top = `${8 + Math.random() * 78}%`;
    span.style.animationDuration = `${14 + Math.random() * 12}s`;
    host.appendChild(span);
    span.addEventListener('animationend', () => span.remove(), { once: true });
    index += 1;
  };

  spawn();
  window.setInterval(spawn, 6000);
}

/**
 * 点击涟漪：照搬源站 components/ClickEffect.tsx 的 canvas 圆环扩散。
 * 独立 canvas（.sg-ripple，z-index 9990），因为内容层 .sg-main 是 z-index:1，
 * 画在 .sg-effects 的 0 层会被卡片整块遮住。
 */
const RIPPLE_RGB = '60, 92, 95'; // 竹青，替代原 indigo

interface Ripple {
  x: number;
  y: number;
  r: number;
  opacity: number;
  velocity: number;
}

function initClickEffect(): void {
  const canvas = document.createElement('canvas');
  canvas.className = 'sg-ripple';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.appendChild(canvas);

  const ctx = canvas.getContext('2d');
  if (!ctx) {
    canvas.remove();
    return;
  }

  let width = 0;
  let height = 0;
  let frame = 0;
  let last = 0;
  const ripples: Ripple[] = [];

  const resize = (): void => {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.floor(width * dpr);
    canvas.height = Math.floor(height * dpr);
    canvas.style.width = `${width}px`;
    canvas.style.height = `${height}px`;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
  };

  resize();
  window.addEventListener('resize', resize, { passive: true });

  const tick = (now: number): void => {
    // 以 60fps 为基准归一化，120Hz 屏幕上涟漪不会缩成一半寿命
    const step = Math.min(48, last ? now - last : 16.67) / 16.67;
    last = now;

    ctx.clearRect(0, 0, width, height);
    ctx.shadowBlur = 15;
    ctx.shadowColor = `rgba(${RIPPLE_RGB}, .5)`;

    for (let i = ripples.length - 1; i >= 0; i -= 1) {
      const item = ripples[i];
      item.r += item.velocity * step;
      item.velocity *= 0.96 ** step;
      item.opacity -= 0.015 * step;

      if (item.opacity <= 0) {
        ripples.splice(i, 1);
        continue;
      }

      ctx.beginPath();
      ctx.arc(item.x, item.y, item.r, 0, Math.PI * 2);
      ctx.strokeStyle = `rgba(${RIPPLE_RGB}, ${item.opacity})`;
      ctx.lineWidth = 2;
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(item.x, item.y, item.r * 0.5, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${RIPPLE_RGB}, ${item.opacity * 0.3})`;
      ctx.fill();
    }

    if (ripples.length) {
      frame = window.requestAnimationFrame(tick);
    } else {
      frame = 0;
      last = 0;
      ctx.clearRect(0, 0, width, height);
    }
  };

  document.addEventListener('pointerdown', (event) => {
    if (event.pointerType === 'mouse' && event.button !== 0) return;

    ripples.push({ x: event.clientX, y: event.clientY, r: 0, opacity: 0.6, velocity: 2.5 });
    if (ripples.length > 10) ripples.shift();

    if (!frame) {
      last = 0;
      frame = window.requestAnimationFrame(tick);
    }
  }, { passive: true });
}

const SEASONS: Season[] = ['sakura', 'snow', 'fireflies', 'leaf'];

export function initEffects(): void {
  const cfg = config();
  const off = reducedMotion() || isSmallScreen();

  // 涟漪在移动端也保留（触感反馈），只受 reduced-motion 限制
  if (cfg.clickEffect && !reducedMotion()) {
    initClickEffect();
  }


  if (off) return;

  /* 后台的值可能是组合（`sakura,snow`），拆开后只保留认识的粒子名；
     旧版本残留的 meteor 值会被静默忽略，不会把整组特效一起拖垮。 */
  const kinds = String(cfg.season ?? 'none')
    .split(',')
    .map((name) => name.trim())
    .filter((name): name is Season => (SEASONS as string[]).includes(name));

  if (cfg.effects && kinds.length) {
    startSeason(kinds, false);
  }

  if (cfg.danmaku.length && !off) {
    initDanmaku(cfg.danmaku);
  }
}
