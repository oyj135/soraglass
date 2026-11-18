/**
 * 背景特效：季节粒子（樱花 / 飘雪 / 萤火虫）、背景弹幕、点击涟漪。
 * 全部合到单个 canvas + 单个 rAF，避免多层 DOM 叠加导致滚动掉帧。
 * 移动端与 prefers-reduced-motion 下整体关闭（CSS 也同步隐藏）。
 */
import { config,isSmallScreen,reducedMotion } from './runtime';

interface Particle {
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

type Season = 'sakura' | 'snow' | 'fireflies';

const DENSITY: Record<Season, number> = {
  sakura: 34,
  snow: 60,
  fireflies: 26,
};

function createParticle(kind: Season, width: number, height: number, seed = false): Particle {
  const base: Particle = {
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

  return base;
}

function initSeason(kind: Season): void {
  const canvas = document.querySelector<HTMLCanvasElement>('[data-sg-effects]');
  const ctx = canvas?.getContext('2d');
  if (!canvas || !ctx) return;

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

  const count = Math.min(DENSITY[kind], Math.round((width * height) / 26000) + 12);
  const particles: Particle[] = Array.from({ length: count }, () => createParticle(kind, width, height, true));

  const drawSakura = (p: Particle): void => {
    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rot);
    ctx.beginPath();
    ctx.ellipse(0, 0, p.size, p.size * 0.55, 0, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(251, 207, 232, ${p.alpha})`;
    ctx.fill();
    ctx.restore();
  };

  const drawSnow = (p: Particle): void => {
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(241, 245, 249, ${p.alpha})`;
    ctx.fill();
  };

  const drawFirefly = (p: Particle): void => {
    const glow = p.size * 3.2;
    const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, glow);
    gradient.addColorStop(0, `rgba(253, 224, 71, ${p.alpha})`);
    gradient.addColorStop(1, 'rgba(253, 224, 71, 0)');
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

      if (kind === 'fireflies') {
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

      p.x += p.vx + Math.sin(p.y / 70 + p.phase) * (kind === 'sakura' ? 0.55 : 0.2);
      p.y += p.vy;
      p.rot += p.vr;

      if (p.y > height + 12) {
        Object.assign(p, createParticle(kind, width, height));
        p.y = -12;
      }
      if (p.x > width + 20) p.x = -20;
      if (p.x < -20) p.x = width + 20;

      if (kind === 'sakura') {
        drawSakura(p);
      } else {
        drawSnow(p);
      }
    }

    raf = window.requestAnimationFrame(step);
  };

  step();

  window.addEventListener('resize', resize, { passive: true });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      running = false;
      window.cancelAnimationFrame(raf);
    } else if (!running) {
      running = true;
      step();
    }
  });
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
const RIPPLE_RGB = '129, 140, 248';

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

export function initEffects(): void {
  const cfg = config();
  const off = reducedMotion() || isSmallScreen();

  // 涟漪在移动端也保留（触感反馈），只受 reduced-motion 限制
  if (cfg.clickEffect && !reducedMotion()) {
    initClickEffect();
  }

  if (off) return;

  if (cfg.effects && cfg.season !== 'none') {
    initSeason(cfg.season);
  }

  if (cfg.danmaku.length) {
    initDanmaku(cfg.danmaku);
  }
}
