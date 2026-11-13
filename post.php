<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgCats = sg_categories($this);
$sgTags = sg_tag_chips($this);
$sgLead = sg_field($this, 'description', '');

/* 「返回上一级」按来源分流（2026-11-13）：
   碎语文章 → 碎语页（chatter），其余文章 → 知识档案页（timeline），与顶部导航一致。
   目标独立页没建时 sg_page_url() 会回落站点根，此时再退到所属分类页、最后首页。 */
$sgIsNote = sg_is_note((int) $this->cid);
$sgBack = $sgIsNote ? sg_page_url('chatter') : sg_page_url('timeline');
$sgBackLabel = $sgIsNote ? '返回碎语' : '返回知识档案';

/* 目标页不可用（没建页面 / permalink 算不出来，形如 "/#"）时，退到所属分类页、最后首页 */
if (
    $sgBack === ''
    || strpos($sgBack, '#') !== false
    || rtrim($sgBack, '/') === rtrim($sgHome, '/')
) {
    $sgBack = $sgHome;
    $sgBackLabel = '返回首页';
    foreach ($sgCats as $sgCrumb) {
        if (!empty($sgCrumb['permalink'])) {
            $sgBack = (string) $sgCrumb['permalink'];
            $sgBackLabel = '返回' . $sgCrumb['name'];
            break;
        }
    }
}
?>
<main class="sg-main sg-article-layout" data-sg-page="post" data-sg-origin="<?php echo $sgIsNote ? 'note' : 'article'; ?>">
  <article class="sg-glass sg-article">
    <div class="sg-cover">
      <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="<?php echo sg_e((string) $this->title); ?>">
    </div>
    <div class="sg-article-body">
      <a class="sg-back" href="<?php echo sg_e($sgBack); ?>"><?php echo sg_icon('arrow-left'); ?><?php echo sg_e($sgBackLabel); ?></a>
      <header class="sg-article-head">
        <h1><?php $this->title(); ?></h1>
        <div class="sg-meta">
          <span class="sg-meta-chip"><?php echo sg_icon('clock'); ?>写作时间：<?php $this->date('Y-m-d H:i:s'); ?></span>
          <?php foreach ($sgCats as $sgCat) : ?>
          <a class="sg-meta-chip is-pink" href="<?php echo sg_e($sgCat['permalink']); ?>"><?php echo sg_icon('tag'); ?><?php echo sg_e($sgCat['name']); ?></a>
          <?php endforeach; ?>
          <?php if ($sgTags !== '') : ?><?php echo $sgTags; ?><?php endif; ?>
        </div>
      </header>
      <?php if ($sgLead !== '') : ?><p class="sg-lead"><?php echo sg_e($sgLead); ?></p><?php endif; ?>
      <div class="sg-prose" data-sg-article><?php $this->content(); ?></div>
      <nav class="sg-post-nav" aria-label="上下篇">
        <span><?php $this->thePrev('%s', '已经是第一篇'); ?></span>
        <span><?php $this->theNext('%s', '已经是最后一篇'); ?></span>
      </nav>
    </div>
  </article>
  <aside class="sg-aside">
    <?php $this->need('partials/author-card.php'); ?>
    <?php $this->need('sidebar.php'); ?>
    <?php $this->need('partials/toc.php'); ?>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
