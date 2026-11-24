/**
 * 岁候 · 天空层（升级重建版）
 *
 * 滚动长纸 = 太阳走一年：黄道弧拱在舞台背后，24 道刻度对应节气，
 * 当前太阳是一枚呼吸的朱点；每节气的篇数化作沿弧向外伸展的短刺
 * （热力弧，数据来自 page-season.php 写在舞台上的 data-sg-term-counts）。
 * 两层星随页面滚动按深度缓移 —— 内容读到哪，太阳走到哪。
 *
 * 几何约定：viewBox 1200×340，弧是圆心 (600,1060)、半径 880 的圆的上缘，
 * 节气 i 从左到右铺在 θ 125°→55° 上（春分在纸头、惊蛰在纸尾）。
 * reduced-motion 下：无星层视差，太阳的呼吸由 CSS 关闭。
 */
import { onPageDispose, pageSignal } from './lifecycle';

const VIEW_W = 1200;
const CX = 600;
const CY = 1060;
const R = 880;
const ARC_L = 125;
const ARC_R = 55;
const N = 24;

/** SVG 命名空间工具 */
const svgEl = (tag: string): SVGElement => document.createElementNS('http://www.w3.org/2000/svg', tag);

/** θ（度）→ 弧上坐标 */
const pt = (deg: number, radius = R): [number, number] => {
  const a = (deg * Math.PI) / 180;
  return [CX + radius * Math.cos(a), CY - radius * Math.sin(a)];
};

/** 确定性伪随机（固定种子）：星图每次访问都一样，像真正的星图 */
const lcg = (seed: number): (() => number) => {
  let s = seed;
  return () => {
    s = (s * 1664525 + 1013904223) % 4294967296;
    return s / 4294967296;
  };
};

export function initSeasonSky(): void {
  const stage = document.querySelector<HTMLElement>('.sg-season-stage');
  if (!stage || stage.querySelector('.sg-season-sky')) return;

  const counts = (stage.getAttribute('data-sg-term-counts') ?? '')
    .split(',')
    .map((s) => parseInt(s, 10) || 0);
  let nowIndex = -1;
  const cur = document.querySelector('[data-sg-term-i].is-current');
  if (cur) nowIndex = parseInt(cur.getAttribute('data-sg-term-i') ?? '', 10);
  const maxCount = Math.max(1, ...counts);

  const wrap = document.createElement('div');
  wrap.className = 'sg-season-sky';
  wrap.setAttribute('aria-hidden', 'true');
  const svg = svgEl('svg');
  svg.setAttribute('viewBox', `0 0 ${VIEW_W} 340`);
  svg.setAttribute('preserveAspectRatio', 'xMidYMid slice');

  /* 星层：远层 28 颗（随滚动缓移 6px），近层 18 颗（14px） */
  const starsFar = svgEl('g');
  starsFar.setAttribute('class', 'sky-stars-far');
  const starsNear = svgEl('g');
  starsNear.setAttribute('class', 'sky-stars-near');
  const rand = lcg(20261124);
  for (let k = 0; k < 46; k++) {
    const star = svgEl('circle');
    star.setAttribute('class', 'sky-star');
    star.setAttribute('cx', (rand() * VIEW_W).toFixed(1));
    star.setAttribute('cy', (rand() * 300).toFixed(1));
    star.setAttribute('r', (0.8 + rand() * 1.4).toFixed(2));
    (k < 28 ? starsFar : starsNear).appendChild(star);
  }

  /* 黄道弧：折线逼近，避免猜 sweep flag */
  const arcPts: string[] = [];
  for (let k = 0; k <= 60; k++) {
    const [x, y] = pt(ARC_L - ((ARC_L - ARC_R) * k) / 60);
    arcPts.push(`${x.toFixed(1)} ${y.toFixed(1)}`);
  }
  const arc = svgEl('path');
  arc.setAttribute('class', 'sky-arc');
  arc.setAttribute('d', `M${arcPts.join(' L')}`);

  /* 节气刻度（向弧内）与热力弧（向弧外）：写得多，一目了然 */
  const gTicks = svgEl('g');
  const gBars = svgEl('g');
  for (let i = 0; i < N; i++) {
    const deg = ARC_L - ((ARC_L - ARC_R) * i) / (N - 1);
    const [tx0, ty0] = pt(deg, R - 8);
    const [tx1, ty1] = pt(deg, R);
    const tick = svgEl('line');
    tick.setAttribute('class', `sky-tick${i === nowIndex ? ' is-now' : ''}`);
    tick.setAttribute('x1', tx0.toFixed(1));
    tick.setAttribute('y1', ty0.toFixed(1));
    tick.setAttribute('x2', tx1.toFixed(1));
    tick.setAttribute('y2', ty1.toFixed(1));
    gTicks.appendChild(tick);

    const c = counts[i] ?? 0;
    if (c > 0) {
      const len = Math.min(46, 6 + c * 5);
      const [bx0, by0] = pt(deg, R + 3);
      const [bx1, by1] = pt(deg, R + 3 + len);
      const bar = svgEl('line');
      bar.setAttribute('class', `sky-bar${c >= maxCount / 2 ? ' is-hot' : ''}`);
      bar.setAttribute('x1', bx0.toFixed(1));
      bar.setAttribute('y1', by0.toFixed(1));
      bar.setAttribute('x2', bx1.toFixed(1));
      bar.setAttribute('y2', by1.toFixed(1));
      gBars.appendChild(bar);
    }
  }

  /* 太阳：当令节气位置上一枚呼吸的朱点 */
  if (nowIndex >= 0) {
    const [sx, sy] = pt(ARC_L - ((ARC_L - ARC_R) * nowIndex) / (N - 1), R + 1);
    const sun = svgEl('circle');
    sun.setAttribute('class', 'sky-sun');
    sun.setAttribute('cx', sx.toFixed(1));
    sun.setAttribute('cy', sy.toFixed(1));
    sun.setAttribute('r', '5');
    svg.appendChild(sun);
  }

  svg.appendChild(starsFar);
  svg.appendChild(arc);
  svg.appendChild(gTicks);
  svg.appendChild(gBars);
  svg.appendChild(starsNear);
  wrap.appendChild(svg);
  stage.insertBefore(wrap, stage.firstChild);

  /* 星层视差：滚动 = 太阳走一年，星随深度缓移；reduced-motion 下静止 */
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduceMotion) {
    let raf = 0;
    const apply = (): void => {
      raf = 0;
      const doc = document.documentElement;
      const total = Math.max(1, doc.scrollHeight - window.innerHeight);
      const p = Math.min(1, Math.max(0, window.scrollY / total));
      starsFar.setAttribute('transform', `translate(0 ${(p * 6).toFixed(2)})`);
      starsNear.setAttribute('transform', `translate(0 ${(p * 14).toFixed(2)})`);
    };
    document.addEventListener(
      'scroll',
      () => {
        if (!raf) raf = requestAnimationFrame(apply);
      },
      { passive: true, signal: pageSignal() },
    );
    onPageDispose(() => {
      if (raf) cancelAnimationFrame(raf);
    });
    apply();
  }
}
