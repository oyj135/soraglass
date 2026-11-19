/**
 * 夜间星空（2026-11-19 第二十四轮，源站 C3「#universe」的 Soraglass 自有版）。
 *
 * 固定全屏 canvas 垫在壁纸之上、内容之下（z-index: -9，pointer-events:none）；
 * 只在夜间（html.dark）+ 桌面端 + 未开启 reduced-motion 时运行，昼夜切换跟随
 * html class（MutationObserver）。三类星体：普通星（淡紫蓝方块）、巨星（大号圆）、
 * 流星（白色亮点 + 30 段渐隐拖尾，50~120 倍速斜向右上），每颗随机 fade in/out，
 * 飞出边界即重生。季节粒子（[data-sg-effects]）不与之耦合，照常运行。
 *
 * 不抄源站压缩代码，参数按源站观感自定；标签页隐藏时自动暂停（rAF 在后台
 * 本就节流，这里显式停更省电），回前台若仍是夜间则恢复。
 */
import { isSmallScreen, reducedMotion } from './runtime';

interface Star {
  x: number;
  y: number;
  /** 巨星（大圆）/ 流星（带拖尾）/ 普通星（小方块） */
  kind: 'giant' | 'comet' | 'normal';
  r: number;
  dx: number;
  dy: number;
  opacity: number;
  /** 目标透明度上限 */
  thresh: number;
  /** 1 = 渐显，-1 = 渐隐 */
  fade: 1 | -1;
  fadeSpeed: number;
}

const TAIL = 30;

/* 星空 canvas 挂在 document.body（不在 PJAX 替换清单内），与主题 class 的
   MutationObserver、resize / visibilitychange 监听、rAF 链一样是会话级资源。
   initUniverse 随每次 mountPage 重跑，故用单例守卫确保只初始化一次——同
   player.ts 的 mountPlayer 写法。否则每切一页就多一块全屏 canvas + 一条常驻
   rAF（P0-1）。首屏因 reducedMotion / 小屏提前返回时不置位，后续可重试。 */
let universeReady = false;

function initUniverse(): void {
  if (universeReady) return;
  if (reducedMotion() || isSmallScreen()) return;
  const canvas = document.createElement('canvas');
  canvas.className = 'sg-universe';
  canvas.setAttribute('aria-hidden', 'true');
  document.body.appendChild(canvas);
  const ctx = canvas.getContext('2d');
  if (!ctx) { canvas.remove(); return; }

  let width = 0;
  let height = 0;
  let raf = 0;
  let running = false;

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

  const rand = (min: number, max: number): number => Math.random() * (max - min) + min;
  /** 概率判定：chance% */
  const chance = (percent: number): boolean => Math.random() * 100 < percent;

  const spawn = (star: Star): void => {
    star.kind = chance(4) ? 'giant' : chance(8) ? 'comet' : 'normal';
    star.x = rand(0, width - 8);
    star.y = rand(0, height);
    star.r = star.kind === 'giant' ? 2.2 : rand(1.5, 2.7);
    if (star.kind === 'comet') {
      // 流星：向右上高速斜飞
      const speed = rand(2.6, 6);
      star.dx = speed * rand(0.5, 1);
      star.dy = -speed * rand(0.5, 1);
    } else {
      // 普通星：极缓漂移
      star.dx = rand(0.05, 0.3);
      star.dy = -rand(0.05, 0.3);
    }
    star.opacity = 0;
    star.thresh = star.kind === 'comet' ? rand(0.6, 1) : rand(0.4, 1);
    star.fade = 1;
    star.fadeSpeed = rand(0.0006, 0.002);
  };

  const stars: Star[] = [];

  const ensureCount = (): void => {
    const target = Math.max(18, Math.min(90, Math.round((width * height) / 22000)));
    while (stars.length < target) {
      const star = {} as Star;
      spawn(star);
      // 首屏就位：普通星直接带随机透明度，避免齐刷刷点亮
      if (star.kind !== 'comet') star.opacity = rand(0.1, star.thresh);
      stars.push(star);
    }
    while (stars.length > target) stars.pop();
  };

  const draw = (): void => {
    ctx.clearRect(0, 0, width, height);
    for (const star of stars) {
      star.opacity += star.fade * star.fadeSpeed;
      if (star.fade === 1 && star.opacity >= star.thresh) star.fade = -1;
      if (star.fade === -1 && star.opacity <= 0) {
        spawn(star);
        continue;
      }
      star.x += star.dx;
      star.y += star.dy;
      if (star.x > width - width / 4 || star.y < -10) star.fade = -1;
      if (star.opacity <= 0) {
        spawn(star);
        continue;
      }

      if (star.kind === 'giant') {
        // 巨星：外圈柔光 + 内核，亮壁纸上也能认出是星
        const glow = star.r * 3.5;
        const gradient = ctx.createRadialGradient(star.x, star.y, 0, star.x, star.y, glow);
        gradient.addColorStop(0, `rgba(214, 216, 255, ${(star.opacity * 0.55).toFixed(3)})`);
        gradient.addColorStop(1, 'rgba(214, 216, 255, 0)');
        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.arc(star.x, star.y, glow, 0, Math.PI * 2, false);
        ctx.fill();
        ctx.beginPath();
        ctx.fillStyle = `rgba(228, 230, 255, ${star.opacity.toFixed(3)})`;
        ctx.arc(star.x, star.y, star.r + 1.2, 0, Math.PI * 2, false);
        ctx.closePath();
        ctx.fill();
      } else if (star.kind === 'comet') {
        ctx.fillStyle = `rgba(240, 242, 255, ${star.opacity.toFixed(3)})`;
        ctx.beginPath();
        ctx.arc(star.x, star.y, 1.8, 0, Math.PI * 2, false);
        ctx.fill();
        // 渐隐拖尾：沿反速度方向 30 个渐弱小方块
        for (let i = 1; i <= TAIL; i++) {
          const tailOpacity = star.opacity - (star.opacity / 16) * i;
          if (tailOpacity <= 0) break;
          ctx.fillStyle = `rgba(240, 242, 255, ${tailOpacity.toFixed(3)})`;
          ctx.fillRect(star.x - (star.dx / 4) * i, star.y - (star.dy / 4) * i - 2, 2.2, 2.2);
        }
      } else {
        ctx.fillStyle = `rgba(214, 216, 255, ${star.opacity.toFixed(3)})`;
        ctx.fillRect(star.x, star.y, star.r, star.r);
      }
    }
  };

  const step = (): void => {
    if (!running) return;
    draw();
    raf = window.requestAnimationFrame(step);
  };

  const stop = (): void => {
    if (!running) return;
    running = false;
    window.cancelAnimationFrame(raf);
    ctx.clearRect(0, 0, width, height);
  };

  const start = (): void => {
    if (running) return;
    running = true;
    resize();
    ensureCount();
    raf = window.requestAnimationFrame(step);
  };

  const sync = (): void => {
    if (document.documentElement.classList.contains('dark')) start();
    else stop();
  };

  resize();
  window.addEventListener('resize', resize);
  new MutationObserver(sync).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stop();
    else sync();
  });
  universeReady = true;
  sync();
}

export { initUniverse };
