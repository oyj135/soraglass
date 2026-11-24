<?php
/**
 * 首页右栏 · 今日一字挂件（字海降级形态）
 *
 * 一行「今日一字」（lib/zi.php 确定性选取，无 JS 也直出）。2026-11-24 岁轮回归后
 * 字海独立页退役，挂件改为纯展示卡，不再跳转；文章页 ?hl= 回链高亮照常可用。
 * 不发光、不轮询：与旧岁候挂件同一克制等级。
 */
if (!sg_zi_enabled()) {
    return;
}
$sgZToday = sg_zi_today();
if (!$sgZToday) {
    return;
}
$sgZFreq = (int) $sgZToday['freq'];
$sgZFirstYear = (int) date('Y', sg_date_ts((int) ($sgZToday['first']['ts'] ?? 0)));
?>
<div class="sg-glass sg-hcard sg-hzi">
  <span class="sg-hzi-seal" aria-hidden="true">字</span>
  <span class="sg-hzi-text">
    <b>今日一字 · <?php echo sg_e($sgZToday['char']); ?></b>
    <span>全站共 <?php echo $sgZFreq; ?> 次<?php if ($sgZFirstYear > 0) : ?> · <?php echo $sgZFirstYear; ?> 年首见<?php endif; ?></span>
  </span>
  <span class="sg-hzi-tag">字海</span>
</div>
