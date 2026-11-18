<?php
/**
 * 首页侧栏 · 轻量春节倒计时卡（参考 Fomalhaut card-countdown 的形态）。
 * 轻量版：内置未来数年春节公历日期表，前端只做「今天 → 下一个春节」天数差
 * 与年/月/周进度条，不引入农历换算与节气算法；日期表用完可在 JS 常量里续加。
 * 服务端先渲染首帧（避免首屏空白），JS 挂载后按本地日期刷新。
 */
$sgCdTable = [
    '2027-02-06', '2028-01-26', '2029-02-13', '2030-02-03',
    '2031-01-23', '2032-02-11', '2033-01-31', '2034-02-19',
];
$sgCdNow = new DateTimeImmutable('today');
$sgCdTarget = null;
foreach ($sgCdTable as $sgCdDate) {
    $sgCdDt = new DateTimeImmutable($sgCdDate);
    if ($sgCdDt >= $sgCdNow) { $sgCdTarget = $sgCdDt; break; }
}
if ($sgCdTarget === null) { $sgCdTarget = new DateTimeImmutable('2027-02-06'); }
$sgCdDays = (int) $sgCdNow->diff($sgCdTarget)->days;
$sgCdYearPct = (int) round(((int) $sgCdNow->format('z') + 1) / ((int) $sgCdNow->format('L') ? 366 : 365) * 100);
$sgCdMonthPct = (int) round(((int) $sgCdNow->format('j')) / ((int) $sgCdNow->format('t')) * 100);
$sgCdWeekPct = (int) round(((int) $sgCdNow->format('w')) / 7 * 100);
?>
<section class="sg-hcard sg-hcd" aria-label="春节倒计时" data-sg-countdown data-sg-cd-target="<?php echo $sgCdTarget->format('Y-m-d'); ?>">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('star'); ?>春节倒计时</span>
    <span class="sg-hcd-date" data-sg-cd-date><?php echo $sgCdTarget->format('Y-m-d'); ?></span>
  </div>
  <div class="sg-hcd-main">
    <div class="sg-hcd-left">
      <span class="sg-hcd-label">距离春节</span>
      <span class="sg-hcd-num" data-sg-cd-days><?php echo $sgCdDays; ?></span>
      <span class="sg-hcd-unit">天</span>
    </div>
    <div class="sg-hcd-rows">
      <div class="sg-hcd-row"><span class="sg-hcd-rlabel">本年</span><span class="sg-hcd-bar"><i style="width:<?php echo $sgCdYearPct; ?>%"></i></span><em><?php echo $sgCdYearPct; ?>%</em></div>
      <div class="sg-hcd-row"><span class="sg-hcd-rlabel">本月</span><span class="sg-hcd-bar"><i style="width:<?php echo $sgCdMonthPct; ?>%"></i></span><em><?php echo $sgCdMonthPct; ?>%</em></div>
      <div class="sg-hcd-row"><span class="sg-hcd-rlabel">本周</span><span class="sg-hcd-bar"><i style="width:<?php echo $sgCdWeekPct; ?>%"></i></span><em><?php echo $sgCdWeekPct; ?>%</em></div>
    </div>
  </div>
</section>
