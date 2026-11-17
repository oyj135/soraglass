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
   发表于 / 更新于走相对时间；字数与阅读时长复用正文统计；阅读量优先自定义字段 views，
   没有时交给页脚 busuanzi 镜像回写 #busuanzi_value_page_pv，加载失败保留占位「—」。 */
$sgPostedAgo = sg_time_ago((int) $this->created);
$sgViews = sg_field($this, 'views', '');
$sgSticky = (int) sg_field($this, 'sticky', '0') > 0;

$sgPrev = sg_adjacent_post($this, 'prev');
$sgNext = sg_adjacent_post($this, 'next');
?>
<main id="sg-main" class="sg-main sg-article-layout" data-sg-page="post" data-sg-origin="<?php echo $sgIsNote ? 'note' : 'article'; ?>">
  <?php /* 文章页顶部（2026-11-17 第六轮）：背景图移除（用户反馈），回到
         Fomalhaut not-top-img 的 #post > #post-info 形态 —— 标题左对齐、
         normal 字重、下缘分隔线；元信息两行 #78818a 灰，图标保留彩色。 */ ?>
  <?php /* 文章页顶部（2026-11-17 第七轮）：与源站一致 —— 全宽透明横幅压在 fixed 壁纸上，
         不渲染封面图；标题居中白字（夜间霓虹发光），元信息两行居中、#78818a 灰、彩色图标。 */ ?>
  <header class="sg-post-banner">
    <h1 class="sg-post-banner-title"><?php echo sg_e($sgArticleTitle !== '' ? $sgArticleTitle : '未命名文章'); ?></h1>
    <div class="sg-post-banner-meta">
      <div class="sg-post-meta-line">
        <?php if ($sgSticky) : ?>
        <span class="sg-post-meta-item sg-post-meta-pin" title="置顶"><?php echo sg_icon('bookmark'); ?>置顶</span>
        <span class="sg-post-meta-sep" aria-hidden="true">|</span>
        <?php endif; ?>
        <time class="sg-post-meta-item is-date" datetime="<?php $this->date('c'); ?>" title="<?php $this->date('Y-m-d H:i'); ?>"><?php echo sg_icon('calendar'); ?>发表于 <?php echo sg_e($sgPostedAgo !== '' ? $sgPostedAgo : $this->date('Y-m-d')); ?></time>
        <?php if ($sgUpdated !== '') : ?>
        <span class="sg-post-meta-sep" aria-hidden="true">|</span>
        <span class="sg-post-meta-item is-updated" title="更新于 <?php echo sg_e($sgUpdated); ?>"><?php echo sg_icon('history'); ?>更新于 <?php echo sg_e($sgUpdatedAgo !== '' ? $sgUpdatedAgo : $sgUpdated); ?></span>
        <?php endif; ?>
        <?php foreach ($sgCats as $sgCat) : ?>
        <span class="sg-post-meta-sep" aria-hidden="true">|</span>
        <a class="sg-post-meta-item is-cate sg-post-meta-cate" href="<?php echo sg_e($sgCat['permalink']); ?>"><?php echo sg_icon('tag'); ?><?php echo sg_e($sgCat['name']); ?></a>
        <?php endforeach; ?>
      </div>
      <div class="sg-post-meta-line">
        <span class="sg-post-meta-item is-word"><?php echo sg_icon('type'); ?>字数总计: <?php echo (int) $sgRead['chars']; ?></span>
        <span class="sg-post-meta-sep" aria-hidden="true">|</span>
        <span class="sg-post-meta-item is-time"><?php echo sg_icon('clock'); ?>阅读时长: <?php echo (int) $sgRead['minutes']; ?>分钟</span>
        <span class="sg-post-meta-sep" aria-hidden="true">|</span>
        <span class="sg-post-meta-item is-views"><?php echo sg_icon('eye'); ?>阅读量: <?php if ($sgViews !== '') : ?><?php echo sg_e($sgViews); ?><?php else : ?><span id="busuanzi_value_page_pv">—</span><?php endif; ?></span>
      </div>
    </div>
  </header>
  <article class="sg-glass sg-article sg-reading-surface">
    <div class="sg-article-body">
      <a class="sg-back" href="<?php echo sg_e($sgBack); ?>"><?php echo sg_icon('arrow-left'); ?><?php echo sg_e($sgBackLabel); ?></a>
      <?php if ($sgLead !== '') : ?><p class="sg-lead"><?php echo sg_e($sgLead); ?></p><?php endif; ?>
      <div class="sg-prose" data-sg-article><?php echo $sgContent; ?></div>

      <?php if ($sgTags !== '') : ?><div class="sg-meta sg-article-tags" aria-label="文章标签"><?php echo $sgTags; ?></div><?php endif; ?>

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

      <?php $sgRelated = sg_related_posts($this, 6); if ($sgRelated) : ?>
      <div class="sg-related">
        <div class="sg-related-head"><?php echo sg_icon('thumbs-up'); ?>相关推荐</div>
        <div class="sg-related-list">
          <?php foreach ($sgRelated as $sgRel) : ?>
          <div class="sg-related-cell">
            <a class="sg-related-item" href="<?php echo sg_e($sgRel['url']); ?>" title="<?php echo sg_e($sgRel['title']); ?>">
              <img src="<?php echo sg_e($sgRel['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
              <div class="sg-related-content">
                <div class="sg-related-date"><?php echo sg_icon('history'); ?><time><?php echo sg_e($sgRel['date']); ?></time></div>
                <div class="sg-related-title"><?php echo sg_e($sgRel['title'] !== '' ? $sgRel['title'] : '未命名文章'); ?></div>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </article>
  <aside class="sg-aside">
    <?php /* 整列吸顶（2026-11-16 第三轮）：作者卡不再随滚动滑走，
       公告 / 目录全部展示不内滚；列太高时由 article.ts 改为底对齐。 */ ?>
    <div class="sg-aside-pin" data-sg-aside-pin>
      <?php $this->need('partials/author-card.php'); ?>
      <?php $this->need('sidebar.php'); ?>
      <?php $this->need('partials/toc.php'); ?>
    </div>
  </aside>
</main>
<?php $this->need('footer.php'); ?>
