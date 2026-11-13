<?php
/**
 * 归档视图：按「年 → 月」倒序分组的全部文章。
 * 由 page.php 在 slug = archives 时分发进来，不需要后台选择自定义模板。
 * 不调用 getTotal()/page —— page 上下文里 Archive::$countSql 未初始化会抛错。
 */
$sgLimit = (int) sg_opt('sgPageSize', '200');
if ($sgLimit < 1) {
    $sgLimit = 200;
}

$sgGroups = [];
$sgTotal = 0;

try {
    /* 碎语不进归档（2026-11-13）：多取碎语条数再剔掉，保证普通文章的量不被挤占 */
    $sgNoteTotal = count(sg_note_cids());
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=' . ($sgLimit + $sgNoteTotal))->to($sgPosts);
    while ($sgPosts->next()) {
        if (sg_is_note((int) $sgPosts->cid)) {
            continue;
        }
        if ($sgTotal >= $sgLimit) {
            break;
        }
        $sgYear = (string) $sgPosts->date->year;
        $sgMonth = (string) $sgPosts->date->month;
        $sgGroups[$sgYear][$sgMonth][] = [
            'title' => (string) $sgPosts->title,
            'url'   => (string) $sgPosts->permalink,
            'day'   => $sgPosts->date->format('d'),
        ];
        $sgTotal++;
    }
} catch (\Throwable $e) {
    $sgGroups = [];
}

if ($sgGroups) {
    krsort($sgGroups);
    foreach ($sgGroups as $sgKey => $sgMonths) {
        krsort($sgGroups[$sgKey]);
    }
}

$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => $sgTotal > 0 ? '共 ' . $sgTotal . ' 篇记录' : '还没有文章',
    'center' => true,
    // 第六轮：去掉 'back' => true。归档页本身就是导航里的一级入口，
    // 页头再挂「返回上一级」既重复又抢标题（用户截图里那颗胶囊）。
];
?>
<main class="sg-main sg-main--pad" data-sg-page="archives">
  <?php sg_page_head($sgHead); ?>

  <?php if (!$sgGroups) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">0</span>
    <h1>还没有可归档的内容</h1>
    <p>发布第一篇文章后，这里会自动按年月排好。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e(rtrim((string) $this->options->siteUrl, '/')); ?>/"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php else : ?>
  <div class="sg-timeline">
    <?php foreach ($sgGroups as $sgYear => $sgMonths) : ?>
    <?php $sgYearCount = 0; foreach ($sgMonths as $sgList) { $sgYearCount += count($sgList); } ?>
    <section class="sg-timeline-year">
      <header class="sg-timeline-year-head">
        <span class="sg-timeline-year-num"><?php echo sg_e((string) $sgYear); ?></span>
        <span class="sg-timeline-year-count"><?php echo $sgYearCount; ?> 篇</span>
      </header>
      <?php foreach ($sgMonths as $sgMonth => $sgItems) : ?>
      <div class="sg-timeline-month">
        <h3 class="sg-timeline-month-title"><?php echo (int) $sgMonth; ?> 月</h3>
        <ul class="sg-timeline-list">
          <?php foreach ($sgItems as $sgItem) : ?>
          <li class="sg-timeline-item">
            <time class="sg-timeline-date"><?php echo sg_e($sgItem['day']); ?></time>
            <a class="sg-timeline-link" href="<?php echo sg_e($sgItem['url']); ?>"><?php echo sg_e($sgItem['title']); ?></a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
