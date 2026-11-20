/**
 * 页面切换进度条（2026-11-20，用户要求增强跳转感知）：
 * · 首次整页加载：DOM 就绪后从 0 缓慢推到 72%，load 事件补齐并淡出；
 * · PJAX 站内跳转：复用 navigation.ts 已有的 html.sg-navigating 类推到 88%，
 *   sg:navigated 事件补齐淡出；跳转失败走整页重载，仍走上面的首屏逻辑。
 * 节点常驻 header.php，位于 #sg-main 之外，PJAX 替换正文不会把它带走。
 * 本模块只在 boot() 调一次，不随 mountPage 重复绑定。
 */
export function initProgress(): void {
  const bar = document.querySelector<HTMLElement>('[data-sg-progress-bar]');
  if (!bar) return;
  const root = document.documentElement;
  let hideTimer = 0;

  const finish = (): void => {
    window.clearTimeout(hideTimer);
    bar.classList.add('is-done');
    hideTimer = window.setTimeout(() => {
      bar.classList.remove('is-done');
      root.classList.remove('sg-loading');
    }, 420);
  };

  if (document.readyState !== 'complete') {
    root.classList.add('sg-loading');
    window.addEventListener('load', finish, { once: true });
    /* 壁纸/字体等慢资源可能把 load 拖很久：给整页首访留一条 3.5 秒兜底，
       避免进度条停在 72% 不动（PJAX 跳转不依赖这个定时器）。 */
    window.setTimeout(finish, 3500);
  }
  document.addEventListener('sg:navigated', finish);
}
