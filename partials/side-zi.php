<?php
/**
 * 首页右栏 · 字海挂件
 *
 * 一行「今日一字」（与字海页同源、确定性选取，无 JS 也直出），点击进该字卡。
 * 不发光、不轮询：与旧岁候挂件（side-season.php，2026-11-24 退役）同一克制等级。
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
<a class="sg-glass sg-hcard sg-hzi" href="<?php echo sg_e(sg_zi_url(['c' => $sgZToday['char']])); ?>">
  <span class="sg-hzi-seal" aria-hidden="true">字</span>
  <span class="sg-hzi-text">
    <b>今日一字 · <?php echo sg_e($sgZToday['char']); ?></b>
    <span>全站共 <?php echo $sgZFreq; ?> 次<?php if ($sgZFirstYear > 0) : ?> · <?php echo $sgZFirstYear; ?> 年首见<?php endif; ?></span>
  </span>
  <span class="sg-hzi-more">检字<?php echo sg_icon_color('arrow-up-right'); ?></span>
</a>
