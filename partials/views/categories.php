<?php
/**
 * 分类视图：分类总览卡片（名称 / 描述 / 篇数），点进 Typecho 原生分类路由。
 * 由 page.php 在 slug = categories 时分发进来。
 */
$sgRows = [];

/* P1-4：每个分类附带最新一篇文章，一次连表查好按 mid 索引 */
$sgLatestMap = sg_category_latest_map();

try {
    $this->widget('Widget_Metas_Category_List')->to($sgCats);
    while ($sgCats->next()) {
        $sgRows[] = [
            'mid'   => (int) $sgCats->mid,
            'name'  => (string) $sgCats->name,
            'desc'  => trim((string) $sgCats->description),
            'count' => (int) $sgCats->count,
            'url'   => (string) $sgCats->permalink,
        ];
    }
} catch (\Throwable $e) {
    $sgRows = [];
}

$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => $sgRows ? '共 ' . count($sgRows) . ' 个分类' : '还没有分类',
    'center' => true,
    'back'   => true,
];
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="categories">
  <?php sg_page_head($sgHead); ?>

  <?php if (!$sgRows) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">0</span>
    <h1>还没有分类</h1>
    <p>在后台「管理 → 分类」新建分类后，这里会自动列出来。</p>
  </div>
  <?php else : ?>
  <div class="sg-term-grid">
    <?php foreach ($sgRows as $sgRow) : ?>
    <?php $sgLatest = $sgLatestMap[$sgRow['mid']] ?? null; ?>
    <div class="sg-glass sg-term-card">
      <a class="sg-term-card-top" href="<?php echo sg_e($sgRow['url']); ?>">
        <span class="sg-term-card-name"><?php echo sg_e($sgRow['name']); ?></span>
        <span class="sg-term-card-arrow"><?php echo sg_icon_color('arrow-up-right'); ?></span>
      </a>
      <span class="sg-term-card-desc"><?php echo sg_e($sgRow['desc'] !== '' ? $sgRow['desc'] : '这个分类还没有写说明'); ?></span>
      <span class="sg-term-card-count"><b><?php echo $sgRow['count']; ?></b> 篇文章</span>
      <?php if ($sgLatest) : ?>
      <a class="sg-term-card-latest" href="<?php echo sg_e($sgLatest['url']); ?>">
        <?php echo sg_icon_color('clock'); ?>
        <span><?php echo sg_e($sgLatest['title'] !== '' ? $sgLatest['title'] : '未命名文章'); ?></span>
        <time><?php echo sg_e($sgLatest['date']); ?></time>
      </a>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
