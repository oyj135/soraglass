<?php
/**
 * 顶部导航 + 移动端半圆盘 + 抽屉。
 * 高亮规则：首页 → index；独立页 → 缩略名；归档/搜索 → 归档页（timeline）。
 *
 * 「碎语」是合并后的统一入口：后台若已建 notes 独立页就用它自己的标题与链接，
 * 没建则退到 chatter / moments 这两个兼容页（模板会渲染同一份碎语流），
 * 保证任何时候都只有一个入口、且不会指向死链。
 */
$sgActive = sg_current_slug($this);
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgBrandTitle = sg_opt('sgNavTitle', (string) $this->options->title);
$sgBrandSuffix = sg_opt('sgNavSuffix', 'の');
$sgBrandAfter = sg_opt('sgNavAfter', '空璃');

/* 先把独立页收成数组：要在遍历过程中插入「碎语」，不能边查边输出。 */
$sgPages = [];
$this->widget('Widget_Contents_Page_List')->to($sgNavPages);
while ($sgNavPages->next()) {
    ob_start();
    $sgNavPages->permalink();
    $sgPages[] = [
        'slug'  => (string) $sgNavPages->slug,
        'title' => (string) $sgNavPages->title,
        'url'   => trim((string) ob_get_clean()),
    ];
}

$sgMergedSlugs = ['chatter', 'moments'];   // 合并进「碎语」的旧页面
$sgHiddenSlugs = array_merge(['tree'], $sgMergedSlugs);

$sgNotesEntry = null;
foreach ($sgPages as $sgPage) {
    if ($sgPage['slug'] === 'notes') {
        $sgNotesEntry = $sgPage;
        break;
    }
}
if ($sgNotesEntry === null) {
    /* 没有 notes 页就复用旧页面地址，标题统一叫「碎语」 */
    foreach ($sgPages as $sgPage) {
        if (in_array($sgPage['slug'], $sgMergedSlugs, true)) {
            $sgNotesEntry = ['slug' => $sgPage['slug'], 'title' => '碎语', 'url' => $sgPage['url']];
            break;
        }
    }
}
if ($sgNotesEntry === null) {
    $sgNotesEntry = ['slug' => 'notes', 'title' => '碎语', 'url' => sg_page_url('notes')];
}

$sgNavItems = [];
$sgNotesDone = false;
foreach ($sgPages as $sgPage) {
    if (in_array($sgPage['slug'], $sgHiddenSlugs, true)) {
        /* 在第一个被合并的旧页位置插入「碎语」，保持原来的排序位置 */
        if (!$sgNotesDone && in_array($sgPage['slug'], $sgMergedSlugs, true)) {
            $sgNavItems[] = $sgNotesEntry;
            $sgNotesDone = true;
        }
        continue;
    }
    if ($sgPage['slug'] === 'notes') {
        /* 页面自己就是统一入口：直接用它的条目，只输出一次 */
        if (!$sgNotesDone) {
            $sgNavItems[] = $sgNotesEntry;
            $sgNotesDone = true;
        }
        continue;
    }
    $sgNavItems[] = $sgPage;
}
if (!$sgNotesDone) {
    $sgNavItems[] = $sgNotesEntry;
}

/* 高亮：「碎语」相关页面（notes / 兼容旧页 / 旧页被访问时）统一高亮这一条 */
$sgActiveKey = in_array($sgActive, array_merge(['notes'], $sgMergedSlugs), true) ? $sgNotesEntry['slug'] : $sgActive;

/* 入口区分（2026-11-14 第二阶段）：评审指出「归档」和「知识档案」对新访客
   区别不直观。按 slug 挂 title 提示，悬停可见；不动页面标题、不加设置、不改 slug。
   未列出的 slug 不受影响。 */
$sgNavHints = [
    'archives' => '按时间翻阅全部文章',
    'timeline' => '按主题整理的知识条目',
];

/* 导航图标（2026-11-17，对齐源站彩色图标的观感）：每个入口一个专属色，
   用内联 color 控制（sg_icon 是 stroke:currentColor）。 */
/* 导航图标（2026-11-21 定档）：改用源站 iconfont CDN 多色 symbol（header.php 的 4 个 async JS 注入），
   每枚内嵌独立品牌色、真彩色质感，与全站语义图标同源；原先是 sg_icon + 内联色（假彩色）。 */
