<?php
/**
 * 独立页模板（所有页面的默认落点）。
 *
 * 两个职责：
 * 1. 白名单分发：缩略名命中 archives / categories / label 时，直接渲染对应的
 *    列表视图（partials/views/*）。Typecho 只按 contents.template 选模板文件，
 *    page-{slug}.php 不会自动生效；放在这里就不需要去后台逐个选模板。
 * 2. 兜底：其余页面仍按「封面 + 正文 + 目录」渲染。
 *
 * 想关掉分发，在页面自定义字段里加 view=default 即可。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgLead = sg_field($this, 'description', '');

$sgViews = ['archives', 'categories', 'label'];
$sgView = '';

try {
    $sgSlug = (string) $this->slug;
    if ($sgSlug !== '' && in_array($sgSlug, $sgViews, true)) {
        $sgView = $sgSlug;
    }
    if ((string) $this->fields->view === 'default') {
        $sgView = '';
    }
} catch (\Throwable $e) {
    $sgView = '';
}

if ($sgView !== '') {
    $this->need('partials/views/' . $sgView . '.php');
    $this->need('footer.php');
    return;
}
?>
<main id="sg-main" class="sg-main sg-article-layout" data-sg-page="page">
  <article class="sg-glass sg-article">
    <div class="sg-cover">
      <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="<?php echo sg_e((string) $this->title); ?>">
    </div>
    <div class="sg-article-body">
      <a class="sg-back" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>返回上一级</a>
      <header class="sg-article-head">
        <h1><?php $this->title(); ?></h1>
        <div class="sg-meta">
          <span class="sg-meta-chip"><?php echo sg_icon('clock'); ?>更新于 <?php $this->date('Y-m-d H:i:s'); ?></span>
        </div>
      </header>
      <?php if ($sgLead !== '') : ?><p class="sg-lead"><?php echo sg_e($sgLead); ?></p><?php endif; ?>
      <div class="sg-prose" data-sg-article><?php $this->content(); ?></div>
    </div>
  </article>
  <aside class="sg-aside">
    <div class="sg-aside-sticky">
      <?php $this->need('sidebar.php'); ?>
      <?php $this->need('partials/toc.php'); ?>
    </div>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
