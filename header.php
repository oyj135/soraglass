<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
sg_context($this);
$sgSplash = sg_opt('sgSplash', 'on') === 'on';

/* 日间与夜间壁纸各自轮播，模糊档位共用。
   2026-11-20：壁纸与内容之间的背景遮罩整层移除（用户要求内容直接叠在壁纸上），
   `sgBgVeil` 设置项与 `.sg-bg-veil` 节点一并下线；旧值留在库里不再读取。 */
$sgWallpapers = sg_wallpapers();

$sgBlur = (string) sg_opt('sgBgBlur', 'none');
if (!in_array($sgBlur, ['none', 'soft', 'strong'], true)) {
    $sgBlur = 'none';
}
?>
<!DOCTYPE html>
<html lang="zh-CN" data-sg-blur="<?php echo sg_e($sgBlur); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php
    /* 「杂谈」「说说」两个旧页现在渲染的是「碎语」流（1），
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
<?php echo sg_asset_missing_notice(); ?>
<!-- 页面切换进度条：整站常驻节点，PJAX 只替换 #sg-main，不会被带走 -->
<div class="sg-progress" aria-hidden="true"><span class="sg-progress-bar" data-sg-progress-bar></span></div>
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
  <div class="sg-gradient" data-sg-gradient></div>
  <div class="sg-glow sg-glow-a"></div>
  <div class="sg-glow sg-glow-b"></div>
</div>
<canvas class="sg-effects" data-sg-effects aria-hidden="true"></canvas>
<div class="sg-danmaku" data-sg-danmaku aria-hidden="true"></div>
<?php $this->need('partials/nav.php'); ?>
