/**
 * 岁候 · 轮盘交互（评审稿 §6.1 / §6.4 / §7.7）
 *
 * 做五件事，别的都不做：
 *   1) hover 节气 → 它所属的整段宫弧亮起；hover 宫 → 它覆盖的两个节气同时亮起
 *   2) 中心读数跟着 hover 变，移出即复原
 *   3) roving tabindex：整环一个 Tab 停靠点，←/→/Home/End 在 24 节气间移动，
 *      ↑/↓ 在节气与所属宫之间切换
 *   4) 盘转针定（第七十七轮）：指针恒在 12 点钟、不动；整盘绕心旋转，
 *      拖动即手转盘面，落位后吸附到扇区中心再跳页 —— 与目标页的静置角完全一致，
 *      所以换页时盘面不动，只有周围内容换掉，没有跳帧。
 *   5) 求签：摇 4–5 圈落在随机一节上，报出签文后前往该节气。
 *
 * 角度只有一个真相：CSS 变量 --sg-rot（写在 .sg-wheel-plot 上），
 * rotor 的 transform 与刻度带的 ::after 都读它，JS 只改这一个值。
 *
 * 生命周期：由 main.ts 的 mountPage() 调用。PJAX 换页时 mountPage 会重跑，
 * 事件全部走 pageOn（随页面销毁自动解绑），DOM 引用一律现查，不存模块顶层。
 */
import { onPageDispose, pageOn, pageSignal, pageTimeout } from './lifecycle';

type Focusable = { focus: () => void; getAttribute: (name: string) => string | null; setAttribute: (name: string, value: string) => void };

/** SVG viewBox 边长：字号换算与角度计算都以它为基准 */
const VIEW = 520;
/** 每节气 15°，扇区中心在起点 +7.5° */
const STEP = 15;
const HALF = STEP / 2;
/** 与 CSS 的 --sg-spin-dur 对齐（求签旋转时长） */
const SPIN_MS = 3400;
/** 求签开局是否已经用过（一次会话只摇一次，别每开一页都摇） */
const DRAWN_KEY = 'sg-season-drawn';

