<?php
/**
 * 标签视图：标签云 + 篇数，点进 Typecho 原生标签路由。
 * 由 page.php 在 slug = label 时分发进来（旧页面正文里那坨 CSS 文本不再输出）。
 */
$sgTags = [];

try {
    $this->widget('Widget_Metas_Tag_Cloud', 'sort=count&ignoreZeroCount=1&desc=1')->to($sgCloud);
    while ($sgCloud->next()) {
        $sgTags[] = [
            'name'  => (string) $sgCloud->name,
            'count' => (int) $sgCloud->count,
            'url'   => (string) $sgCloud->permalink,
        ];
    }
} catch (\Throwable $e) {
    $sgTags = [];
}

$sgMax = 1;
foreach ($sgTags as $sgTag) {
    if ($sgTag['count'] > $sgMax) {
        $sgMax = $sgTag['count'];
    }
}

$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => $sgTags ? '共 ' . count($sgTags) . ' 个标签' : '还没有标签',
    'center' => true,
    'back'   => true,
];
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="label">
  <?php sg_page_head($sgHead); ?>

  <?php if (!$sgTags) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">0</span>
    <h1>还没有标签</h1>
    <p>给文章添加标签后，这里会按使用次数排列成标签云。</p>
  </div>
  <?php else : ?>
  <div class="sg-glass sg-tag-cloud">
    <?php foreach ($sgTags as $sgTag) : ?>
    <?php $sgWeight = $sgMax > 1 ? ($sgTag['count'] / $sgMax) : 1; ?>
    <a class="sg-tag-chip" href="<?php echo sg_e($sgTag['url']); ?>" style="--sg-weight:<?php echo round(0.6 + $sgWeight * 0.4, 3); ?>">
      <?php echo sg_icon_color('tag'); ?><?php echo sg_e($sgTag['name']); ?><span class="sg-tag-chip-count"><?php echo $sgTag['count']; ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
