<?php
/**
 * 页脚仪表盘：实时时钟 + 站点运行时长 + 徽章 + 备案号。
 * 时钟与时长由 src/scripts/modules/dashboard.ts 驱动。
 */
$sgBadges = sg_json('sgFooterBadges', 'footerBadges', []);
$sgUp = sg_uptime();
$sgIcp = sg_opt('sgIcp', '');
?>
<div class="sg-glass sg-dashboard">
  <div class="sg-dash-clock" data-sg-clock>00:00:00</div>
  <div class="sg-dash-body">
    <div class="sg-dash-status">
      <i class="sg-dash-dot" aria-hidden="true"></i>
      <span>系统已稳定运行：<b data-sg-uptime data-since="<?php echo (int) $sgUp['since']; ?>"><?php echo (int) $sgUp['days']; ?> 天 <?php echo (int) $sgUp['hours']; ?> 小时</b></span>
    </div>
    <div class="sg-dash-badges">
      <?php foreach ($sgBadges as $sgBadge) : ?>
      <?php if (!is_array($sgBadge)) { continue; } ?>
      <span class="sg-badge"><i style="background:<?php echo sg_value($sgBadge, 'color', '#6366f1'); ?>"></i><?php echo sg_value($sgBadge, 'name', ''); ?></span>
      <?php endforeach; ?>
      <?php if ($sgIcp !== '') : ?><span class="sg-dash-icp"><?php echo sg_e($sgIcp); ?></span><?php endif; ?>
    </div>
  </div>
</div>
