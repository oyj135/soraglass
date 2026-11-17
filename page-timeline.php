<?php
/**
 * 知识档案（原「时间线」）
 *
 * @package custom
 *
 * 结构：标题 → 搜索 → 「全部档案」+ 标签胶囊（带计数）→ 固定矩阵网格 → 分页条。
 *
 * 服务端分页（2026-11-16 第二十轮）：筛选与分页全部在服务端完成，
 * URL 三个 query 参数：tag（标签 slug）、k（关键词）、p（页码，1 起）。
 * 不能用 s / page：Typecho 的 Archive::execute() 把 s 当搜索参数 302 跳走、
 * 把 page 当归档分页，独立页会被劫持（见 var/Widget/Archive.php:565）。
 * 服务端只输出当前页 9 篇的 HTML，翻页/筛选都是真实 <a> 链接 —— 既可被
 * navigation.ts 的 PJAX 接管（query 变化即新路由），无 JS 时也能正常翻页。
 * 标签计数基于全量文章（切片前），不随分页变化。
 *
 * 碎语不属于知识档案：多取碎语条数再剔掉，保证普通文章仍能填满一页。
 * 缩略名仍是 timeline，旧链接不失效。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgSize = 200;                 // 全量取数上限（与归档页一致，不受 sgPageSize 影响）
$sgPageSize = 9;               // 显示层每页 9 篇（3×3 矩阵）
$sgCurrentPage = max(1, (int) ($_GET['p'] ?? 1));
$sgActiveTag = trim((string) ($_GET['tag'] ?? ''));
$sgKeyword = trim((string) ($_GET['k'] ?? ''));

$sgAllPosts = [];
$sgCids  = [];
try {
    $sgNoteTotal = count(sg_note_cids());
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=' . ($sgSize + $sgNoteTotal))->to($sgRecent);
    while ($sgRecent->next()) {
        if (sg_is_note((int) $sgRecent->cid)) {
            continue;
        }
        if (count($sgAllPosts) >= $sgSize) {
            break;
        }
        $sgCids[] = (int) $sgRecent->cid;
        $sgAllPosts[] = [
            'cid'       => (int) $sgRecent->cid,
            'title'     => (string) $sgRecent->title,
            'permalink' => (string) $sgRecent->permalink,
            // Widget::date() 是「打印」而非「返回」，取格式化串要用 ->date->format()
            'date'      => $sgRecent->date->format('Y.m.d'),
            'stamp'     => (int) ($sgRecent->date->timestamp ?? 0),
            'sticky'    => (int) sg_field($sgRecent, 'sticky', '0'),
            'cover'     => sg_cover($sgRecent),
            'desc'      => sg_card_desc($sgRecent),
        ];
    }
} catch (\Throwable $e) {
    $sgAllPosts = [];
}

// 标签总表：slug => ['name'=>..,'count'=>..]，基于全量，按出现次数倒序，最多 18 个
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

/* ---- 服务端筛选：标签 slug + 关键词（标题/描述/标签名），二者交叉 ---- */
$sgFiltered = [];
foreach ($sgAllPosts as $sgPost) {
    $sgRowTags = $sgTagMap[$sgPost['cid']] ?? [];
    $sgSlugs   = array_map(static fn(array $t): string => (string) $t['slug'], $sgRowTags);
    $sgNames   = array_map(static fn(array $t): string => (string) $t['name'], $sgRowTags);

    $sgByTag = $sgActiveTag === '' || in_array($sgActiveTag, $sgSlugs, true);
    $sgByText = true;
    if ($sgKeyword !== '') {
        $sgHaystack = mb_strtolower($sgPost['title'] . ' ' . $sgPost['desc'] . ' ' . implode(' ', $sgNames));
        $sgByText = mb_strpos($sgHaystack, mb_strtolower($sgKeyword)) !== false;
    }
    if (!$sgByTag || !$sgByText) {
        continue;
    }
    $sgPost['slugs'] = $sgSlugs;
    $sgPost['names'] = $sgNames;
    $sgFiltered[] = $sgPost;
}

