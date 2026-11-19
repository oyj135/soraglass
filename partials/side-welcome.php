<?php
$sgWelcomeCfg = sg_sidebar_cfg();
if ($sgWelcomeCfg['mode'] === 'off') return;
?>
<section class="sg-hcard sg-hwelcome" data-sg-welcome aria-label="欢迎来访">
  <div class="sg-hcard-head"><span class="sg-hcard-title"><?php echo sg_icon_color('star'); ?>欢迎来访</span><span class="sg-hwelcome-greeting" data-sg-welcome-greeting>你好</span></div>
  <p class="sg-hwelcome-location" data-sg-welcome-location><?php echo $sgWelcomeCfg['mode'] === 'site' && $sgWelcomeCfg['site'] ? '来自' . sg_e($sgWelcomeCfg['site']['label']) . '的一声问候' : '来自远方的朋友，欢迎来访'; ?></p>
  <p class="sg-hwelcome-meta" data-sg-welcome-meta>愿你在这里找到喜欢的文字</p>
  <?php if ($sgWelcomeCfg['mode'] === 'visitor') : ?>
  <div class="sg-hwelcome-actions"><button type="button" class="sg-hwelcome-refresh" data-sg-location-refresh>重新获取</button><span role="status" aria-live="polite" data-sg-welcome-feedback></span></div>
  <?php endif; ?>
</section>
