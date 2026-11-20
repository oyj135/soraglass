/**
 * 背景特效：季节粒子（樱花 / 飘雪 / 萤火虫 / 流星）、背景弹幕、点击涟漪。
 * 全部合到单个 canvas + 单个 rAF，避免多层 DOM 叠加导致滚动掉帧。
 * 移动端与 prefers-reduced-motion 下整体关闭（CSS 也同步隐藏）。
 *
 * 2026-11-20：新增「流星」（自下向上飞行，见 drawMeteor），且粒子支持多类型共存 ——
 * 后台下拉可选「樱花 + 流星」这类组合，JS 侧按英文逗号拆成 kinds 后同帧绘制，
 * 共享同一个 canvas 与 rAF，不会出现两层动画互相盖住或各自抢帧的问题。
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

type Season = 'sakura' | 'snow' | 'fireflies' | 'meteor';

const DENSITY: Record<Season, number> = {
  sakura: 34,
  snow: 60,
  fireflies: 26,
  // 流星宁少勿多：同屏十几道就够撑起夜空，再多会像下雨
  meteor: 12,
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

  if (kind === 'meteor') {
    // 从视口下方往上飞：向右上斜掠，速度比飘落的粒子快一档，尾迹才有「划过」的感觉。
    base.x = Math.random() * width;
    base.y = seed ? Math.random() * height : height + 24 + Math.random() * 80;
    base.vx = 0.35 + Math.random() * 0.95;
    base.vy = -(2.1 + Math.random() * 3.1);
    base.size = 1.1 + Math.random() * 1.1;
    base.alpha = 0.45 + Math.random() * 0.5;
  }

  return base;
}

function initSeason(kinds: Season[]): void {
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

  /* 每种粒子按自己的密度单独取数量，再合到一个数组里 —— 组合档位里两类粒子
     互不挤占名额，也不会因为种类变多而把同屏粒子数翻倍。 */
  const particles: Particle[] = [];
  for (const kind of kinds) {
    const count = Math.min(DENSITY[kind], Math.round((width * height) / 26000) + 12);
    for (let i = 0; i < count; i += 1) particles.push(createParticle(kind, width, height, true));
  }

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

  /* 流星：头部一个亮点 + 沿飞行反方向渐隐的拖尾。
     颜色跟随主题即取即算 —— 日间壁纸偏亮，冰白会看不见，改用深青蓝；
     夜间壁纸偏暗，回到冰白 + 冷蓝尾。每帧读一次 html.dark，昼夜切换后立即生效。 */
  const drawMeteor = (p: Particle): void => {
    const dark = document.documentElement.classList.contains('dark');
    const tail = 52 + p.size * 46;
    const length = Math.hypot(p.vx, p.vy) || 1;
    const tailX = p.x - (p.vx / length) * tail;
    const tailY = p.y - (p.vy / length) * tail;

    const head = dark ? `rgba(255, 255, 255, ${p.alpha})` : `rgba(23, 92, 116, ${p.alpha * 0.72})`;
    const mid = dark ? `rgba(186, 230, 253, ${p.alpha * 0.6})` : `rgba(66, 148, 176, ${p.alpha * 0.34})`;

    const trail = ctx.createLinearGradient(p.x, p.y, tailX, tailY);
    trail.addColorStop(0, head);
    trail.addColorStop(0.45, mid);
    trail.addColorStop(1, 'rgba(186, 230, 253, 0)');
    ctx.strokeStyle = trail;
    // 日间壁纸底色亮，线要略粗一点才不被当成划痕；夜间保持细锐。
    ctx.lineWidth = p.size * (dark ? 1.3 : 1.6);
    ctx.lineCap = 'round';
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    ctx.lineTo(tailX, tailY);
    ctx.stroke();

    const glow = p.size * (dark ? 6 : 4.5);
    const halo = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, glow);
    halo.addColorStop(0, dark ? `rgba(255, 255, 255, ${p.alpha * 0.9})` : `rgba(24, 104, 130, ${p.alpha * 0.45})`);
    halo.addColorStop(1, 'rgba(255, 255, 255, 0)');
    ctx.beginPath();
    ctx.arc(p.x, p.y, glow, 0, Math.PI * 2);
    ctx.fillStyle = halo;
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

      /* 流星向上飞：越过顶部就回到底部重新出发（随机横向位置与速度），
         左右越界从另一侧绕回，长跑不会在角落堆积。 */
      if (p.kind === 'meteor') {
        p.x += p.vx;
        p.y += p.vy;
        if (p.y < -90) Object.assign(p, createParticle('meteor', width, height));
        else if (p.x > width + 60) p.x = -60;
        else if (p.x < -60) p.x = width + 60;
        drawMeteor(p);
        continue;
      }

      p.x += p.vx + Math.sin(p.y / 70 + p.phase) * (p.kind === 'sakura' ? 0.55 : 0.2);
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

const SEASONS: Season[] = ['sakura', 'snow', 'fireflies', 'meteor'];

export function initEffects(): void {
  const cfg = config();
  const off = reducedMotion() || isSmallScreen();

  // 涟漪在移动端也保留（触感反馈），只受 reduced-motion 限制
  if (cfg.clickEffect && !reducedMotion()) {
    initClickEffect();
  }

  if (off) return;

  /* 后台的值可能是组合（`snow,meteor`），拆开后只保留认识的粒子名；
     未知值（旧版本残留）自动忽略，不会把整组特效一起拖垮。 */
  const kinds = String(cfg.season ?? 'none')
    .split(',')
    .map((name) => name.trim())
    .filter((name): name is Season => (SEASONS as string[]).includes(name));

  if (cfg.effects && kinds.length) {
    initSeason(kinds);
  }

  if (cfg.danmaku.length) {
    initDanmaku(cfg.danmaku);
  }
}
