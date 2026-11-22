/**
 * 时光机年份筛选（2026-11-22，page-time.php 专用）：
 *   [data-sg-time-pill]   年份胶囊：点击筛选对应 [data-sg-time-year] 分区；
 *                         再次点击已选中的胶囊恢复显示全部年份。
 *   [data-sg-time-year]   年份分区，hidden 切换即可，无需动画依赖。
 * 页面无胶囊（无数据）时整段空跑；PJAX 换页由 lifecycle 自动清理。
 */
export function initTimeMachine(): void {
    const pills = Array.from(document.querySelectorAll<HTMLButtonElement>('[data-sg-time-pill]'));
    const sections = Array.from(document.querySelectorAll<HTMLElement>('[data-sg-time-year]'));
    if (!pills.length || !sections.length) return;

    const apply = (): void => {
        const active = pills.find((p) => p.getAttribute('aria-pressed') === 'true');
        const year = active?.dataset.sgTimePill ?? '';
        for (const section of sections) {
            section.hidden = year !== '' && section.dataset.sgTimeYear !== year;
        }
    };

    for (const pill of pills) {
        pill.addEventListener('click', () => {
            const wasActive = pill.getAttribute('aria-pressed') === 'true';
            for (const p of pills) p.setAttribute('aria-pressed', 'false');
            pill.setAttribute('aria-pressed', wasActive ? 'false' : 'true');
            apply();
        });
    }
    apply();
}
