<?php
/**
 * 杂谈 —— 按 chatter 分类聚合的带封面随笔流，支持标签筛选与站内搜索。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgList = sg_category_posts($this, 'chatter', 20);
if ($sgList === null) {
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=20')->to($sgList);
}
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '碎碎念与随手记录 · 想到什么写什么'),
    'search'      => true,
    'back'        => true,
    'center'      => true,
    'placeholder' => '搜索杂谈标题或内容...',
];
?>
<main class="sg-main sg-main--pad" data-sg-page="chatter">
  <?php $this->need('partials/page-head.php'); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php $sgEmpty = true; ?>
  <div class="sg-card-grid" data-sg-filter-grid>
    <?php while ($sgList->next()) : ?>
    <?php $sgEmpty = false; ?>
    <article class="sg-glass sg-post-card" data-sg-filter-item data-keywords="<?php echo sg_e((string) $sgList->title . ' ' . sg_first_category($sgList, '')); ?>">
      <a class="sg-thumb" href="<?php $sgList->permalink(); ?>" tabindex="-1" aria-hidden="true">
        <img src="<?php echo sg_e(sg_cover($sgList)); ?>" alt="" loading="lazy">
      </a>
      <div class="sg-card-body">
        <span class="sg-card-meta">
          <span class="sg-chip--tag"><?php echo sg_e(sg_first_category($sgList, '杂谈')); ?></span>
          <time class="sg-card-date"><?php $sgList->date('Y.m.d H:i'); ?></time>
        </span>
        <h3 class="sg-card-title"><a href="<?php $sgList->permalink(); ?>"><?php $sgList->title(); ?></a></h3>
        <p class="sg-card-desc"><?php $sgList->excerpt(96, '...'); ?></p>
      </div>
    </article>
    <?php endwhile; ?>
  </div>

  <?php if ($sgEmpty) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有杂谈</h1>
    <p>把文章归到「杂谈」分类（缩略名 chatter）后就会出现在这里。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
