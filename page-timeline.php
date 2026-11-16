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

/* 知识档案是「全量知识条目」页，取数上限固定 200，不受后台「每页条数上限」
   sgPageSize 影响——归档页 archives.php 同样固定 200。2026-11-16 修复：
   此前跟随 sgPageSize，后台误填 6 时本部文章被 if(count>=sgSize) 截断成 6 篇，
   标签计数也跟着只统计这 6 篇（java 4 / 后端 4 …），表现为「篇数与标签都错」。 */
$sgSize = 200;

$sgPosts = [];
$sgCids  = [];
try {
    /* 碎语不属于知识档案（2026-11-13）：多取「碎语条数」那么多再剔掉，
       保证普通文章仍能填满一页，不会被碎语挤掉。 */
    $sgNoteTotal = count(sg_note_cids());
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=' . ($sgSize + $sgNoteTotal))->to($sgRecent);
    while ($sgRecent->next()) {
        if (sg_is_note((int) $sgRecent->cid)) {
            continue;
        }
        if (count($sgPosts) >= $sgSize) {
            break;
        }
        $sgCids[] = (int) $sgRecent->cid;
        $sgPosts[] = [
            'cid'       => (int) $sgRecent->cid,
            'title'     => (string) $sgRecent->title,
            'permalink' => (string) $sgRecent->permalink,
            // Widget::date() 是「打印」而不是「返回」——写成 $sgRecent->date('Y.m.d')
            // 会把这 6 个日期直接 echo 到 <main> 之前（页首那串裸日期）。
            'date'      => $sgRecent->date->format('Y.m.d'),
            // 时间戳供参考图卡「发表于 N 天前」用；Typecho\Date 有 timestamp 属性
            'stamp'     => (int) ($sgRecent->date->timestamp ?? 0),
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
<main id="sg-main" class="sg-main sg-main--wide sg-main--pad sg-archive" data-sg-page="timeline" data-sg-archive data-sg-grid>
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
    <?php
      $sgAgo = sg_time_ago((int) ($sgPost['stamp'] ?? 0));
      $sgDesc = trim((string) $sgPost['desc']);
    ?>
    <article class="sg-feature-card" data-sg-archive-card data-sg-page-item data-keywords="<?php echo sg_e($sgKeyword); ?>" data-tags="<?php echo sg_e(implode('|', $sgSlugs)); ?>">
      <a class="sg-feature-media" href="<?php echo sg_e($sgPost['permalink']); ?>" tabindex="-1" aria-hidden="true">
        <img src="<?php echo sg_e($sgPost['cover']); ?>" alt="" loading="lazy">
        <?php if ($sgDesc !== '') : ?>
        <span class="sg-feature-quote">「<?php echo sg_e($sgDesc); ?>」</span>
        <?php endif; ?>
      </a>
      <div class="sg-feature-body">
        <h3 class="sg-feature-title"><a href="<?php echo sg_e($sgPost['permalink']); ?>"><?php echo sg_e($sgPost['title']); ?></a></h3>
        <div class="sg-feature-meta">
          <span><?php echo sg_icon('clock'); ?><time datetime="<?php echo sg_e($sgPost['date']); ?>"><?php echo sg_e($sgAgo !== '' ? '发表于 ' . $sgAgo : '发表于 ' . $sgPost['date']); ?></time></span>
        </div>
        <?php if ($sgNames) : ?>
        <div class="sg-feature-tags">
          <span><?php echo sg_icon('tag'); ?><?php echo sg_e(implode(' · ', $sgNames)); ?></span>
        </div>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- 分页条（每页 9 篇）：由 archive.ts 按筛选结果动态渲染 -->
  <nav class="sg-page-nav" data-sg-page-nav aria-label="知识档案分页"></nav>

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
