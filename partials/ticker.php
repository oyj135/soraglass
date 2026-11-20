<?php
/**
 * 顶部公告跑马灯（2026-11-20）—— 替代原侧栏公告卡。
 *
 * 内容仍取后台「顶部公告条」sgNotice，一行一条，纯文本输出（emoji 原样保留）。
 * 结构：固定标签 + 视窗 + 轨道；轨道里内容输出两份，第二份 aria-hidden，
 * 动画整体左移一份宽度即为无缝循环。速度由 ticker.ts 按内容宽度换算，
 * 没有 JS 时退回 CSS 默认 26s。
 *
 * 放在 .sg-article-layout 的第一个子节点：≥1024px 是 flex wrap 布局，
 * flex: 0 0 100% 让它独占一行、横跨正文与侧栏。
 */
$sgTickerLines = [];
foreach (preg_split('/\R/u', (string) sg_opt('sgNotice', '')) ?: [] as $sgTickerLine) {
    $sgTickerLine = trim($sgTickerLine);
    if ($sgTickerLine === '') {
        continue;
    }
    $sgTickerLines[] = mb_substr($sgTickerLine, 0, 120);
    if (count($sgTickerLines) >= 8) {
        break;
    }
}
if (!$sgTickerLines) {
    return;
}
$sgTickerGroup = static function (bool $hidden) use ($sgTickerLines): void {
    echo '<div class="sg-ticker-group"' . ($hidden ? ' aria-hidden="true"' : '') . '>';
    foreach ($sgTickerLines as $sgTickerLine) {
        echo '<span class="sg-ticker-item">' . sg_e($sgTickerLine) . '</span>';
        echo '<span class="sg-ticker-dot" aria-hidden="true">·</span>';
    }
    echo '</div>';
};
?>
<div class="sg-ticker" data-sg-ticker role="region" aria-label="站内公告">
  <span class="sg-ticker-badge" aria-hidden="true">公告</span>
  <div class="sg-ticker-window">
    <div class="sg-ticker-track">
      <?php $sgTickerGroup(false); ?>
      <?php $sgTickerGroup(true); ?>
    </div>
  </div>
</div>
