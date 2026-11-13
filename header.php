<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
sg_context($this);
$sgSplash = sg_opt('sgSplash', 'on') === 'on';

/* ---------------- 背景 ----------------
   2026-11-13 第二十轮：多图轮播下线，只剩一张壁纸 + 单页覆盖。
   轮播每 9 秒换一次会把阅读打断，而壁纸本来就压在网格与卡片之下，
   单图配遮罩 / 模糊已经够用；少一个定时器也少一处抖动来源。
   1. 壁纸：后台「背景壁纸」一张；
   2. 单篇 / 单页可用自定义字段 sgBg 单独指定一张，覆盖全站设置；
   3. 遮罩与模糊是 html 上的 data 属性，CSS 按档位取值，不写死颜色。 */
$sgBgSrc = trim((string) sg_opt('sgBg', ''));
/* 第二十三轮：去掉了「老版本 sgBgImages 兜底」。
   站长明确要求「不填就不显示旧图」—— 旧值留在 options 里也一律不读，
   留空就走下面内置的渐变壁纸。 */
try {
    $sgOwnBg = trim((string) ($this->fields->sgBg ?? ''));
    if ($sgOwnBg !== '') {
        $sgBgSrc = $sgOwnBg;
    }
} catch (\Throwable $e) {
    /* 归档页没有自定义字段，忽略 */
}
$sgBgSrc = sg_bg_url($sgBgSrc);
if ($sgBgSrc === '') {
    $sgBgSrc = sg_theme_url('dist/img/bg-1.svg');
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
<?php echo sg_asset(); ?>
</head>
<body class="sg-body">
<?php if ($sgSplash) : ?>
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<?php endif; ?>
<div class="sg-bg" aria-hidden="true">
  <div class="sg-bg-images" data-sg-bg>
    <div class="sg-bg-frame is-active"><img src="<?php echo sg_e($sgBgSrc); ?>" alt=""></div>
  </div>
  <div class="sg-bg-veil"></div>
  <div class="sg-gradient" data-sg-gradient></div>
  <div class="sg-glow sg-glow-a"></div>
  <div class="sg-glow sg-glow-b"></div>
</div>
<canvas class="sg-effects" data-sg-effects aria-hidden="true"></canvas>
<div class="sg-danmaku" data-sg-danmaku aria-hidden="true"></div>
<?php $this->need('partials/nav.php'); ?>