$sgNavIconMap = [
    'archives'  => 'icon-guidang1',
    'timeline'  => 'icon--article',
    'time'      => 'icon-lishi',
    'notes'     => 'icon-liuyan',
    'chatter'   => 'icon-liuyan',
    'moments'   => 'icon-liuyan',
    'music'     => 'icon-yinle',
    'photowall' => 'icon-tupian',
    'gallery'   => 'icon-tupian',
    'friends'   => 'icon-pengyouquan',
    'about'     => 'icon-shejiaoxinxi',
    'projects'  => 'icon-codeoptimization',
];
$sgNavIcon = static function (string $sgSlug) use ($sgNavIconMap): string {
    $sgSymbol = $sgNavIconMap[$sgSlug] ?? null;
    if ($sgSymbol === null) {
        return '';
    }
    return '<span class="sg-nav-ico"><svg aria-hidden="true"><use href="#' . $sgSymbol . '"></use></svg></span>';
};

/* P1-11：导航分组——条目过多时尾部收进「更多」下拉，避免顶栏溢出换行。
   顶栏最多 8 个位置：首页 + 6 个页面 + 「更多」；移动端圆盘不受影响，仍平铺全部。 */
$sgNavMax = 8;
$sgNavPrimary = $sgNavItems;
$sgNavMore = [];
if (count($sgNavItems) + 1 > $sgNavMax) {
    $sgKeep = $sgNavMax - 2;
    $sgNavPrimary = array_slice($sgNavItems, 0, $sgKeep);
    $sgNavMore = array_slice($sgNavItems, $sgKeep);
}
$sgMoreActive = false;
foreach ($sgNavMore as $sgMorePage) {
    if ($sgActiveKey === $sgMorePage['slug']) {
        $sgMoreActive = true;
        break;
    }
}
?>
<header class="sg-nav" data-sg-nav>
  <div class="sg-nav-inner">
    <a class="sg-brand" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_e($sgBrandTitle); ?><span class="sg-brand-accent"><?php echo sg_e($sgBrandSuffix); ?></span><?php echo sg_e($sgBrandAfter); ?></a>
    <?php /* 滚动后顶栏中央显示当前页名（2026-11-19 A1，对齐源站 #name-container）：
       文字由 JS 填充（当前页标题），点击回到顶部；悬停露出「回到顶部」提示。 */ ?>
    <a class="sg-nav-page" data-sg-nav-page href="#" data-tip="回到顶部" hidden></a>
    <nav class="sg-nav-links" aria-label="主导航">
      <a class="sg-nav-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>"<?php echo $sgActive === 'index' ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgHome); ?>"><span class="sg-nav-ico"><svg aria-hidden="true"><use href="#icon-home"></use></svg></span>首页<?php if ($sgActive === 'index') : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php foreach ($sgNavPrimary as $sgPage) : ?>
      <a class="sg-nav-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage['slug']); ?><?php echo sg_e($sgPage['title']); ?><?php if ($sgActiveKey === $sgPage['slug']) : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php endforeach; ?>
      <?php if ($sgNavMore) : ?>
      <div class="sg-nav-more<?php echo $sgMoreActive ? ' is-active' : ''; ?>" data-sg-nav-more>
        <button class="sg-nav-link sg-nav-more-btn" type="button" aria-expanded="false" aria-haspopup="true">
          更多<?php echo sg_icon_color('chevron-down'); ?>
          <?php if ($sgMoreActive) : ?><span class="sg-nav-dot"></span><?php endif; ?>
        </button>
        <div class="sg-nav-more-menu" role="menu">
          <?php foreach ($sgNavMore as $sgPage) : ?>
          <a class="sg-nav-more-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> role="menuitem" href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage['slug']); ?><?php echo sg_e($sgPage['title']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </nav>
  </div>
</header>

<button class="sg-disc" type="button" data-sg-sheet-open aria-label="打开导航" aria-expanded="false" aria-controls="sg-sheet">
  <span class="sg-disc-label" aria-hidden="true">菜单</span>
  <span class="sg-disc-dots" aria-hidden="true"><i></i><i></i><i></i></span>
</button>

<dialog class="sg-sheet" id="sg-sheet" data-sg-sheet aria-labelledby="sg-sheet-title">
  <div class="sg-sheet-panel">
    <div class="sg-sheet-head">
      <h2 id="sg-sheet-title">站点导航</h2>
      <button class="sg-sheet-close" type="button" data-sg-sheet-close aria-label="关闭导航" autofocus>关闭</button>
    </div>
    <a class="sg-sheet-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>"<?php echo $sgActive === 'index' ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgHome); ?>"><span class="sg-nav-ico"><svg aria-hidden="true"><use href="#icon-home"></use></svg></span>首页</a>
    <?php foreach ($sgNavItems as $sgPage) : ?>
    <a class="sg-sheet-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage['slug']); ?><?php echo sg_e($sgPage['title']); ?></a>
    <?php endforeach; ?>
    <hr class="sg-divider">
    <button class="sg-sheet-link" type="button" data-sg-theme-toggle><?php echo sg_icon_color('sun', 'sg-i-sun'); ?><?php echo sg_icon_color('moon', 'sg-i-moon'); ?> 切换昼夜</button>
  </div>
</dialog>