/* ---- 服务端切片：只把当前页的 9 篇渲染成 HTML ---- */
$sgTotal = count($sgFiltered);
$sgTotalPages = max(1, (int) ceil($sgTotal / $sgPageSize));
if ($sgCurrentPage > $sgTotalPages) {
    $sgCurrentPage = $sgTotalPages;
}
$sgPosts = array_slice($sgFiltered, ($sgCurrentPage - 1) * $sgPageSize, $sgPageSize);

/* ---- 分页/筛选链接构造：相对 query，PJAX 可接管，无 JS 也能翻页 ---- */
// 翻页链接：换页码，保留当前标签与关键词。
$sgUrl = static function (int $p) use ($sgActiveTag, $sgKeyword): string {
    $sgQuery = [];
    if ($p > 1) { $sgQuery['p'] = $p; }
    if ($sgActiveTag !== '') { $sgQuery['tag'] = $sgActiveTag; }
    if ($sgKeyword !== '') { $sgQuery['k'] = $sgKeyword; }
    return '?' . ($sgQuery ? http_build_query($sgQuery) : '');
};
// 标签切换：保留搜索词、回到第 1 页；「全部」清空 tag。
$sgTagUrl = static function (string $tag) use ($sgKeyword): string {
    $sgQuery = [];
    if ($tag !== '') { $sgQuery['tag'] = $tag; }
    if ($sgKeyword !== '') { $sgQuery['k'] = $sgKeyword; }
    return '?' . (($sgQuery) ? http_build_query($sgQuery) : '');
};

