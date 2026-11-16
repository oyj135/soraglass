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
$sgUpdatedAgo = '';
try {
    if (!empty($this->modified) && (int) $this->modified !== (int) $this->created) {
        $sgUpdated = $this->date('Y-m-d', (int) $this->modified);
        $sgUpdatedAgo = sg_time_ago((int) $this->modified);
    }
} catch (\Throwable $e) {
    $sgUpdated = '';
    $sgUpdatedAgo = '';
}
/* 头图元信息（参考 Fomalhaut post-info 的信息完整度）：
   发表于 / 更新于走相对时间；字数与阅读时长复用正文统计；阅读量是可选自定义字段 views，
   没装计数插件时不显示，绝不伪造数字。 */
$sgPostedAgo = sg_time_ago((int) $this->created);
$sgViews = sg_field($this, 'views', '');
$sgSticky = (int) $this->sticky > 0;

$sgPrev = sg_adjacent_post($this, 'prev');
$sgNext = sg_adjacent_post($this, 'next');
?>
<main id="sg-main" class="sg-main sg-article-layout" data-sg-page="post" data-sg-origin="<?php echo $sgIsNote ? 'note' : 'article'; ?>">
  <article class="sg-glass sg-article sg-reading-surface">
    <?php /* 参考 Fomalhaut：标题 + 元信息压在封面 Banner 上，底部波浪过渡到正文。
           颜色用青瓷 token + 深青遮罩，保持 Soraglass 材质，不照搬源站蓝。 */ ?>
    <header class="sg-post-hero">
      <img class="sg-post-hero-cover" src="<?php echo sg_e(sg_cover($this)); ?>" alt="" aria-hidden="true">
      <span class="sg-post-hero-scrim" aria-hidden="true"></span>
      <div class="sg-post-hero-inner">
        <h1 class="sg-post-hero-title"><?php echo sg_e($sgArticleTitle !== '' ? $sgArticleTitle : '未命名文章'); ?></h1>
        <div class="sg-meta sg-meta--hero">
          <?php if ($sgSticky) : ?>
          <span class="sg-meta-chip sg-meta-chip--pin" title="置顶"><?php echo sg_icon('bookmark'); ?>置顶</span>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <?php endif; ?>
          <time class="sg-meta-chip sg-meta-chip--date" datetime="<?php $this->date('c'); ?>" title="<?php $this->date('Y-m-d H:i'); ?>"><?php echo sg_icon('calendar'); ?>发表于 <?php echo sg_e($sgPostedAgo !== '' ? $sgPostedAgo : $this->date('Y-m-d')); ?></time>
          <?php if ($sgUpdated !== '') : ?>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <span class="sg-meta-chip sg-meta-chip--updated" title="更新于 <?php echo sg_e($sgUpdated); ?>"><?php echo sg_icon('history'); ?>更新于 <?php echo sg_e($sgUpdatedAgo !== '' ? $sgUpdatedAgo : $sgUpdated); ?></span>
          <?php endif; ?>
          <?php foreach ($sgCats as $sgCat) : ?>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <a class="sg-meta-chip sg-meta-chip--cate" href="<?php echo sg_e($sgCat['permalink']); ?>"><?php echo sg_icon('folder'); ?><?php echo sg_e($sgCat['name']); ?></a>
          <?php endforeach; ?>
          <?php if ($sgRead['chars'] > 0) : ?>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <span class="sg-meta-chip sg-meta-chip--stat"><?php echo sg_icon('book-open'); ?>字数 <?php echo sg_e($sgRead['text']); ?></span>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <span class="sg-meta-chip sg-meta-chip--stat"><?php echo sg_icon('clock'); ?>约 <?php echo (int) $sgRead['minutes']; ?> 分钟</span>
          <?php endif; ?>
          <?php if ($sgViews !== '') : ?>
          <span class="sg-meta-sep" aria-hidden="true"></span>
          <span class="sg-meta-chip sg-meta-chip--stat"><?php echo sg_icon('eye'); ?>阅读 <?php echo sg_e($sgViews); ?></span>
          <?php endif; ?>
        </div>
        <?php if ($sgTags !== '') : ?><div class="sg-meta sg-meta--hero-tags"><?php echo $sgTags; ?></div><?php endif; ?>
      </div>
      <span class="sg-post-hero-wave" aria-hidden="true"></span>
    </header>
    <div class="sg-article-body">
      <a class="sg-back" href="<?php echo sg_e($sgBack); ?>"><?php echo sg_icon('arrow-left'); ?><?php echo sg_e($sgBackLabel); ?></a>
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
      <?php $this->need('sidebar.php'); ?>
      <?php $this->need('partials/toc.php'); ?>
    </div>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
