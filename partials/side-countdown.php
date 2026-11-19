<?php
/**
 * 春节首帧来自统一日期兜底表；前端农历算法寻找下一目标并跨日更新。
 */
$sgCdData = sg_sidebar_date();
$sgCdTarget = $sgCdData['target']['date'] ?? '';
?>
<section class="sg-hcard sg-hcd" aria-label="春节倒计时" data-sg-countdown data-sg-cd-target="<?php echo sg_e($sgCdTarget); ?>">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('star'); ?>春节倒计时</span>
    <span class="sg-hcd-date" data-sg-cd-date><?php echo $sgCdTarget !== '' ? sg_e($sgCdTarget) : '日期待更新'; ?></span>
  </div>
  <div class="sg-hcd-main">
    <div class="sg-hcd-left">
      <span class="sg-hcd-label">距离春节</span>
      <span class="sg-hcd-num" data-sg-cd-days><?php echo $sgCdData['target']['days'] ?? '—'; ?></span>
      <span class="sg-hcd-unit">天</span>
    </div>
    <div class="sg-hcd-rows">
      <?php foreach (['本年', '本月', '本周'] as $sgCdIndex => $sgCdLabel) : $sgCdProgress = $sgCdData['progress'][$sgCdIndex]; ?>
      <div class="sg-hcd-row"><span class="sg-hcd-rlabel"><?php echo $sgCdLabel; ?></span><span class="sg-hcd-bar"><i style="width:<?php echo $sgCdProgress['percent']; ?>%"></i></span><em><?php echo $sgCdProgress['percent']; ?>%</em><span class="sg-hcd-remaining" data-sg-cd-remaining>剩 <?php echo $sgCdProgress['remaining']; ?> 天</span></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
