<?php
/**
 * 顶部导航 + 移动端半圆盘 + 抽屉。
 * 高亮规则：首页 → index；独立页 → 缩略名；归档 → 归档页（timeline）。
 *
 * 「碎语」是合并后的统一入口（2026-11-22 第五十八轮重写）：slug 为 notes 或模板为碎语模板
 * （notes.php）的页面都视为统一入口，按它在页面层级里的位置自然显示（父页组内或顶层直链）；
 * 旧 chatter / moments 地址仅作兼容，不再生成第二份导航条目；真碎语页不存在时才回落兼容条目，
 * 保证任何时候都只有一个入口、且不会指向死链。
 *
 * 分组导航（2026-11-22，参考 miraii.cn 的分组顶栏）：
 *   主路径 = Typecho 原生父子页面 —— 后台独立页选择父级后，父页自动变成
 *   顶栏「组按钮」（组名 = 父页标题），子页收进横排下拉面板，父页自身作为面板首项；
 *   可选覆盖 = 后台 sgNavGroups JSON（组名 / 图标 emoji 或 iconfont / slug 列表），
 *   填了以 JSON 分组优先；两者都未配置 → 回落现行单层导航（+「更多」兜底）。
 *   「首页」恒为独立直链；顶栏超限时尾部直链自动收进「更多」组。
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
        'cid'     => (int) $sgNavPages->cid,
        'slug'    => (string) $sgNavPages->slug,
        'title'   => (string) $sgNavPages->title,
        'url'     => trim((string) ob_get_clean()),
        'template' => (string) $sgNavPages->template,
        'parent'  => (int) $sgNavPages->parent,
    ];
}

$sgMergedSlugs = ['chatter', 'moments'];   // 合并进「碎语」的旧页面
$sgHiddenSlugs = array_merge(['tree'], $sgMergedSlugs);

/* 碎语统一入口（2026-11-22 第五十八轮重写）：slug 为 notes 或模板为碎语模板（notes.php）
   的页面都视为统一入口 —— 真碎语页按它在页面层级里的位置自然显示（父页组内或顶层直链），
   旧 chatter/moments 地址仅作兼容、不再生成第二份导航条目（此前新建碎语页 slug 不是
   notes 时，兼容条目会重复出现一份「碎语」）。真碎语页不存在时才回落兼容/合成条目。 */
