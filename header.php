<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
sg_context($this);
$sgSplash = sg_opt('sgSplash', 'on') === 'on';

/* 日间与夜间壁纸各自轮播；单页 sgBg 覆盖两组，遮罩/模糊共用。 */
$sgOwnBg = '';
try { $sgOwnBg = trim((string) ($this->fields->sgBg ?? '')); } catch (\Throwable $e) { /* 归档页无自定义字段 */ }
$sgWallpapers = sg_wallpapers($sgOwnBg);

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
<title><?php
    /* 「杂谈」「说说」两个旧页现在渲染的是「碎语」流（§21），
       标题标签跟着统一，别让浏览器标签页还写着旧名字。 */
    $sgDocSlug = sg_current_slug($this);
    if (in_array($sgDocSlug, ['chatter', 'moments'], true)) {
        echo '碎语 - ';
    } elseif ($this->is('post') || $this->is('page') || $this->is('category') || $this->is('tag') || $this->is('search')) {
        $this->archiveTitle('', '', ' - ');
    }
    $this->options->title();
    ?></title>
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
<?php /* 字体 preload 必须排在样式表前面：浏览器是从上往下预扫描的，
        放后面等于没提前，还会跟 CSS 抢同一个连接槽。 */ ?>
<?php echo sg_asset(); ?>
</head>
<body class="sg-body">
<a class="sg-skip" href="#sg-main">跳到正文</a>
<?php if ($sgSplash) : ?>
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<?php endif; ?>
<div class="sg-bg" aria-hidden="true">
  <div class="sg-bg-images" data-sg-bg data-interval="<?php echo max(0, min(120, (int) sg_opt('sgBgInterval', '9'))) * 1000; ?>">
    <?php foreach ($sgWallpapers as $sgMode => $sgImages): ?>
    <div class="sg-bg-mode" data-bg-mode="<?php echo $sgMode; ?>">
      <?php foreach ($sgImages as $sgIndex => $sgImage): ?>
      <div class="sg-bg-frame<?php echo $sgIndex === 0 ? ' is-active' : ''; ?>"><img src="<?php echo sg_e($sgImage); ?>" alt="" decoding="async" <?php echo $sgIndex === 0 ? 'fetchpriority="high"' : 'loading="lazy"'; ?>></div>
      <?php endforeach; ?>
    </div>
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
