<?php
$sgWelcomeCfg = sg_sidebar_cfg();
if ($sgWelcomeCfg['mode'] === 'off') return;
// 访客模式首屏只占位：定位结果由 sidebar-data.ts 拼成源站那句「来自 省 市 区 的访客…」。
$sgWelcomeVisitor = $sgWelcomeCfg['mode'] === 'visitor';
$sgWelcomeSite = !$sgWelcomeVisitor && $sgWelcomeCfg['site'];
?>
<section class="sg-hcard sg-hwelcome<?php if ($sgWelcomeVisitor) echo ' sg-hwelcome--visitor'; ?>" data-sg-welcome aria-label="欢迎来访">
  <div class="sg-hcard-head"><span class="sg-hcard-title"><?php echo sg_icon_color('star'); ?>欢迎来访</span></div>
  <p class="sg-hwelcome-location" data-sg-welcome-location><?php
    if ($sgWelcomeVisitor) echo '正在定位…';
    elseif ($sgWelcomeSite) echo '来自' . sg_e($sgWelcomeCfg['site']['label']) . '的一声问候';
    else echo '来自远方的朋友，欢迎来访';
  ?></p>
  <p class="sg-hwelcome-meta" data-sg-welcome-meta<?php if ($sgWelcomeVisitor) echo ' hidden'; ?>><?php
    echo $sgWelcomeVisitor ? '' : ($sgWelcomeSite ? '站长所在城市' : '愿你在这里找到喜欢的文字');
  ?></p>
</section>
