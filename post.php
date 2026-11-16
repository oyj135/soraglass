<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgCats = sg_categories($this);
$sgTags = sg_tag_chips($this);
$sgLead = sg_field($this, 'description', '');
$sgArticleTitle = trim((string) $this->title);

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

/* 正文只取一次：字数统计、阅读时长与正文输出共用同一份过滤结果。 */
ob_start();
$this->content();
$sgContent = (string) ob_get_clean();
$sgRead = sg_reading_stats($sgContent);

$sgUpdated = '';
try {
    if (!empty($this->modified) && (int) $this->modified !== (int) $this->created) {
        $sgUpdated = $this->date('Y-m-d', (int) $this->modified);
    }
} catch (\Throwable $e) {
    $sgUpdated = '';
}

$sgPrev = sg_adjacent_post($this, 'prev');
$sgNext = sg_adjacent_post($this, 'next');
?>
<main id="sg-main" class="sg-main sg-article-layout" data-sg-page="post" data-sg-origin="<?php echo $sgIsNote ? 'note' : 'article'; ?>">
  <article class="sg-glass sg-article sg-reading-surface">
    <div class="sg-cover">
      <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="<?php echo sg_e((string) $this->title); ?>">
    </div>
    <div class="sg-article-body">
      <a class="sg-back" href="<?php echo sg_e($sgBack); ?>"><?php echo sg_icon('arrow-left'); ?><?php echo sg_e($sgBackLabel); ?></a>
      <header class="sg-article-head">
        <h1><?php echo sg_e($sgArticleTitle !== '' ? $sgArticleTitle : '未命名文章'); ?></h1>
        <div class="sg-meta">
          <time class="sg-meta-chip" datetime="<?php $this->date('c'); ?>"><?php echo sg_icon('clock'); ?><?php $this->date('Y-m-d H:i'); ?></time>
          <?php if ($sgUpdated !== '') : ?>
          <span class="sg-meta-chip"><?php echo sg_icon('history'); ?>更新于 <?php echo sg_e($sgUpdated); ?></span>
          <?php endif; ?>
          <?php foreach ($sgCats as $sgCat) : ?>
          <a class="sg-meta-chip" href="<?php echo sg_e($sgCat['permalink']); ?>"><?php echo sg_icon('folder'); ?><?php echo sg_e($sgCat['name']); ?></a>
          <?php endforeach; ?>
          <?php if ($sgRead['chars'] > 0) : ?>
          <span class="sg-meta-chip"><?php echo sg_icon('history'); ?><?php echo sg_e($sgRead['text']); ?></span>
          <span class="sg-meta-chip"><?php echo sg_icon('clock'); ?><?php echo (int) $sgRead['minutes']; ?> 分钟阅读</span>
          <?php endif; ?>
          <?php if ($sgTags !== '') : ?><?php echo $sgTags; ?><?php endif; ?>
        </div>
      </header>
      <?php if ($sgLead !== '') : ?><p class="sg-lead"><?php echo sg_e($sgLead); ?></p><?php endif; ?>
      <div class="sg-prose" data-sg-article><?php echo $sgContent; ?></div>

      <?php /* P1-12：分享条——系统分享 + 复制链接，不引第三方 SDK；
         浏览器不支持 navigator.share 时由 JS 隐藏系统分享按钮 */ ?>
      <div class="sg-share" role="group" aria-label="分享这篇文章">
        <button class="sg-btn sg-btn--ghost sg-share-btn" type="button" data-sg-share><?php echo sg_icon('share'); ?>分享</button>
        <button class="sg-btn sg-btn--ghost" type="button" data-sg-copy-link><?php echo sg_icon('link'); ?>复制链接</button>
      </div>

      <?php if ($sgPrev || $sgNext) : ?>
      <nav class="sg-post-nav" aria-label="上下篇">
        <?php if ($sgPrev) : ?>
        <a class="sg-post-nav-item is-prev<?php echo $sgNext ? '' : ' is-full'; ?>" href="<?php echo sg_e($sgPrev['url']); ?>">
          <img src="<?php echo sg_e($sgPrev['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
          <span class="sg-post-nav-info">
            <em>上一篇</em>
            <b><?php echo sg_e($sgPrev['title'] !== '' ? $sgPrev['title'] : '未命名文章'); ?></b>
          </span>
        </a>
        <?php endif; ?>
        <?php if ($sgNext) : ?>
        <a class="sg-post-nav-item is-next<?php echo $sgPrev ? '' : ' is-full'; ?>" href="<?php echo sg_e($sgNext['url']); ?>">
          <img src="<?php echo sg_e($sgNext['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
          <span class="sg-post-nav-info">
            <em>下一篇</em>
            <b><?php echo sg_e($sgNext['title'] !== '' ? $sgNext['title'] : '未命名文章'); ?></b>
          </span>
        </a>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </article>
  <aside class="sg-aside">
    <?php $this->need('partials/author-card.php'); ?>
    <div class="sg-aside-sticky">
      <?php $this->need('partials/toc.php'); ?>
      <?php $this->need('sidebar.php'); ?>
    </div>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
