import { pageFrame, pageOn } from './lifecycle';

/**
 * 手机端自绘滚动条（对齐 Fomalhaut source/js/modules/shell.js 末段）。
 *
 * 手机浏览器用的是系统 overlay 滚动条，会完全忽略 ::-webkit-scrollbar，
 * 电脑端那条主题色斜条纹在手机宽度下不会出现，所以这里画一条一样的：
 * 固定在右边缘、长度按可视比例、位置随滚动进度走；只要页面能滚就一直显示，
 * 与电脑端「滚动条不隐藏」的取舍保持一致（样式见 tokens.css 的 #mscrollbar）。
 */
const TRACK_ID = 'mscrollbar';
const THUMB_ID = 'mscrollbar-thumb';
const MIN_THUMB = 30;

export function initScrollbar(): void {
  let track: HTMLElement | null = null;
  let thumb: HTMLElement | null = null;
  let frame: number | null = null;
  let lastHeight: string | null = null;
  let lastTransform: string | null = null;
  let shown = false;

  const ensure = (body: HTMLElement): void => {
    // 整页导航可能替换轨道或滑块；不能只验证 track。
    if (track && thumb && body.contains(track) && track.contains(thumb)) return;
    track = document.getElementById(TRACK_ID);
    if (!track || !body.contains(track)) {
      track = document.createElement('div');
      track.id = TRACK_ID;
      body.appendChild(track);
    }
    thumb = document.getElementById(THUMB_ID);
    if (!thumb || !track.contains(thumb)) {
      thumb = document.createElement('div');
      thumb.id = THUMB_ID;
      track.appendChild(thumb);
    }
    // 新绑定节点以当前值为基准，不继承旧 DOM 的写入缓存。
    lastHeight = thumb.style.height;
    lastTransform = thumb.style.transform;
    shown = track.classList.contains('mscrollbar-on');
  };

  const setVisible = (visible: boolean): void => {
    if (shown === visible || !track) return;
    track.classList[visible ? 'add' : 'remove']('mscrollbar-on');
    shown = visible;
  };

  const skipDesktop = (): boolean => {
    // 桌面必须在任何高度/滚动布局读取以及节点创建之前退出。
    if (window.innerWidth <= 768) return false;
    if (frame !== null) window.cancelAnimationFrame(frame);
    frame = null;
    if (track && document.body && document.body.contains(track)) {
      setVisible(false);
    } else {
      track = thumb = null;
    }
    return true;
  };

  const update = (): void => {
    frame = null;
    if (skipDesktop()) return;
    const body = document.body;
    if (!body) return;
    const doc = document.documentElement;
    // 读阶段：所有滚动/布局值先采样，随后创建节点和写样式，避免读写交错。
    const vh = window.innerHeight || doc.clientHeight;
    const total = Math.max(body.scrollHeight, doc.scrollHeight, body.offsetHeight, doc.offsetHeight);
    const visible = total > vh + 1;
    let height = '';
    let transform = '';
    if (visible) {
      const h = Math.max(MIN_THUMB, Math.round((vh * vh) / total));
      let ratio = (window.pageYOffset || doc.scrollTop || 0) / (total - vh);
      if (ratio < 0) ratio = 0;
      if (ratio > 1) ratio = 1;
      height = `${h}px`;
      transform = `translateY(${Math.round(ratio * (vh - h))}px)`;
    }
    // 写阶段：节点替换时重绑；相同的像素值和可见状态不重复写入。
    ensure(body);
    if (visible && thumb) {
      if (lastHeight !== height) {
        thumb.style.height = height;
        lastHeight = height;
      }
      if (lastTransform !== transform) {
        thumb.style.transform = transform;
        lastTransform = transform;
      }
    }
    setVisible(visible);
  };

  const schedule = (): void => {
    if (skipDesktop() || frame !== null) return;
    frame = pageFrame(update);
  };

  pageOn(window, 'scroll', schedule, { passive: true });
  pageOn(window, 'resize', schedule);
  schedule();
}
