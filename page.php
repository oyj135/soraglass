<?php
/**
 * 通用独立页兜底模板。
 * 未提供 page-{slug}.php 的独立页面会走到这里：封面 + 正文 + 目录。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgLead = sg_field($this, 'description', '');
?>
<main class="sg-main sg-article-layout" data-sg-page="page">
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
    <?php $this->need('partials/toc.php'); ?>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
