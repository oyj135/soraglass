<?php
/**
 * 首页春节倒计时首帧。版式对齐源站 aside-calendar.js 的 renderCountdown()：
 *   左栏 30%（距离春节 → 天数 → 目标日期，整列居中）+ 右侧三条进度条
 *   （百分比嵌条内左侧、还剩天数嵌条内右侧）。百分比两位小数与源站 toFixed(2) 一致。
 * 首帧数字来自统一日期兜底表；前端农历算法寻找下一目标并跨日更新。
 */
$sgCdData = sg_sidebar_date();
$sgCdTarget = $sgCdData['target']['date'] ?? '';
$sgCdDays = $sgCdData['target']['days'] ?? null;
$sgCdSub = $sgCdTarget === '' ? '日期待更新' : ($sgCdDays === 0 ? '就是今天' : $sgCdTarget);
?>
<section class="sg-hcard sg-hcd" aria-label="春节倒计时" data-sg-countdown data-sg-cd-target="<?php echo sg_e($sgCdTarget); ?>">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('star'); ?>春节倒计时</span>
  </div>
  <div class="sg-hcd-main">
    <div class="sg-hcd-left">
      <span class="sg-hcd-label">距离春节</span>
      <span class="sg-hcd-num" data-sg-cd-days><?php echo $sgCdDays === null ? '—' : (int) $sgCdDays; ?></span>
      <span class="sg-hcd-date" data-sg-cd-date><?php echo sg_e($sgCdSub); ?></span>
    </div>
    <div class="sg-hcd-rows">
      <?php foreach (['本年', '本月', '本周'] as $sgCdIndex => $sgCdLabel) : $sgCdProgress = $sgCdData['progress'][$sgCdIndex]; $sgCdPercent = number_format((float) $sgCdProgress['percent'], 2, '.', ''); ?>
      <div class="sg-hcd-row"><span class="sg-hcd-rlabel"><?php echo $sgCdLabel; ?></span><span class="sg-hcd-bar"><i style="width:<?php echo $sgCdPercent; ?>%"></i><em><?php echo $sgCdPercent; ?>%</em><b data-sg-cd-remaining>还剩 <?php echo $sgCdProgress['remaining']; ?> 天</b></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