$sgNotesEntry = null;
$sgNotesIsReal = false;
foreach ($sgPages as $sgPage) {
    $sgPageTpl = preg_replace('/\.php$/i', '', (string) $sgPage['template']);
    if ($sgPage['slug'] === 'notes' || $sgPageTpl === 'notes') {
        $sgNotesEntry = $sgPage;
        $sgNotesIsReal = true;
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
foreach ($sgPages as $sgPage) {
    /* 旧碎语地址（chatter/moments）与 tree 永不出现在导航；
       真碎语页与其它页面一样，按自己的层级位置自然进入导航（不在这里特殊插入）。 */
    if (in_array($sgPage['slug'], $sgHiddenSlugs, true)) {
        continue;
    }
    $sgNavItems[] = $sgPage;
}
if (!$sgNotesIsReal) {
    $sgNavItems[] = $sgNotesEntry; // 无真实碎语页时，兼容条目放在末尾
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

/* 导航图标（2026-11-21 定档）：改用源站 iconfont CDN 多色 symbol（header.php 的 4 个 async JS 注入），
   每枚内嵌独立品牌色、真彩色质感，与全站语义图标同源；原先是 sg_icon + 内联色（假彩色）。 */
$sgNavIconMap = [
    /* 书签盒（原网址导航）：模板名 / slug / 改名前后的标题都覆盖，保证后台改名后图标不丢 */
    'page-nav'  => 'icon-lianjie',
    'nav'       => 'icon-lianjie',
    '书签盒'    => 'icon-lianjie',
    '网址导航'  => 'icon-lianjie',
    'archives'  => 'icon-guidang1',
    'timeline'  => 'icon--article',
    'time'      => 'icon-lishi',
    /* 标题兑底：Page List 行的 template/slug 在部分环境下取不到（用户页面 slug 可能改过），
       页面标题是最稳定的最后一道匹配。 */
    '时光机'    => 'icon-lishi',
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
$sgNavIcon = static function (array $sgPage) use ($sgNavIconMap): string {
    /* 图标按 模板名（contents.template 去掉 .php）→ slug → 页面标题 三级匹配 ——
       模板/slug 在部分环境下取不到，标题是用户在导航上直接可见的稳定标识。 */
    $sgTpl = preg_replace('/\.php$/i', '', (string) ($sgPage['template'] ?? ''));
    $sgSymbol = $sgNavIconMap[$sgTpl]
        ?? $sgNavIconMap[$sgPage['slug']]
        ?? $sgNavIconMap[trim((string) $sgPage['title'])]
        ?? null;
    if ($sgSymbol === null) {
        return '';
    }
    return '<span class="sg-nav-ico"><svg aria-hidden="true"><use href="#' . $sgSymbol . '"></use></svg></span>';
};

/* ---------- 分组导航（2026-11-22） ---------- */

/* 可选覆盖配置：sgNavGroups JSON（组名 / 图标 / slug 列表），解析失败按未配置处理 */
$sgNavCfgRaw = trim(sg_opt('sgNavGroups', ''));
$sgNavCfg = [];
if ($sgNavCfgRaw !== '') {
    $sgNavCfgDecoded = json_decode($sgNavCfgRaw, true);
    if (is_array($sgNavCfgDecoded)) {
        $sgNavCfg = $sgNavCfgDecoded;
    }
}

/* cid 索引：原生父子聚合用 */
$sgByCid = [];
foreach ($sgPages as $sgPage) {
    if ($sgPage['cid'] > 0) {
        $sgByCid[$sgPage['cid']] = $sgPage;
    }
}
$sgChildrenOf = [];
foreach ($sgPages as $sgPage) {
    if ($sgPage['parent'] > 0 && isset($sgByCid[$sgPage['parent']])) {
        $sgChildrenOf[$sgPage['parent']][] = $sgPage;
    }
}

/* 组装顶栏渲染序列：条目 = ['type' => link|group, ...]
   JSON 配置优先（组序 = 配置序）；否则按原生父子页聚合（组出现在父页的排序位置）。 */
$sgAssignedSlugs = [];
$sgNavSeq = [];
if ($sgNavCfg) {
    foreach ($sgNavCfg as $sgCfgGroup) {
        if (!is_array($sgCfgGroup)) {
            continue;
        }
        $sgCfgName = trim((string) ($sgCfgGroup['name'] ?? ''));
        $sgCfgItems = [];
        foreach ((array) ($sgCfgGroup['pages'] ?? []) as $sgCfgSlug) {
            foreach ($sgNavItems as $sgItem) {
                if ($sgItem['slug'] === (string) $sgCfgSlug) {
                    $sgCfgItems[] = $sgItem;
                    $sgAssignedSlugs[$sgItem['slug']] = true;
                    break;
                }
            }
        }
        if ($sgCfgName === '' || !$sgCfgItems) {
            continue;
        }
        $sgNavSeq[] = ['type' => 'group', 'name' => $sgCfgName, 'icon' => trim((string) ($sgCfgGroup['icon'] ?? '')), 'items' => $sgCfgItems];
    }
    foreach ($sgNavItems as $sgItem) {
        if (!isset($sgAssignedSlugs[$sgItem['slug']])) {
            $sgNavSeq[] = ['type' => 'link', 'item' => $sgItem];
        }
    }
} else {
    foreach ($sgNavItems as $sgItem) {
        /* 唯一的合成条目「碎语」没有 cid/parent 键，全部用 ?? 归零防 PHP 8 未定义键告警 */
        if ((int) ($sgItem['cid'] ?? 0) > 0 && isset($sgChildrenOf[(int) $sgItem['cid']])) {
            /* 父页 → 组按钮；子页收进面板（父页自身不再重复列出，组按钮已是它的入口）。
               组图标回落第一个子页的彩色图标。 */
            $sgGroupChildren = $sgChildrenOf[(int) $sgItem['cid']];
            foreach ($sgGroupChildren as $sgChild) {
                $sgAssignedSlugs[$sgChild['slug']] = true;
            }
            $sgNavSeq[] = ['type' => 'group', 'name' => $sgItem['title'], 'icon' => '', 'items' => $sgGroupChildren];
            continue;
        }
        if ((int) ($sgItem['parent'] ?? 0) > 0 && isset($sgByCid[(int) $sgItem['parent']])) {
            continue; // 子页已归组，顶层不再输出
        }
        $sgNavSeq[] = ['type' => 'link', 'item' => $sgItem];
    }
}

/* 顶栏超限兜底：首页 + 序列 > 8 个位置时，从尾部摘直链进「更多」组（组按钮不动） */
$sgNavMax = 8;
$sgNavMoreItems = [];
while (count($sgNavSeq) + 1 > $sgNavMax) {
    $sgPopped = false;
    for ($i = count($sgNavSeq) - 1; $i >= 0; $i--) {
        if ($sgNavSeq[$i]['type'] === 'link') {
            $sgNavMoreItems[] = $sgNavSeq[$i]['item'];
            array_splice($sgNavSeq, $i, 1);
            $sgPopped = true;
            break;
        }
    }
    if (!$sgPopped) {
        break; // 全是组按钮时不再强摘，顶栏允许被组撑宽
    }
}
$sgNavMoreItems = array_reverse($sgNavMoreItems);
$sgMoreActive = false;
foreach ($sgNavMoreItems as $sgMorePage) {
    if ($sgActiveKey === $sgMorePage['slug']) {
        $sgMoreActive = true;
        break;
    }
}

/* 组按钮图标：JSON 图标（emoji 或 iconfont symbol）优先，回落组内第一项自己的图标 */
$sgGroupIcon = static function (array $sgSeqEntry) use ($sgNavIcon): string {
    $sgIcon = trim((string) ($sgSeqEntry['icon'] ?? ''));
    if ($sgIcon === '') {
        return $sgNavIcon($sgSeqEntry['items'][0] ?? []);
    }
    if (strpos($sgIcon, 'icon-') === 0) {
        return '<span class="sg-nav-ico"><svg aria-hidden="true"><use href="#' . sg_e($sgIcon) . '"></use></svg></span>';
    }
    return '<span class="sg-nav-ico sg-nav-gemoji">' . sg_e($sgIcon) . '</span>';
};

/* 组内当前页判定：当前页在组里 → 组按钮高亮 */
$sgGroupHasActive = static function (array $sgSeqEntry) use ($sgActiveKey): bool {
    foreach ($sgSeqEntry['items'] as $sgItem) {
        if ($sgActiveKey === $sgItem['slug']) {
            return true;
        }
    }
    return false;
};
?>
<header class="sg-nav" data-sg-nav>
  <div class="sg-nav-inner">
    <a class="sg-brand" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_e($sgBrandTitle); ?><span class="sg-brand-accent"><?php echo sg_e($sgBrandSuffix); ?></span><?php echo sg_e($sgBrandAfter); ?></a>
    <?php /* 滚动后顶栏中央显示当前页名（2026-11-19 A1，对齐源站 #name-container）：
       文字由 JS 填充（当前页标题），点击回到顶部；悬停露出「回到顶部」提示。 */ ?>
    <a class="sg-nav-page" data-sg-nav-page href="#" data-tip="回到顶部" hidden></a>
    <nav class="sg-nav-links" aria-label="主导航">
      <a class="sg-nav-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>"<?php echo $sgActive === 'index' ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgHome); ?>"><span class="sg-nav-ico"><svg aria-hidden="true"><use href="#icon-home"></use></svg></span>首页<?php if ($sgActive === 'index') : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php foreach ($sgNavSeq as $sgSeqEntry) : ?>
      <?php if ($sgSeqEntry['type'] === 'link') : $sgPage = $sgSeqEntry['item']; ?>
      <a class="sg-nav-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?><?php if ($sgActiveKey === $sgPage['slug']) : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php else : ?>
      <div class="sg-nav-group<?php echo $sgGroupHasActive($sgSeqEntry) ? ' is-active-group' : ''; ?>" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">
          <?php echo $sgGroupIcon($sgSeqEntry); ?><?php echo sg_e($sgSeqEntry['name']); ?><?php echo sg_icon_color('chevron-down'); ?>
        </button>
        <div class="sg-nav-group-menu" role="menu">
          <?php foreach ($sgSeqEntry['items'] as $sgPage) : ?>
          <a class="sg-nav-group-item<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> role="menuitem" href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
      <?php if ($sgNavMoreItems) : ?>
      <div class="sg-nav-group<?php echo $sgMoreActive ? ' is-active-group' : ''; ?>" data-sg-nav-group>
        <button class="sg-nav-link sg-nav-group-btn" type="button" aria-expanded="false" aria-haspopup="true">
          更多<?php echo sg_icon_color('chevron-down'); ?>
        </button>
        <div class="sg-nav-group-menu" role="menu">
          <?php foreach ($sgNavMoreItems as $sgPage) : ?>
          <a class="sg-nav-group-item<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> role="menuitem" href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?></a>
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
    <?php foreach ($sgNavSeq as $sgSeqEntry) : ?>
    <?php if ($sgSeqEntry['type'] === 'link') : $sgPage = $sgSeqEntry['item']; ?>
    <a class="sg-sheet-link<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?></a>
    <?php else : ?>
    <p class="sg-sheet-group-name"><?php echo sg_e($sgSeqEntry['name']); ?></p>
    <?php foreach ($sgSeqEntry['items'] as $sgPage) : ?>
    <a class="sg-sheet-link sg-sheet-sub<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($sgNavMoreItems) : ?>
    <p class="sg-sheet-group-name">更多</p>
    <?php foreach ($sgNavMoreItems as $sgPage) : ?>
    <a class="sg-sheet-link sg-sheet-sub<?php echo $sgActiveKey === $sgPage['slug'] ? ' is-active' : ''; ?>"<?php echo $sgActiveKey === $sgPage['slug'] ? ' aria-current="page"' : ''; ?> href="<?php echo sg_e($sgPage['url']); ?>"<?php echo isset($sgNavHints[$sgPage['slug']]) ? ' title="' . sg_e($sgNavHints[$sgPage['slug']]) . '"' : ''; ?>><?php echo $sgNavIcon($sgPage); ?><?php echo sg_e($sgPage['title']); ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
    <hr class="sg-divider">
    <button class="sg-sheet-link" type="button" data-sg-theme-toggle><?php echo sg_icon_color('sun', 'sg-i-sun'); ?><?php echo sg_icon_color('moon', 'sg-i-moon'); ?> 切换昼夜</button>
  </div>
</dialog>
