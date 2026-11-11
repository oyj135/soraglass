<?php
/**
 * 说说
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgList = sg_category_posts($this, 'moments', 30);
if ($sgList === null) {
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=30')->to($sgList);
}
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '生活动态 · 随手记下的碎片'),
    'search'      => true,
    'back'        => true,
    'center'      => true,
    'placeholder' => '搜索说说内容...',
];
?>
<main class="sg-main sg-main--pad" data-sg-page="moments">
  <?php $this->need('partials/page-head.php'); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <div class="sg-card-grid" data-sg-filter-grid>
    <?php while ($sgList->next()) : ?>
    <article class="sg-glass sg-note" data-sg-filter-item data-keywords="<?php echo sg_e((string) $sgList->title); ?>">
      <time><?php $sgList->date('Y.m.d H:i'); ?></time>
      <h3><a href="<?php $sgList->permalink(); ?>"><?php $sgList->title(); ?></a></h3>
      <p><?php $sgList->excerpt(110, '...'); ?></p>
      <a class="sg-note-more" href="<?php $sgList->permalink(); ?>">阅读全文 →</a>
    </article>
    <?php endwhile; ?>
  </div>
</main>
<?php $this->need('footer.php'); ?>
