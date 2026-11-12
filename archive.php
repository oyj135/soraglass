<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgTotal = 0;
try {
    $sgTotal = (int) $this->getTotal();
} catch (\Throwable $e) {
    $sgTotal = 0;
}
$sgIsSearch = $this->is('search');
$sgKeyword = '';
if ($sgIsSearch) {
    try {
        $sgKeyword = (string) $this->request->get('s');
    } catch (\Throwable $e) {
        $sgKeyword = isset($_GET['s']) ? (string) $_GET['s'] : '';
    }
}
$sgCurrentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
?>
<main class="sg-main sg-main--pad" data-sg-page="archive">
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
        <p class="sg-page-sub"><?php echo $sgTotal > 0 ? '共 ' . $sgTotal . ' 篇记录' : '暂未找到相关内容'; ?></p>
      </div>
      <form class="sg-search sg-search--sm" role="search" method="get" action="<?php echo sg_e($sgHome); ?>">
        <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
        <input class="sg-search-input" type="search" name="s" value="<?php echo sg_e($sgKeyword); ?>" placeholder="搜寻标题、描述或标签..." aria-label="站内搜索">
      </form>
    </div>
  </div>

  <?php if (!$sgIsSearch) : ?>
    <?php $this->widget('Widget_Metas_Tag_Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=16')->to($sgTagCloud); ?>
    <?php if ($sgTagCloud->have()) : ?>
    <div class="sg-filter" style="margin-bottom:1.5rem">
      <a class="sg-filter-btn<?php echo $this->is('tag') ? '' : ' is-active'; ?>" href="<?php echo sg_e($sgHome); ?>">全部</a>
      <?php while ($sgTagCloud->next()) : ?>
      <?php $sgTagPath = parse_url((string) $sgTagCloud->permalink, PHP_URL_PATH); ?>
      <a class="sg-filter-btn<?php echo ($sgTagPath !== null && $sgTagPath === $sgCurrentPath) ? ' is-active' : ''; ?>" href="<?php $sgTagCloud->permalink(); ?>">
        <?php $sgTagCloud->name(); ?><span class="sg-filter-count"><?php $sgTagCloud->count(); ?></span>
      </a>
      <?php endwhile; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($this->have()) : ?>
  <div class="sg-card-grid">
    <?php while ($this->next()) : ?><?php $this->need('partials/card-post.php'); ?><?php endwhile; ?>
  </div>
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
