/**
 * 时光机年份胶囊（2026-11-22，page-time.php 专用）：
 *   [data-sg-time-pill]   年份胶囊 —— 点击平滑滚动到对应 [data-sg-time-year] 分区顶部
 *                         （预留导航高度），并把胶囊标记为当前定位年份；
 *   [data-sg-time-year]   年份分区，始终渲染、永不隐藏 —— 胶囊只做「跳转定位」，
 *                         不做筛选（2026-11-22 下午用户反馈：点击 2026 后 2023/2025
 *                         被隐藏，误以为分区丢失；跳转语义下所有年份恒在）。
 * 页面无胶囊（无数据）时整段空跑；PJAX 换页由 main 的 mountPage 重跑。
 */
export function initTimeMachine(): void {
    const pills = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-sg-time-pill]'));
    if (!pills.length) return;
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    for (const pill of pills) {
        pill.addEventListener('click', () => {
            const year = pill.dataset.sgTimePill ?? '';
            for (const p of pills) p.setAttribute('aria-pressed', p === pill ? 'true' : 'false');
            const target = document.querySelector<HTMLElement>(`[data-sg-time-year="${year}"]`);
            if (!target) return;
            const nav = document.querySelector<HTMLElement>('.sg-nav');
            const navH = nav ? nav.offsetHeight : 72;
            const top = target.getBoundingClientRect().top + window.scrollY - navH - 16;
            window.scrollTo({ top: Math.max(0, top), behavior: reduced ? 'auto' : 'smooth' });
        });
    }
}