$sgSub = sg_page_sub($this, '总计 ' . $sgTotal . ' 篇研究记录');
if ($sgTotal !== count($sgAllPosts)) {
    $sgSub .= ' · 当前筛选 ' . $sgTotal . ' 篇';
}
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => $sgSub,
    'search'      => true,
    'center'      => false,
    'placeholder' => '搜寻被封存的知识...',
    // 服务端搜索：提交到本页，用 k（避开 Typecho 占用的 s），保留当前标签，回填关键词
    'search_action' => '',
    'search_name'   => 'k',
    'search_hidden' => ($sgActiveTag !== '') ? ['tag' => $sgActiveTag] : [],
    'search_value'  => $sgKeyword,
];
?>
<main id="sg-main" class="sg-main sg-main--wide sg-main--pad sg-archive" data-sg-page="timeline" data-sg-archive data-sg-grid>
  <?php sg_page_head($sgHead); ?>

  <div class="sg-archive-toolbar">
    <nav class="sg-filter sg-archive-tags" aria-label="按标签筛选">
      <?php $sgAllActive = $sgActiveTag === ''; ?>
      <a class="sg-filter-btn<?php echo $sgAllActive ? ' is-active' : ''; ?>" href="<?php echo sg_e($sgTagUrl('')); ?>"<?php echo $sgAllActive ? ' aria-current="page"' : ''; ?>>全部档案</a>
      <?php foreach ($sgTagStats as $sgSlug => $sgStat) : ?>
      <?php $sgOn = ($sgActiveTag === (string) $sgSlug); ?>
      <a class="sg-filter-btn<?php echo $sgOn ? ' is-active' : ''; ?>" href="<?php echo sg_e($sgTagUrl((string) $sgSlug)); ?>"<?php echo $sgOn ? ' aria-current="page"' : ''; ?>><?php echo sg_e($sgStat['name']); ?><span class="sg-filter-count"><?php echo (int) $sgStat['count']; ?></span></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="sg-archive-list">
    <?php foreach ($sgPosts as $sgPost) : ?>
    <?php
      $sgNames   = $sgPost['names'];
      $sgSlugs   = $sgPost['slugs'];
      $sgKeywordRow = $sgPost['title'] . ' ' . $sgPost['desc'] . ' ' . implode(' ', $sgNames);
    ?>
    <?php
      $sgAgo = sg_time_ago((int) ($sgPost['stamp'] ?? 0));
      $sgDesc = trim((string) $sgPost['desc']);
      /* 引号摘要精简（2026-11-17）：最多 48 字（两行以内），与首页/归档卡片同步 */
      if (function_exists('mb_strlen') && mb_strlen($sgDesc, 'UTF-8') > 48) {
          $sgDesc = mb_substr($sgDesc, 0, 48, 'UTF-8') . '…';
      }
    ?>
    <article class="sg-feature-card" data-sg-archive-card data-keywords="<?php echo sg_e($sgKeywordRow); ?>" data-tags="<?php echo sg_e(implode('|', $sgSlugs)); ?>">
      <?php if ((int) ($sgPost['sticky'] ?? 0) > 0) : ?>
      <div class="sg-sticky-badge<?php echo (int) $sgPost['sticky'] >= 2 ? ' is-top' : ''; ?>"><?php echo sg_icon('bookmark'); ?>置顶<span class="sg-sticky-shine" aria-hidden="true"></span></div>
      <?php endif; ?>
      <a class="sg-feature-media" href="<?php echo sg_e($sgPost['permalink']); ?>" tabindex="-1" aria-hidden="true">
        <img src="<?php echo sg_e($sgPost['cover']); ?>" alt="" loading="lazy">
        <?php if ($sgDesc !== '') : ?>
        <span class="sg-feature-quote">「<?php echo sg_e($sgDesc); ?>」</span>
        <?php endif; ?>
        <span class="sg-feature-arrow" aria-hidden="true"></span>
      </a>
      <div class="sg-feature-body">
        <h3 class="sg-feature-title"><a href="<?php echo sg_e($sgPost['permalink']); ?>"><?php echo sg_e($sgPost['title']); ?></a></h3>
        <div class="sg-feature-meta">
          <span class="is-date"><?php echo sg_icon('clock'); ?><time datetime="<?php echo sg_e($sgPost['date']); ?>"><?php echo sg_e($sgAgo !== '' ? '发表于 ' . $sgAgo : '发表于 ' . $sgPost['date']); ?></time></span>
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

  <?php if ($sgTotal === 0) : ?>
  <p class="sg-filter-empty">这个频段没有接收到任何信号，换个关键词或标签试试。</p>
  <?php endif; ?>

  <!-- 服务端分页条（每页 9 篇）：真实 <a> 链接，PJAX 可接管，无 JS 也能翻页 -->
  <?php if ($sgTotalPages > 1) : ?>
  <nav class="sg-page-nav" data-sg-page-nav aria-label="知识档案分页">
    <?php
      $sgPrev = $sgCurrentPage > 1 ? $sgCurrentPage - 1 : 0;
      $sgNext = $sgCurrentPage < $sgTotalPages ? $sgCurrentPage + 1 : 0;
    ?>
    <?php if ($sgPrev) : ?>
    <a class="sg-page-btn sg-page-btn--nav" href="<?php echo sg_e($sgUrl($sgPrev)); ?>" rel="prev" aria-label="上一页"><?php echo sg_icon('chevron-left'); ?></a>
    <?php else : ?>
    <span class="sg-page-btn sg-page-btn--nav is-disabled" aria-disabled="true" aria-label="上一页"><?php echo sg_icon('chevron-left'); ?></span>
    <?php endif; ?>

    <?php for ($sgP = 1; $sgP <= $sgTotalPages; $sgP++) : ?>
    <?php if ($sgP === $sgCurrentPage) : ?>
    <span class="sg-page-btn is-active" aria-current="page" aria-label="第 <?php echo $sgP; ?> 页"><?php echo $sgP; ?></span>
    <?php else : ?>
    <a class="sg-page-btn" href="<?php echo sg_e($sgUrl($sgP)); ?>" aria-label="第 <?php echo $sgP; ?> 页"><?php echo $sgP; ?></a>
    <?php endif; ?>
    <?php endfor; ?>

    <?php if ($sgNext) : ?>
    <a class="sg-page-btn sg-page-btn--nav" href="<?php echo sg_e($sgUrl($sgNext)); ?>" rel="next" aria-label="下一页"><?php echo sg_icon('chevron-right'); ?></a>
    <?php else : ?>
    <span class="sg-page-btn sg-page-btn--nav is-disabled" aria-disabled="true" aria-label="下一页"><?php echo sg_icon('chevron-right'); ?></span>
    <?php endif; ?>
  </nav>
  <p class="sg-page-status" aria-live="polite">第 <?php echo $sgCurrentPage; ?> / <?php echo $sgTotalPages; ?> 页</p>
  <?php endif; ?>

  <?php if (!$sgAllPosts) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有研究记录</h1>
    <p>发布第一篇文章后，这里会自动按时间归档。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
