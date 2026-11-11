<?php
/**
 * 归档 / 时间线
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$this->widget('Widget_Contents_Post_Recent', 'pageSize=60')->to($sgArchive);
$sgStats = sg_stats();
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '总计 ' . $sgStats['posts'] . ' 篇研究记录'),
    'search'      => true,
    'back'        => true,
    'center'      => true,
    'placeholder' => '搜寻被封存的知识...',
];
?>
<main class="sg-main sg-main--pad" data-sg-page="timeline">
  <?php $this->need('partials/page-head.php'); ?>

  <?php $this->widget('Widget_Metas_Tag_Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=18')->to($sgTagCloud); ?>
  <?php if ($sgTagCloud->have()) : ?>
  <div class="sg-filter" style="margin-bottom:2rem">
    <a class="sg-filter-btn is-active" href="<?php echo sg_e(rtrim((string) $this->options->siteUrl, '/') . '/'); ?>">全部档案</a>
    <?php while ($sgTagCloud->next()) : ?>
    <a class="sg-filter-btn" href="<?php $sgTagCloud->permalink(); ?>"><?php $sgTagCloud->name(); ?><span class="sg-filter-count"><?php $sgTagCloud->count(); ?></span></a>
    <?php endwhile; ?>
  </div>
  <?php endif; ?>

  <div class="sg-card-grid">
    <?php while ($sgArchive->next()) : ?>
    <article class="sg-glass sg-note">
      <time><?php $sgArchive->date('Y.m.d H:i'); ?></time>
      <h3><a href="<?php $sgArchive->permalink(); ?>"><?php $sgArchive->title(); ?></a></h3>
      <p><?php $sgArchive->excerpt(110, '...'); ?></p>
      <a class="sg-note-more" href="<?php $sgArchive->permalink(); ?>">阅读全文 →</a>
    </article>
    <?php endwhile; ?>
  </div>
</main>
<?php $this->need('footer.php'); ?>
