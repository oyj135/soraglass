<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgIsSearch = $this->is('search');

/* 碎语不进通用归档（2026-11-13）：分类页/标签页/搜索页/日期归档都只列普通文章，
   只有直接访问碎语分类本身时才全量显示，否则那个分类页会被自己清空。 */
$sgIsNotesArchive = false;
try {
    $sgIsNotesArchive = $this->is('category') && $this->getArchiveSlug() === sg_notes_slug();
} catch (\Throwable $e) {
    $sgIsNotesArchive = false;
}
$sgSkipNotes = !$sgIsNotesArchive;
$sgKeyword = '';
if ($sgIsSearch) {
    try {
        // Typecho 将 s 重写为搜索路由的 keywords；archiveSlug 已保存实际关键词。
        $sgKeyword = (string) $this->getArchiveSlug();
    } catch (\Throwable $e) {
        $sgKeyword = isset($_GET['s']) ? (string) $_GET['s'] : '';
    }
}
$sgCurrentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

// 只计本页实际渲染的记录，不能从搜索结果总数扣除全站碎语数。
$sgVisibleCount = 0;
ob_start();
while ($this->next()) {
    if ($sgSkipNotes && sg_is_note((int) $this->cid)) {
        continue;
    }
    ++$sgVisibleCount;
    $this->need('partials/card-post.php');
}
$sgCardsHtml = (string) ob_get_clean();
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="archive">
  <div class="sg-page-head">
    <div class="sg-page-head-row is-centered">
      <div>
        <h1 class="sg-page-title"><?php $this->archiveTitle(array(
          'category' => _t('分类 %s'),
          'search'   => _t('搜索 %s'),
          'tag'      => _t('标签 %s'),
          'author'   => _t('%s 的文章'),
          'date'     => _t('%s 的文章')
        ), '', ''); ?></h1>
        <p class="sg-page-sub"><?php echo $sgVisibleCount > 0 ? '本页 ' . $sgVisibleCount . ' 篇记录' : '暂未找到相关内容'; ?></p>
      </div>
    </div>
  </div>

  <?php if (!$sgIsSearch) : ?>
    <?php $this->widget('Widget_Metas_Tag_Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=16')->to($sgTagCloud); ?>
    <?php if ($sgTagCloud->have()) : ?>
    <div class="sg-filter">
      <?php /* 「全部」= 全部文章归档页；分类/标签/日期本身是筛选态，不再误标「全部」为选中 */ ?>
      <a class="sg-filter-btn" href="<?php echo sg_e(sg_page_url('archives')); ?>">全部</a>
      <?php while ($sgTagCloud->next()) : ?>
      <?php $sgTagPath = parse_url((string) $sgTagCloud->permalink, PHP_URL_PATH); ?>
      <a class="sg-filter-btn<?php echo ($sgTagPath !== null && $sgTagPath === $sgCurrentPath) ? ' is-active' : ''; ?>" href="<?php $sgTagCloud->permalink(); ?>">
        <?php $sgTagCloud->name(); ?><span class="sg-filter-count"><?php $sgTagCloud->count(); ?></span>
      </a>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($sgVisibleCount > 0) : ?>
  <section class="sg-reading-list sg-reading-surface" aria-label="文章列表">
    <?php echo $sgCardsHtml; ?>
  </section>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">0</span>
    <h1>没有匹配的内容</h1>
    <p>换个关键词，或者从标签里挑一个方向再试试。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
      <a class="sg-btn sg-btn--ghost" href="<?php echo sg_e(sg_page_url('archives')); ?>"><?php echo sg_icon('folder'); ?>浏览全部归档</a>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
