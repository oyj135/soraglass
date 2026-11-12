<?php
/**
 * 知识档案（原「时间线」）
 *
 * @package custom
 *
 * 结构与源站 components/TimelineClient.tsx + TimelineNode.tsx 一致：
 * 标题 → 搜索 → 「全部档案」+ 标签胶囊（带计数）→ 固定矩阵网格
 * （封面/日期/标题/描述/#标签）。
 *
 * 2026-11-12 第四轮：去掉「中枢链路 / 矩阵网格」双视图切换。
 * 两套布局的阅读节奏差异太大，切换按钮本身也在抢标签胶囊的注意力；
 * 现只保留等宽矩阵，行高与列宽由 CSS 固定，翻页时不会跳版。
 *
 * 缩略名仍是 timeline，旧链接不失效；只是导航与标题换成了更贴切的名字。
 *
 * 标签胶囊不取 Widget_Metas_Tag_Cloud，而是从本页文章列表自己汇总：
 * 这样胶囊的 slug 与卡片 data-tags 一定同源，筛选不会因为转义差异匹配不上。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgSize = (int) sg_opt('sgPageSize', '200');
if ($sgSize <= 0) {
    $sgSize = 200;
}

$sgPosts = [];
$sgCids  = [];
try {
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=' . $sgSize)->to($sgRecent);
    while ($sgRecent->next()) {
        $sgCids[] = (int) $sgRecent->cid;
        $sgPosts[] = [
            'cid'       => (int) $sgRecent->cid,
            'title'     => (string) $sgRecent->title,
            'permalink' => (string) $sgRecent->permalink,
            // Widget::date() 是「打印」而不是「返回」——写成 $sgRecent->date('Y.m.d')
            // 会把这 6 个日期直接 echo 到 <main> 之前（页首那串裸日期）。
            'date'      => $sgRecent->date->format('Y.m.d'),
            'cover'     => sg_cover($sgRecent),
            'desc'      => sg_field($sgRecent, 'description', '') !== ''
                ? sg_field($sgRecent, 'description', '')
                : sg_excerpt($sgRecent, 110),
        ];
    }
} catch (\Throwable $e) {
    $sgPosts = [];
}

// 标签总表：slug => ['name'=>..,'count'=>..]，按出现次数倒序，最多 18 个
$sgTagMap   = sg_post_tags_map($sgCids);
$sgTagStats = [];
foreach ($sgTagMap as $sgRowTags) {
    foreach ($sgRowTags as $sgTag) {
        $sgKey = $sgTag['slug'];
        if (!isset($sgTagStats[$sgKey])) {
            $sgTagStats[$sgKey] = ['name' => $sgTag['name'], 'count' => 0];
        }
        $sgTagStats[$sgKey]['count'] += 1;
    }
}
uasort($sgTagStats, static function (array $a, array $b): int {
    return $b['count'] <=> $a['count'];
});
$sgTagStats = array_slice($sgTagStats, 0, 18, true);

$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '总计 ' . count($sgPosts) . ' 篇研究记录'),
    'search'      => true,
    'center'      => false,
    'placeholder' => '搜寻被封存的知识...',
];
?>
<main class="sg-main sg-main--wide sg-main--pad sg-archive" data-sg-page="timeline" data-sg-archive data-sg-grid>
  <?php sg_page_head($sgHead); ?>

  <div class="sg-archive-toolbar">
    <div class="sg-filter sg-archive-tags">
      <button class="sg-filter-btn is-active" type="button" data-sg-archive-tag="全部">全部档案</button>
      <?php foreach ($sgTagStats as $sgSlug => $sgStat) : ?>
      <button class="sg-filter-btn" type="button" data-sg-archive-tag="<?php echo sg_e((string) $sgSlug); ?>"><?php echo sg_e($sgStat['name']); ?><span class="sg-filter-count"><?php echo (int) $sgStat['count']; ?></span></button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="sg-archive-list">
    <?php foreach ($sgPosts as $sgPost) : ?>
    <?php
      $sgTags    = $sgTagMap[$sgPost['cid']] ?? [];
      $sgSlugs   = array_map(static fn(array $t): string => $t['slug'], $sgTags);
      $sgNames   = array_map(static fn(array $t): string => $t['name'], $sgTags);
      $sgKeyword = $sgPost['title'] . ' ' . $sgPost['desc'] . ' ' . implode(' ', $sgNames);
    ?>
    <article class="sg-glass sg-archive-card" data-sg-archive-card data-keywords="<?php echo sg_e($sgKeyword); ?>" data-tags="<?php echo sg_e(implode('|', $sgSlugs)); ?>" data-sg-spotlight>
      <a class="sg-archive-cover" href="<?php echo sg_e($sgPost['permalink']); ?>" tabindex="-1" aria-hidden="true">
        <img src="<?php echo sg_e($sgPost['cover']); ?>" alt="" loading="lazy">
      </a>
      <div class="sg-archive-body">
        <div class="sg-archive-date"><time datetime="<?php echo sg_e($sgPost['date']); ?>"><?php echo sg_e($sgPost['date']); ?></time></div>
        <h3><a href="<?php echo sg_e($sgPost['permalink']); ?>"><?php echo sg_e($sgPost['title']); ?></a></h3>
        <?php if ($sgPost['desc'] !== '') : ?><p><?php echo sg_e($sgPost['desc']); ?></p><?php endif; ?>
        <?php if ($sgNames) : ?>
        <div class="sg-archive-tags">
          <?php foreach ($sgNames as $sgTagName) : ?>
          <span class="sg-archive-tag">#<?php echo sg_e($sgTagName); ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <p class="sg-filter-empty" data-sg-archive-empty hidden>这个频段没有接收到任何信号，换个关键词或标签试试。</p>

  <?php if (!$sgPosts) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有研究记录</h1>
    <p>发布第一篇文章后，这里会自动按时间归档。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
