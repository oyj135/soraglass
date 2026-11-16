/**
 * 知识档案（page-timeline.php）：筛选与分页已全部改为服务端完成。
 *
 * 2026-11-16 第二十轮起，tag / s / page 三个 query 参数由服务端读取，
 * 只输出当前页 9 篇的 HTML；标签是带 href 的 <a>，分页条是真实链接，
 * 由 navigation.ts 的 PJAX 统一接管，无 JS 也能翻页。
 * 因此本模块不再做卡片显隐、标签切换或分页条渲染 —— 保留空实现仅为
 * 兼容 main.ts 的挂载约定，避免改动入口契约。若以后恢复前端筛选，
 * 需同时移除服务端的切片逻辑，避免两套分页互相打架。
 */
export function initArchive(): void {
  /* intentionally empty — filtering & pagination are server-side now */
}
