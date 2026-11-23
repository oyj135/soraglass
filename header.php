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

/* 换页加载遮罩（2026-11-21，对齐源站 heo preloader）：圆头像 + 绿点，
   只在软换页（PJAX）时由 loading.ts 揭开/盖上，首屏不参与避免与开屏叠加。 */
$sgLoadingAvatar = sg_media((string) sg_opt('sgAvatarUrl', ''), sg_theme_url('dist/img/avatar.svg'));

/* 网站图标（2026-11-22 第五十三轮）：后台「网站图标」可填 https/http 外链
   （如对象存储 / CDN），也支持 usr/uploads/… 与主题内相对路径；留空回退自带 SVG。 */
$sgFavicon = sg_bg_url((string) sg_opt('sgFavicon', ''));
if ($sgFavicon === '') {
    $sgFavicon = sg_theme_url('dist/favicon.svg');
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
    /* 岁候：24 个 ?t= 与 12 个 ?g= 各自需要唯一标题，模板在 header 之后改不了 <title>，
       所以由 page-season.php 先算好放进 SG_DOC_TITLE。 */
    $sgDocOverride = isset($GLOBALS['SG_DOC_TITLE']) ? (string) $GLOBALS['SG_DOC_TITLE'] : '';
    if ($sgDocOverride !== '') {
        echo sg_e($sgDocOverride) . ' - ';
    } elseif (in_array($sgDocSlug, ['chatter', 'moments'], true)) {
        echo '碎语 - ';
    } elseif ($this->is('post') || $this->is('page') || $this->is('category') || $this->is('tag')) {
        $this->archiveTitle('', '', ' - ');
    }
    $this->options->title();
    ?></title>
<?php /* canonical：只有岁候页会设置（?d= 指向所属 ?t=，?t=/?g= 指向自身），其余页面维持现状不输出 */ ?>
<?php if (!empty($GLOBALS['SG_CANONICAL'])) : ?>
<link rel="canonical" href="<?php echo sg_e((string) $GLOBALS['SG_CANONICAL']); ?>">
<?php endif; ?>
<link rel="icon" href="<?php echo sg_e($sgFavicon); ?>">
<?php /* 阿里 iconfont 多色语义图标（对齐源站 fomal.cc）：4 个 async JS 向 body 首部注入
        隐藏 <symbol>，每枚内嵌独立品牌色；页面用 <use href="#icon-*"> 引用（见 icons.php）。
        symbol 常驻 DOM，PJAX 只换 #sg-main 不受影响；CDN 不可达时图标位留白但布局不塌。 */ ?>
<link rel="dns-prefetch" href="https://at.alicdn.com">
<script async src="https://at.alicdn.com/t/c/font_3586335_hsivh70x0fm.js"></script>
<script async src="https://at.alicdn.com/t/c/font_3636804_glcijzcpx7f.js"></script>
<script async src="https://at.alicdn.com/t/c/font_3612150_gkavascgf3n.js"></script>
<script async src="https://at.alicdn.com/t/c/font_5239918_ta23cg9gek.js"></script>
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
<?php
/* Typecho 核心的 header() 对 single 会输出一条不带查询串的 canonical；
   岁候页需要 ?t= / ?g= 各自指向自身（§6.3），所以这里缓冲一次、去掉核心那条，
   由上面第 50 行的 canonical 接管；其余页面不设置则原样输出，行为不变。 */
ob_start();
$this->header();
$sgHeadHtml = (string) ob_get_clean();
if (!empty($GLOBALS['SG_CANONICAL'])) {
    $sgHeadHtml = (string) preg_replace('#<link rel="canonical"[^>]*>\s*#i', '', $sgHeadHtml);
}
echo $sgHeadHtml;
?>
<?php echo sg_runtime_config(); ?>
<?php echo sg_asset(); ?>
</head>
<body class="sg-body">
<?php echo sg_asset_missing_notice(); ?>
<?php /* 自绘真彩色图标（源站 iconfont 库无贴合 symbol 的 3 枚：精选星/关于我/定位）；
        其余 14 枚语义图标由 head 里的 iconfont CDN 注入，此处不重复 */ ?>
<?php echo sg_icon_sprite_defs(); ?>
<a class="sg-skip" href="#sg-main">跳到正文</a>
<?php if ($sgSplash) : ?>
<div class="sg-splash" aria-hidden="true"><span class="sg-splash-mark">空 璃</span></div>
<?php endif; ?>
<div class="sg-loading" id="sg-loading" data-sg-loading aria-hidden="true">
  <img class="sg-loading-img" src="<?php echo sg_e($sgLoadingAvatar); ?>" alt="" decoding="async">
  <span class="sg-loading-dot"></span>
</div>
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