export function initSeasonWheel(): void {
  const wheel = document.querySelector<HTMLElement>('.sg-wheel');
  if (!wheel) return;

  const plot = wheel.querySelector<HTMLElement>('[data-sg-wheel-plot]');
  const terms = Array.from(wheel.querySelectorAll<Element>('[data-sg-term-i]'));
  const gongs = Array.from(wheel.querySelectorAll<Element>('[data-sg-gong-i]'));
  if (!plot || terms.length === 0) return;

  const nameEl = wheel.querySelector<HTMLElement>('[data-sg-wheel-name]');
  const subEl = wheel.querySelector<HTMLElement>('[data-sg-wheel-sub]');
  const originName = nameEl?.textContent ?? '';
  const originSub = subEl?.textContent ?? '';

  const gongOf = new Map<string, Element>();
  for (const gong of gongs) gongOf.set(gong.getAttribute('data-sg-gong-i') ?? '', gong);

  /* 求签期间锁住 hover 读数：签文报出来之后不该被一次划过的鼠标改掉 */
  let locked = false;

  /* ---------- 字号钉死（第七十七轮）----------
     盘面是 viewBox 520 的 SVG，写 13px 的标签会随盘宽等比缩水（336px 盘面只剩 8.4px）。
     这里把「想要的真实像素」(--sg-wheel-target) 反算成 viewBox 单位，写进 --sg-wheel-fs，
     CSS 只用 calc(var(--sg-wheel-fs) × 系数)，于是盘宽怎么变，字都是 14px。 */
  const pinFont = (): void => {
    const width = plot.clientWidth;
    if (!width) return;
    const target = parseFloat(getComputedStyle(plot).getPropertyValue('--sg-wheel-target')) || 14;
    plot.style.setProperty('--sg-wheel-fs', `${(target * VIEW) / width}px`);
  };
  pinFont();
  if (typeof ResizeObserver !== 'undefined') {
    const observer = new ResizeObserver(pinFont);
    observer.observe(plot);
    onPageDispose(() => observer.disconnect());
  }

  /* ---------- 角度的唯一入口 ---------- */
  const rot = (): number => parseFloat(plot.style.getPropertyValue('--sg-rot')) || 0;
  const setRot = (deg: number): void => {
    plot.style.setProperty('--sg-rot', `${deg}deg`);
  };
  /** 针尖（屏幕 0°）正对着的那个节气：扇区 i 占盘面局部角 [i×15, i×15+15) */
  const needleIndex = (deg: number): number => {
    let local = -(deg % 360);
    if (local < 0) local += 360;
    return Math.floor(local / STEP) % 24;
  };

  const clearLinked = (): void => {
    for (const el of terms) el.classList.remove('is-linked');
    for (const el of gongs) el.classList.remove('is-linked');
  };

  const restore = (): void => {
    if (locked) return;
    clearLinked();
    if (nameEl) nameEl.textContent = originName;
    if (subEl) subEl.textContent = originSub;
  };

  const highlightTerm = (term: Element): void => {
    clearLinked();
    term.classList.add('is-linked');
    const gong = gongOf.get(term.getAttribute('data-sg-gong') ?? '');
    if (gong) gong.classList.add('is-linked');
    if (nameEl) nameEl.textContent = term.getAttribute('data-sg-label') ?? originName;
    if (subEl) subEl.textContent = term.getAttribute('data-sg-sub') ?? originSub;
  };

  const highlightGong = (gong: Element): void => {
    clearLinked();
    gong.classList.add('is-linked');
    const index = gong.getAttribute('data-sg-gong-i') ?? '';
    for (const term of terms) {
      if (term.getAttribute('data-sg-gong') === index) term.classList.add('is-linked');
    }
    if (nameEl) nameEl.textContent = gong.getAttribute('data-sg-label') ?? originName;
    if (subEl) subEl.textContent = '';
  };

  pageOn(wheel, 'mouseover', (event) => {
    if (locked || plot.classList.contains('is-drag')) return;
    const target = event.target as Element | null;
    const term = target?.closest?.('[data-sg-term-i]');
    if (term) { highlightTerm(term); return; }
    const gong = target?.closest?.('[data-sg-gong-i]');
    if (gong) highlightGong(gong);
  });

  pageOn(wheel, 'mouseleave', restore);

  /* 键盘焦点也触发同一套高亮：鼠标能看见的关系，键盘也要能看见 */
  pageOn(wheel, 'focusin', (event) => {
    const target = event.target as Element | null;
    const term = target?.closest?.('[data-sg-term-i]');
    if (term) { highlightTerm(term); return; }
    const gong = target?.closest?.('[data-sg-gong-i]');
    if (gong) highlightGong(gong);
  });

  const setTabStop = (active: Element): void => {
    for (const el of [...terms, ...gongs]) el.setAttribute('tabindex', el === active ? '0' : '-1');
  };

  const move = (current: Element, delta: number): void => {
    const index = terms.indexOf(current);
    if (index < 0) return;
    const next = terms[(index + delta + terms.length) % terms.length];
    setTabStop(next);
    (next as unknown as Focusable).focus();
  };

  pageOn(wheel, 'keydown', (event) => {
    const key = (event as KeyboardEvent).key;
    const target = (event.target as Element | null)?.closest?.('[data-sg-term-i], [data-sg-gong-i]');
    if (!target) return;

    if (key === 'Escape') {
      restore();
      return;
    }

    const isTerm = target.hasAttribute('data-sg-term-i');

    if (key === 'ArrowRight' && isTerm) {
      event.preventDefault();
      move(target, 1);
    } else if (key === 'ArrowLeft' && isTerm) {
      event.preventDefault();
      move(target, -1);
    } else if (key === 'Home' && isTerm) {
      event.preventDefault();
      setTabStop(terms[0]);
      (terms[0] as unknown as Focusable).focus();
    } else if (key === 'End' && isTerm) {
      event.preventDefault();
      const last = terms[terms.length - 1];
      setTabStop(last);
      (last as unknown as Focusable).focus();
    } else if (key === 'ArrowDown' && isTerm) {
      const gong = gongOf.get(target.getAttribute('data-sg-gong') ?? '');
      if (gong) {
        event.preventDefault();
        setTabStop(gong);
        (gong as unknown as Focusable).focus();
      }
    } else if (key === 'ArrowUp' && !isTerm) {
      const index = target.getAttribute('data-sg-gong-i') ?? '';
      const term = terms.find((el) => el.getAttribute('data-sg-gong') === index);
      if (term) {
        event.preventDefault();
        setTabStop(term);
        (term as unknown as Focusable).focus();
      }
    }
  });

  /* ---------- 求签（第七十七轮）----------
     自动旋转与手动求签共用这一条链路：摇 4–5 圈 → 落在随机一节 → 报签 → 前往。
     落位角恒为 −(i×15+7.5)，与目标页的静置角同值，所以换页时盘面不跳。 */
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let spinToken = 0;

  const spin = (index: number, done: () => void): void => {
    const token = ++spinToken;
    const rest = -(index * STEP + HALF);
    /* 至少 3 圈、最多 5 圈：先读一次计算值，让 .is-drawing 的 transition 先落地再改角度 */
    const target = rest - 360 * (4 + Math.floor(Math.random() * 2));
    plot.classList.remove('is-snapping');
    plot.classList.add('is-drawing');
    void getComputedStyle(plot).getPropertyValue('--sg-rot');

    let finished = false;
    const finish = (): void => {
      if (finished || token !== spinToken) return;
      finished = true;
      plot.classList.remove('is-drawing');
      done();
    };

    setRot(target);
    if (reduceMotion) {
      finish();
      return;
    }
    const rotor = plot.querySelector('.sg-wheel-rotor');
    rotor?.addEventListener('transitionend', finish, { once: true, signal: pageSignal() });
    pageTimeout(finish, SPIN_MS + 120);
  };

  const draw = (): void => {
    if (locked || terms.length === 0) return;
    locked = true;
    const index = Math.floor(Math.random() * terms.length);
    const term = terms[index];
    const parts = (term.getAttribute('data-sg-label') ?? '').split(' · ');
    if (nameEl) nameEl.textContent = '求签';
    if (subEl) subEl.textContent = '摇一卦，落一节';

    spin(index, () => {
      if (nameEl) nameEl.textContent = parts[0] ?? originName;
      if (subEl) subEl.textContent = '签 · ' + (parts[1] ?? '');
      const href = term.getAttribute('href');
      if (href) pageTimeout(() => window.location.assign(href), 900);
    });
  };

  const drawBtn = wheel.querySelector<HTMLButtonElement>('[data-sg-draw]');
  if (drawBtn) pageOn(drawBtn, 'click', () => draw());

  /* 开局自动求签：只在「裸地址」（没有任何参数）且本会话还没摇过时触发。
     带 ?t/?g/?d/?y 进来的人已经明确要看某一段，不该被摇晃打断；
     reduced-motion 下也不摇 —— 否则页面会毫无预兆地跳到随机一节。 */
  if (wheel.dataset.sgAutoDraw === '1' && !reduceMotion) {
    let drawn = false;
    try {
      drawn = sessionStorage.getItem(DRAWN_KEY) === '1';
      if (!drawn) sessionStorage.setItem(DRAWN_KEY, '1');
    } catch {
      /* 隐私模式禁用了 sessionStorage：按「没摇过」处理，不影响功能 */
    }
    if (!drawn) pageTimeout(draw, 900);
  }

  /* ---------- 拖动转盘（第七十六轮，第七十七轮改为盘转针定）----------
     抓住盘面任意位置转，盘跟着手走，针不动、针尖所指即读数；松手吸附到扇区中心再跳页。
     等价于天文馆的时间轴，但复用轮盘本身，不新增一根滑杆。
     只在「有精确指针」的设备上启用：触屏要保留页面滚动，手机上走滑条与键盘。 */
  const hasFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  /* 触屏也能拖盘（升级）：CSS touch-action: pan-y 保证纵向滚动优先，横向拖动归盘面；
     死区比桌面大一倍，手滑不会误发跳页 */
  const canDrag = hasFinePointer || window.matchMedia('(hover: none)').matches;

  /* ---------- 盘面 3D 倾斜（L2 交互层，升级）----------
     桌面精确指针下，盘面朝指针方向轻倾 ±3.2 度：仪器被「拿在手里看」的错觉。
     rAF 节流写 CSS 变量；拖动/求签时让位（CSS 里 .is-drag/.is-drawing 直接 none）。 */
  if (hasFinePointer && !reduceMotion) {
    let tiltRaf = 0;
    let tiltX = 0;
    let tiltY = 0;
    const applyTilt = (): void => {
      tiltRaf = 0;
      plot.style.setProperty('--sg-tilt-x', `${tiltX.toFixed(2)}deg`);
      plot.style.setProperty('--sg-tilt-y', `${tiltY.toFixed(2)}deg`);
    };
    pageOn(wheel, 'pointermove', (event) => {
      if (locked || plot.classList.contains('is-drag') || plot.classList.contains('is-drawing')) return;
      const rect = plot.getBoundingClientRect();
      const nx = ((event.clientX - rect.left) / rect.width) * 2 - 1;
      const ny = ((event.clientY - rect.top) / rect.height) * 2 - 1;
      tiltX = -ny * 3.2;
      tiltY = nx * 3.2;
      if (!tiltRaf) tiltRaf = requestAnimationFrame(applyTilt);
    });
    pageOn(wheel, 'pointerleave', () => {
      if (tiltRaf) {
        cancelAnimationFrame(tiltRaf);
        tiltRaf = 0;
      }
      tiltX = 0;
      tiltY = 0;
      plot.style.setProperty('--sg-tilt-x', '0deg');
      plot.style.setProperty('--sg-tilt-y', '0deg');
    });
  }

  /* ---------- 外环滚动缓转（L3 叙事层，升级）----------
     主环仍盘转针定；只有最外的日刻度带随页面滚动缓转（全程 24 度，一天一度）。
     滚完一页 = 外环走过一天 —— 与天空层的「太阳走一年」同一叙事。 */
  if (!reduceMotion) {
    const sheet = document.querySelector<HTMLElement>('.sg-season-sheet') ?? document.body;
    let driftRaf = 0;
    const applyDrift = (): void => {
      driftRaf = 0;
      const total = Math.max(1, sheet.offsetHeight - window.innerHeight);
      const p = Math.min(1, Math.max(0, window.scrollY / total));
      plot.style.setProperty('--sg-drift', `${(p * 24).toFixed(2)}deg`);
    };
    document.addEventListener(
      'scroll',
      () => {
        if (!driftRaf) driftRaf = requestAnimationFrame(applyDrift);
      },
      { passive: true, signal: pageSignal() },
    );
    applyDrift();
  }

  if (canDrag) {
    let dragging = false;
    let moved = false;
    let suppressClick = false;
    let startX = 0;
    let startY = 0;
    let startRot = 0;
    let startAngle = Number.NaN;
    let dragIndex = -1;

    /** 指针相对盘心的角度（0° 在正上、顺时针）；太靠近盘心时角度不稳定，返回 null */
    const pointerAngle = (clientX: number, clientY: number): number | null => {
      const rect = plot.getBoundingClientRect();
      const dx = clientX - (rect.left + rect.width / 2);
      const dy = clientY - (rect.top + rect.height / 2);
      if (Math.hypot(dx, dy) < rect.width * 0.14) return null;
      let deg = (Math.atan2(dx, -dy) * 180) / Math.PI;
      if (deg < 0) deg += 360;
      return deg;
    };

    pageOn(plot, 'pointerdown', (event) => {
      if (event.button !== 0 || locked || plot.classList.contains('is-drawing')) return;
      dragging = true;
      moved = false;
      startX = event.clientX;
      startY = event.clientY;
      startRot = rot();
      startAngle = pointerAngle(event.clientX, event.clientY) ?? Number.NaN;
    });

    pageOn(document, 'pointermove', (event) => {
      if (!dragging || locked) return;
      if (!moved) {
        /* 死区：手抖不算拖动，点按仍然是一次点击；触屏死区大一倍 */
        const dead = event.pointerType === 'touch' ? 14 : 6;
        if (Math.hypot(event.clientX - startX, event.clientY - startY) < dead) return;
        moved = true;
        plot.classList.add('is-drag');
        dragIndex = -1;
      }
      const angle = pointerAngle(event.clientX, event.clientY);
      if (angle === null || Number.isNaN(startAngle)) return;
      /* 取最近的等角差，避免跨 0°/360° 时盘面倒着甩一圈 */
      let delta = angle - startAngle;
      while (delta > 180) delta -= 360;
      while (delta < -180) delta += 360;
      const next = startRot + delta;
      setRot(next);
      const index = needleIndex(next);
      if (index === dragIndex) return;
      dragIndex = index;
      const term = terms[index];
      if (term) highlightTerm(term);
    });

    pageOn(document, 'pointerup', () => {
      if (!dragging) return;
      dragging = false;
      plot.classList.remove('is-drag');
      if (moved && dragIndex >= 0) {
        suppressClick = true;
        moved = false;
        /* 吸附到扇区中心：取与当前角同余（差 360 整数倍）的那个角，不反着拧回去。
           这个角就是目标页的静置角 —— 换页后盘面停在原地，只有周围内容换掉。 */
        const center = -(dragIndex * STEP + HALF);
        const nearest = center + 360 * Math.round((rot() - center) / 360);
        plot.classList.add('is-snapping');
        setRot(nearest);
        const href = terms[dragIndex].getAttribute('href');
        pageTimeout(() => {
          if (href) window.location.assign(href);
        }, 320);
        return;
      }
      moved = false;
      restore();
    });

    pageOn(plot, 'pointercancel', () => {
      dragging = false;
      moved = false;
      plot.classList.remove('is-drag');
      restore();
    });

    /* 拖动结束浏览器还会补一次 click：拦掉，否则「松手跳一次、click 再跳一次」 */
    pageOn(
      plot,
      'click',
      (event) => {
        if (!suppressClick) return;
        suppressClick = false;
        event.preventDefault();
        event.stopPropagation();
      },
      true,
    );
  }
}
