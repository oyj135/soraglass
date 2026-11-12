<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
sg_context($this);
$sgSplash = sg_opt('sgSplash', 'on') === 'on';

/* ---------------- 背景（2026-11-12 第四轮新增可配置项） ----------------
   1. 壁纸：多行 = 多张，按后台「背景轮播间隔」轮换；
   2. 单篇 / 单页可用自定义字段 sgBg 单独指定一张，覆盖全站设置；
   3. 遮罩与模糊是 html 上的 data 属性，CSS 按档位取值，不写死颜色。 */
$sgBgList = array_values(array_filter(array_map('trim', sg_lines('sgBgImages', [
    sg_theme_url('dist/img/bg-1.svg'),
    sg_theme_url('dist/img/bg-2.svg'),
]))));
if (!$sgBgList) {
    $sgBgList = [sg_theme_url('dist/img/bg-1.svg')];
}
try {
    $sgOwnBg = trim((string) ($this->fields->sgBg ?? ''));
    if ($sgOwnBg !== '') {
        $sgBgList = [$sgOwnBg];
    }
} catch (\Throwable $e) {
    /* 归档页没有自定义字段，忽略 */
}
$sgBgList = array_values(array_filter(array_map('sg_bg_url', $sgBgList)));

/* 默认 9 秒：与上一版写死的 BG_INTERVAL 一致，老站升级后观感不变；
   填 0 才是关闭。*/
$sgBgInterval = (int) sg_opt('sgBgInterval', '9');
if ($sgBgInterval < 0) {
    $sgBgInterval = 0;
}
if (count($sgBgList) < 2) {
    $sgBgInterval = 0;
}

$sgVeil = (string) sg_opt('sgBgVeil', 'mid');
if (!in_array($sgVeil, ['soft', 'mid', 'strong'], true)) {
    $sgVeil = 'mid';
}
$sgBlur = (string) sg_opt('sgBgBlur', 'none');
if (!in_array($sgBlur, ['none', 'soft', 'strong'], true)) {
    $sgBlur = 'none';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-sg-veil="<?php echo sg_e($sgVeil); ?>" data-sg-blur="<?php echo sg_e($sgBlur); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php if ($this->is('post') || $this->is('page') || $this->is('category') || $this->is('tag') || $this->is('search')) { $this->archiveTitle('', '', ' - '); } ?><?php $this->options->title(); ?></title>
<link rel="icon" href="<?php echo sg_e(sg_theme_url('dist/favicon.svg')); ?>">
<script>
(function () {
  var d = document.documentElement;
  try {
    if (localStorage.getItem('sg-theme') !== 'light') { d.classList.add('dark'); }
  } catch (e) { d.classList.add('dark'); }
  /* 对齐源站 SplashScreen：只在会话首次进入时挂开屏，站内切页立刻显形。
     这段必须同步执行——放到异步 JS 里就会先黑一帧。 */
  try {
    if (<?php echo $sgSplash ? 'true' : 'false'; ?> && sessionStorage.getItem('sg-splash-seen') !== '1') {
      d.classList.add('sg-splash-pending');
    } else {
      d.classList.add('sg-ready');
    }
  } catch (e) {
    d.classList.add('sg-ready');
  }
})();
</script>
<?php $this->header(); ?>
<?php echo sg_runtime_config(); ?>
<?php echo sg_asset(); ?>
</head>
<body class="sg-body">
<?php if ($sgSplash) : ?>
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<?php endif; ?>
<div class="sg-bg" aria-hidden="true">
  <div class="sg-bg-images" data-sg-bg data-sg-bg-interval="<?php echo (int) $sgBgInterval; ?>">
    <?php foreach ($sgBgList as $sgBgIndex => $sgBgSrc) : ?>
    <div class="sg-bg-frame<?php echo $sgBgIndex === 0 ? ' is-active' : ''; ?>"><img src="<?php echo sg_e($sgBgSrc); ?>" alt=""<?php echo $sgBgIndex === 0 ? '' : ' loading="lazy"'; ?>></div>
    <?php endforeach; ?>
  </div>
  <div class="sg-bg-veil"></div>
  <div class="sg-gradient" data-sg-gradient></div>
  <div class="sg-glow sg-glow-a"></div>
  <div class="sg-glow sg-glow-b"></div>
</div>
<canvas class="sg-effects" data-sg-effects aria-hidden="true"></canvas>
<div class="sg-danmaku" data-sg-danmaku aria-hidden="true"></div>
<?php $this->need('partials/nav.php'); ?>
