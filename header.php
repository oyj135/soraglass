<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
sg_context($this);
$sgFirstBg = sg_lines('sgBgImages', [sg_theme_url('dist/img/bg-1.svg')])[0];
$sgSplash = sg_opt('sgSplash', 'on') === 'on';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php if ($this->is('post') || $this->is('page') || $this->is('category') || $this->is('tag') || $this->is('search')) { $this->archiveTitle('', '', ' - '); } ?><?php $this->options->title(); ?></title>
<link rel="icon" href="<?php echo sg_e(sg_theme_url('dist/favicon.svg')); ?>">
<script>(function(){try{if(localStorage.getItem('sg-theme')!=='light'){document.documentElement.classList.add('dark');}}catch(e){document.documentElement.classList.add('dark');}})();</script>
<?php $this->header(); ?>
<?php echo sg_runtime_config(); ?>
<?php echo sg_asset(); ?>
</head>
<body class="sg-body">
<?php if ($sgSplash) : ?>
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<?php endif; ?>
<div class="sg-bg" aria-hidden="true">
  <div class="sg-bg-images" data-sg-bg>
    <div class="sg-bg-frame is-active"><img src="<?php echo sg_e($sgFirstBg); ?>" alt=""></div>
  </div>
  <div class="sg-bg-veil"></div>
  <div class="sg-gradient" data-sg-gradient></div>
  <div class="sg-glow sg-glow-a"></div>
  <div class="sg-glow sg-glow-b"></div>
</div>
<canvas class="sg-effects" data-sg-effects aria-hidden="true"></canvas>
<div class="sg-danmaku" data-sg-danmaku aria-hidden="true"></div>
<?php $this->need('partials/nav.php'); ?>
