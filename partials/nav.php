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
?>
<header class="sg-nav" data-sg-nav>
  <div class="sg-nav-inner">
    <a class="sg-brand" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_e($sgBrandTitle); ?><span class="sg-brand-accent"><?php echo sg_e($sgBrandSuffix); ?></span><?php echo sg_e($sgBrandAfter); ?></a>
    <nav class="sg-nav-links" aria-label="主导航">
      <a class="sg-nav-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>"<?php echo $sgActive === 'index' ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgHome); ?>">首页<?php if ($sgActive === 'index') : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php foreach ($sgNavItems as $sgPage) : ?>
      <a class="sg-nav-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"><?php echo sg_e($sgPage['title']); ?><?php if ($sgActiveKey === $sgPage['slug']) : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
  </div>
</header>

<button class="sg-disc" type="button" data-sg-sheet-open aria-label="打开导航">
  <span class="sg-disc-dots"><i></i><i></i><i></i></span>
</button>

<div class="sg-sheet" data-sg-sheet aria-hidden="true">
  <div class="sg-sheet-panel">
    <a class="sg-sheet-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>"<?php echo $sgActive === 'index' ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgHome); ?>">首页</a>
    <?php foreach ($sgNavItems as $sgPage) : ?>
    <a class="sg-sheet-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"><?php echo sg_e($sgPage['title']); ?></a>
    <?php endforeach; ?>
    <hr class="sg-divider">
    <button class="sg-sheet-link" type="button" data-sg-theme-toggle><?php echo sg_icon('sun', 'sg-i-sun'); ?><?php echo sg_icon('moon', 'sg-i-moon'); ?> 切换昼夜</button>
  </div>
</div>
