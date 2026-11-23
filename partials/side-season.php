<?php
/**
 * 首页右栏 · 岁候挂件（评审稿 §5 F7）
 *
 * 一行结论，与岁候页同源（sg_season_line 的内部读数），点击进入当日所在节气。
 * 不发光、不轮询：全站已有 5 处在跑 12s 三色轮询，挂件只做入口。
 */
$sgSideNow = sg_season_now();
if (!$sgSideNow) {
    return;
}
?>
<a class="sg-glass sg-hcard sg-hseason" href="<?php echo sg_e(sg_season_url(['t' => $sgSideNow['termSlug'], 'y' => $sgSideNow['year']])); ?>">
  <span class="sg-hseason-icon"><?php echo sg_icon_color('calendar'); ?></span>
  <span class="sg-hseason-text">
    <b><?php echo sg_e($sgSideNow['termName']); ?></b>
    <span><?php echo sg_e($sgSideNow['gongName'] . '宫'); ?></span>
  </span>
  <span class="sg-hseason-meta">距<?php echo sg_e($sgSideNow['nextName']); ?> <?php echo (int) $sgSideNow['nextDays']; ?> 天</span>
</a>
