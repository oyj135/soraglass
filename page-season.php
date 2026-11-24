<?php
/**
 * 岁候 —— 二十四节气 × 黄道十二宫（一条黄道，两种读法）
 *
 * 二十四节气把黄道 24 等分（15°），黄道十二宫 12 等分（30°），两者都以春分为 0°，
 * 所以宫界必然落在偶数序节气（中气）上：gong = floor(i / 2)，没有任何映射表。
 *
 * 参数互斥（评审稿 §6.3），同一链接里只出现一个选择参数：
 *   ?t=lidong   该节气 15 天
 *   ?g=scorpio  该宫 30 天
 *   ?d=2026-11-22 该日所在的候（5 天），自动推 ?y=
 *   ?y=2026     公历年，可与任一个共存
 *
 * 数据层在 lib/season.php，节气日期表由 scripts/gen-season.mjs 生成
 * （data/season-terms.php，线上不跑 Node，生成物随包发布）。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}

$sgSNow = sg_season_now();
$sgSThisYear = (int) ($sgSNow['year'] ?? (int) date('Y'));

/* 参数：非法值一律退回默认，不报错 */
$sgSYear = (int) ($_GET['y'] ?? 0);
$sgST = trim((string) ($_GET['t'] ?? ''));
$sgSG = trim((string) ($_GET['g'] ?? ''));
$sgSD = trim((string) ($_GET['d'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sgSD)) {
    $sgSD = '';
}
/* ?d= 自动推年份（1 月 1 日至小寒前归上一年） */
if ($sgSD !== '' && !isset($_GET['y'])) {
    $sgSDYear = sg_season_year_of($sgSD);
    if ($sgSDYear > 0) {
        $sgSYear = $sgSDYear;
    }
}
if ($sgSYear < 1900 || $sgSYear > 2100) {
    $sgSYear = $sgSThisYear;
}

$sgSTerms = sg_season_terms($sgSYear);
$sgSGongs = sg_season_gongs();
$sgSHouNames = ['一候', '二候', '三候'];

/* ---------- 选中态：参数即状态，不另立状态机 ---------- */
$sgSMode = 'term';
$sgSTIndex = $sgST !== '' ? sg_season_index($sgST) : -1;
$sgSGIndex = $sgSG !== '' ? sg_season_gong_index($sgSG) : -1;
$sgSHouIndex = -1;
$sgSFrom = 0;
$sgSTo = 0;
$sgSTerm = 0;

if (!$sgSTerms) {
    /* 年份越界或表缺失：整页空态，不渲染环 */
    $sgSMode = 'none';
} elseif ($sgSTIndex >= 0) {
    $sgSTerm = $sgSTIndex;
    $sgSFrom = (int) $sgSTerms[$sgSTIndex]['ts'];
    $sgSTo = (int) $sgSTerms[$sgSTIndex]['end'];
} elseif ($sgSGIndex >= 0) {
    $sgSMode = 'gong';
    $sgSTerm = $sgSGIndex * 2;
    $sgSFrom = (int) $sgSTerms[$sgSTerm]['ts'];
    $sgSTo = (int) $sgSTerms[$sgSTerm + 1]['end'];
} elseif ($sgSD !== '' && ($sgSHouRange = sg_season_hou_range($sgSYear, $sgSD))) {
    $sgSMode = 'day';
    $sgSTerm = (int) $sgSHouRange['term'];
    $sgSHouIndex = (int) $sgSHouRange['houIndex'];
    $sgSFrom = (int) $sgSHouRange['from'];
    $sgSTo = (int) $sgSHouRange['to'];
} else {
    /* 当令：公历年与今年相同时用今日所在节气，否则沿用同一节气序 */
    $sgSTerm = $sgSYear === $sgSThisYear && $sgSNow ? (int) $sgSNow['term'] : (int) ($sgSNow['term'] ?? 19);
    if (!isset($sgSTerms[$sgSTerm])) {
        $sgSTerm = 19;
    }
    $sgSFrom = (int) $sgSTerms[$sgSTerm]['ts'];
    $sgSTo = (int) $sgSTerms[$sgSTerm]['end'];
}

$sgSCurTerm = $sgSTerms[$sgSTerm] ?? [];
$sgSCurGong = $sgSGongs[(int) ($sgSCurTerm['gong'] ?? 0)] ?? $sgSGongs[0];
$sgSCounts = $sgSTerms ? sg_season_buckets($sgSYear) : ['terms' => [], 'gongs' => [], 'notes' => [], 'from' => 0, 'to' => 0, 'skipped' => 0];

/* ---------- 文档标题与 canonical（header.php 读取，模板不能事后改 <title>） ---------- */
$sgSDateRange = static function (array $from, array $to): string {
    $a = date('m.d', sg_date_ts((int) $from['ts']));
    /* end 是下一节气的 00:00（开区间），显示给读者的末日要退一天 */
    $b = date('m.d', sg_date_ts((int) $to['end'] - 86400));
    return $a . ' – ' . $b;
};

if ($sgSMode === 'none') {
    $GLOBALS['SG_DOC_TITLE'] = '岁候 · 日子的两种读法';
    $GLOBALS['SG_CANONICAL'] = sg_season_url(['y' => $sgSYear]);
} elseif ($sgSMode === 'gong') {
    $sgSGongTerms = $sgSTerms[$sgSTerm]['name'] . ' · ' . $sgSTerms[$sgSTerm + 1]['name'];
    $GLOBALS['SG_DOC_TITLE'] = $sgSCurGong[1] . '宫 · ' . $sgSGongTerms . ' · 岁候';
    $GLOBALS['SG_CANONICAL'] = sg_season_url(['g' => $sgSCurGong[0], 'y' => $sgSYear]);
} elseif ($sgSMode === 'day') {
    $sgSHouName = $sgSHouNames[$sgSHouIndex] ?? '一候';
    $GLOBALS['SG_DOC_TITLE'] = $sgSCurTerm['name'] . ' · ' . $sgSHouName . ' · 岁候';
    /* ?d= 的 canonical 指向所属 ?t=（评审稿 §6.3） */
    $GLOBALS['SG_CANONICAL'] = sg_season_url(['t' => $sgSCurTerm['slug'], 'y' => $sgSYear]);
} else {
    $GLOBALS['SG_DOC_TITLE'] = $sgSCurTerm['name'] . ' · ' . $sgSCurGong[1] . '宫 · 岁候';
    $GLOBALS['SG_CANONICAL'] = sg_season_url(['t' => $sgSCurTerm['slug'], 'y' => $sgSYear]);
}

$this->need('header.php');

/* ---------- 区间内容 ---------- */
$sgSRange = $sgSMode === 'none'
    ? ['posts' => [], 'notes' => [], 'photos' => [], 'empty' => true, 'nearPrev' => [], 'nearNext' => []]
    : sg_season_range($sgSFrom, $sgSTo, $sgSYear);

/* 按「节气 × 候」分组：展陈区的小标题是「立冬 · 二候 · 地始冻」，不是读数 */
$sgSGroups = [];
foreach ($sgSRange['posts'] as $sgPost) {
    $sgKey = $sgPost['term'] . '-' . $sgPost['houIndex'];
    if (!isset($sgSGroups[$sgKey])) {
        $sgT = $sgSTerms[$sgPost['term']];
        $sgSGroups[$sgKey] = [
            'label' => $sgT['name'] . ' · ' . ($sgSHouNames[$sgPost['houIndex']] ?? '一候') . ' · ' . $sgT['hou'][$sgPost['houIndex']],
            'order' => $sgPost['term'] * 10 + $sgPost['houIndex'],
            'posts' => [],
        ];
    }
    $sgSGroups[$sgKey]['posts'][] = $sgPost;
}
uasort($sgSGroups, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);

/* ---------- 轮盘数据 ---------- */
$sgWheelTerms = [];
foreach ($sgSTerms as $sgI => $sgT) {
    $sgCount = count($sgSCounts['terms'][$sgI] ?? []);
    $sgIsCurrent = $sgSYear === $sgSThisYear && $sgSNow && (int) $sgSNow['term'] === $sgI;
    $sgWheelTerms[] = [
        'i' => $sgI,
        'slug' => $sgT['slug'],
        'name' => $sgT['name'],
        'gong' => $sgT['gong'],
        'gongName' => $sgSGongs[$sgT['gong']][1],
        'lon' => $sgT['lon'],
        'date' => date('m.d', sg_date_ts((int) $sgT['ts'])),
        'count' => $sgCount,
        'current' => $sgIsCurrent,
        'selected' => $sgSMode === 'term' && $sgI === $sgSTerm,
        'sub' => $sgIsCurrent && $sgSNow
            ? $sgSNow['nextDays'] . ' 天后是' . $sgSNow['nextName']
            : $sgSDateRange($sgT, $sgT),
        'url' => sg_season_url(['t' => $sgT['slug'], 'y' => $sgSYear]),
    ];
}
$sgWheelGongs = [];
foreach ($sgSGongs as $sgGI => $sgG) {
    $sgSpanTerms = ($sgSTerms[$sgGI * 2]['name'] ?? '') . ' – ' . ($sgSTerms[$sgGI * 2 + 1]['name'] ?? '');
    $sgWheelGongs[] = [
        'i' => $sgGI,
        'name' => $sgG[1],
        'span' => $sgSpanTerms,
        'count' => count($sgSCounts['gongs'][$sgGI] ?? []),
        'selected' => $sgSMode === 'gong' && $sgGI === $sgSGIndex,
        'url' => sg_season_url(['g' => $sgG[0], 'y' => $sgSYear]),
    ];
}

/* 中心读数：只给两个数（§8.3 第 3 条） */
if ($sgSMode === 'gong') {
    $sgCenterName = $sgSCurGong[1] . '宫';
    $sgCenterSub = $sgSTerms[$sgSTerm]['name'] . ' – ' . $sgSTerms[$sgSTerm + 1]['name'] . ' · ' . count($sgSCounts['gongs'][$sgSGIndex] ?? []) . ' 篇';
} elseif ($sgSMode === 'day') {
    $sgCenterName = $sgSCurTerm['name'] . ' · ' . ($sgSHouNames[$sgSHouIndex] ?? '一候');
    $sgCenterSub = $sgSCurTerm['hou'][$sgSHouIndex] . ' · ' . $sgSDateRange(['ts' => $sgSFrom], ['end' => $sgSTo]);
} else {
    $sgCenterName = $sgSCurTerm['name'] . ' · ' . $sgSCurGong[1] . '宫';
    /* 黄经与「距下一节气」已移到舞台四角（第七十六轮），中心只留日期区间，不重复读数 */
    $sgCenterSub = $sgSDateRange($sgSCurTerm, $sgSCurTerm);
}

/* ---------- 对照表 ---------- */
$sgTableGongs = [];
foreach ($sgSGongs as $sgGI => $sgG) {
    $sgStart = $sgSTerms[$sgGI * 2] ?? [];
    $sgTableGongs[] = [
        'name' => $sgG[1],
        'startName' => (string) ($sgStart['name'] ?? ''),
        'startDate' => $sgStart ? date('m.d', sg_date_ts((int) $sgStart['ts'])) : '',
        'span' => $sgStart ? $sgStart['name'] . ' → ' . ($sgSTerms[$sgGI * 2 + 1]['name'] ?? '') : '',
        'count' => count($sgSCounts['gongs'][$sgGI] ?? []),
        'selected' => $sgSMode === 'gong' && $sgGI === $sgSGIndex,
        /* 第七十六轮：当令宫单独标一行，看年表时能立刻对上轮盘上亮着的那一段 */
        'now' => $sgSYear === $sgSThisYear && $sgSNow && (int) $sgSNow['gong'] === $sgGI,
        'url' => sg_season_url(['g' => $sgG[0], 'y' => $sgSYear]),
    ];
}
$sgTableTerms = [];
foreach ($sgSTerms as $sgI => $sgT) {
    $sgTableTerms[] = [
        'name' => $sgT['name'],
        'startDate' => date('m.d', sg_date_ts((int) $sgT['ts'])),
        'lon' => $sgT['lon'],
        'gongName' => $sgSGongs[$sgT['gong']][1],
        'count' => count($sgSCounts['terms'][$sgI] ?? []),
        'selected' => $sgSMode === 'term' && $sgI === $sgSTerm,
        'url' => sg_season_url(['t' => $sgT['slug'], 'y' => $sgSYear]),
    ];
}

/* ---------- 上一节气 / 下一节气 / 年份行 ---------- */
$sgSOrder = $sgSTerms ? sg_season_order($sgSYear) : [];
$sgSAt = array_search($sgSTerm, $sgSOrder, true);
$sgSPrevUrl = '';
$sgSNextUrl = '';
if ($sgSAt !== false) {
    if ($sgSAt > 0) {
        $sgSPrevUrl = sg_season_url(['t' => $sgSTerms[$sgSOrder[$sgSAt - 1]]['slug'], 'y' => $sgSYear]);
    } else {
        $sgSPrevTerms = sg_season_terms($sgSYear - 1);
        if ($sgSPrevTerms) {
            $sgSPrevUrl = sg_season_url(['t' => $sgSPrevTerms[18]['slug'], 'y' => $sgSYear - 1]);
        }
    }
    if ($sgSAt < count($sgSOrder) - 1) {
        $sgSNextUrl = sg_season_url(['t' => $sgSTerms[$sgSOrder[$sgSAt + 1]]['slug'], 'y' => $sgSYear]);
    } else {
        $sgSNextTerms = sg_season_terms($sgSYear + 1);
        if ($sgSNextTerms) {
            $sgSNextUrl = sg_season_url(['t' => $sgSNextTerms[19]['slug'], 'y' => $sgSYear + 1]);
        }
    }
}

/* 年份切换保留当前选择参数（§5 F5） */
$sgSYears = sg_season_years();
$sgSYearUrl = static function (int $y) use ($sgSMode, $sgSCurTerm, $sgSCurGong, $sgSGIndex, $sgSD): string {
    if ($sgSMode === 'gong') {
        return sg_season_url(['g' => $sgSCurGong[0], 'y' => $y]);
    }
    if ($sgSMode === 'day' && $sgSD !== '') {
        return sg_season_url(['t' => $sgSCurTerm['slug'] ?? '', 'y' => $y]);
    }
    return sg_season_url(['t' => $sgSCurTerm['slug'] ?? '', 'y' => $y]);
};
$sgSSub = sg_page_sub($this, '日子的两种读法');
$sgSShowGong = sg_opt('sgGongRing', 'gong') !== 'off';

/* ---------- 幕一：四角仪表读数（第七十六轮）----------
   三格恒描述「这一段」，只有第四格在「看的就是当令这一段」时切成「距下一节气」，
   这样 ?t / ?g / ?d / ?y 任何参数下四角都成立，不会出现「今天」与所选年份打架。
   读数不重复中心：黄经与「距下一节气」原本在中心副行，已让位到这里。 */
$sgSTermDays = max(1, (int) round(($sgSTo - $sgSFrom) / 86400));
$sgSIsNowTerm = $sgSMode !== 'none' && $sgSYear === $sgSThisYear && $sgSNow && (int) $sgSNow['term'] === $sgSTerm;
$sgSGauge = [];
if ($sgSMode !== 'none') {
    $sgSGauge = [
        ['tl', '黄经', $sgSCurTerm['lon'] . '°', '春分起算 · λ'],
        [
            'tr',
            $sgSIsNowTerm ? '候' : '起于',
            $sgSIsNowTerm
                ? ($sgSHouNames[$sgSNow['houIndex']] ?? '一候') . ' · ' . $sgSNow['houName']
                : date('m.d', sg_date_ts((int) $sgSCurTerm['ts'])),
            $sgSIsNowTerm ? $sgSCurTerm['name'] . ' 第 ' . ((int) $sgSNow['houIndex'] + 1) . ' 候' : $sgSCurTerm['name'],
        ],
        [
            'bl',
            '跨度',
            $sgSTermDays . ' 天',
            $sgSMode === 'gong' ? '两节气 · 一宫' : ($sgSMode === 'day' ? '一候 · 五天' : '一节气的长度'),
        ],
        [
            'br',
            $sgSIsNowTerm ? '距' . $sgSNow['nextName'] : '止于',
            $sgSIsNowTerm ? $sgSNow['nextDays'] . ' 天' : date('m.d', sg_date_ts((int) $sgSCurTerm['end'] - 86400)),
            $sgSIsNowTerm ? '太阳走完这一段' : '这一段最后一天',
        ],
    ];
}

/* 幕二的小标题在三幕输出之前就要定好（片段从 $GLOBALS 取，模板局部变量传不进去） */
if ($sgSMode === 'gong') {
    $sgSRangeEyebrow = '这一宫';
    $sgSRangeTitle = $sgSCurGong[1] . '宫';
    $sgSRangeSub = $sgSTerms[$sgSTerm]['name'] . ' – ' . $sgSTerms[$sgSTerm + 1]['name'] . ' · ' . $sgSCurGong[2];
} elseif ($sgSMode === 'day') {
    $sgSRangeEyebrow = '这一候';
    $sgSRangeTitle = $sgSCurTerm['name'] . ' · ' . ($sgSHouNames[$sgSHouIndex] ?? '一候');
    $sgSRangeSub = $sgSDateRange(['ts' => $sgSFrom], ['end' => $sgSTo]) . ' · ' . $sgSCurTerm['hou'][$sgSHouIndex];
} elseif ($sgSMode === 'none') {
    $sgSRangeEyebrow = '这一年';
    $sgSRangeTitle = (string) $sgSYear;
    $sgSRangeSub = '';
} else {
    $sgSRangeEyebrow = '这一段';
    $sgSRangeTitle = $sgSCurTerm['name'];
    $sgSRangeSub = $sgSDateRange($sgSCurTerm, $sgSCurTerm) . ' · ' . $sgSCurTerm['gloss'];
}

/* ---------- 盘转针定（第七十七轮）----------
   轮盘整盘旋转，指针恒在正上方：静置角 = −α(focus)，focus 是当前区间的中心角度。
   没有选参数时（裸地址）允许开局自动求签一次，落地后由 JS 跳到抽中的节气。 */
$sgSWheelFocus = $sgSMode === 'gong' ? $sgSGIndex * 30 + 15.0 : $sgSTerm * 15 + 7.5;
$sgSAutoDraw = $sgST === '' && $sgSG === '' && $sgSD === '' && !isset($_GET['y']) && !isset($_GET['draw']);

/* 三幕与三个片段共用的页面态。Typecho 的 $this->need() 在 Widget 方法作用域里 require，
   模板局部变量传不进去，所以统一走 $GLOBALS（与 page-notes.php 的 SG_NOTES_HEAD 同惯例）。 */
$GLOBALS['SG_SEASON'] = [
    'year' => $sgSYear,
    'wheel' => [
        'terms' => $sgWheelTerms,
        'gongs' => $sgWheelGongs,
        'current' => (int) ($sgSNow['term'] ?? -1),
        'selectedTerm' => $sgSMode === 'term' ? $sgSTerm : -1,
        'selectedGong' => $sgSMode === 'gong' ? $sgSGIndex : -1,
        'showGong' => $sgSShowGong,
        'centerName' => $sgCenterName,
        'centerSub' => $sgCenterSub,
        'focus' => $sgSWheelFocus,
        'autoDraw' => $sgSAutoDraw,
    ],
    'range' => $sgSRange,
    'groups' => $sgSGroups,
    'rangeMeta' => ['eyebrow' => $sgSRangeEyebrow, 'title' => $sgSRangeTitle, 'sub' => $sgSRangeSub],
    'table' => ['gongs' => $sgTableGongs, 'terms' => $sgTableTerms],
];
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad sg-season" data-sg-page="season">
  <?php /* 一整张纸（第七十八轮）：舞台与年表共用一个纸面。分成两张卡时，轮盘卡底与
     「这一段」之间会露出壁纸，读起来像两个互不相干的部件；并成一张之后，纸面从盘面
     一直落到对照表底，两段只用一条渐隐细线分栏。幕三的跋仍是独立一条低对比页脚。 */ ?>
  <div class="sg-season-sheet">
  <?php /* 幕一 · 舞台 —— 定调文案 + 仪表：轮盘居中，四个读数环绕四角 */ ?>
  <section class="sg-season-stage" data-sg-stage data-sg-term-counts="<?php echo implode(',', array_map(static fn(array $sgT2): int => (int) $sgT2['count'], $sgWheelTerms)); ?>">
    <header class="sg-stage-copy">
      <p class="sg-stage-kicker">Seasonal almanac · 岁时记</p>
      <h1 class="sg-season-title"><?php echo sg_e((string) $this->title); ?></h1>
      <p class="sg-season-sub"><?php echo sg_e($sgSSub); ?></p>
      <?php if ($sgSCurTerm) : ?>
      <p class="sg-stage-romance"><?php echo sg_e($sgSCurTerm['gloss']); ?></p>
      <?php endif; ?>
      <details class="sg-stage-manual">
        <summary>这一页怎么读</summary>
        <p>一条黄道，两种读法：节气把黄道分成二十四段，宫分成十二段。宫不是天上的星座，是同一条黄道的十二等分。</p>
      </details>
    </header>

  <?php if ($sgSMode === 'none') : ?>
  <?php /* 整年无字 / 表缺年份：纸面照铺，只是没有年表可接 */ ?>
  <div class="sg-season-empty">
    <p>这一年还没有落笔。</p>
    <p><a href="<?php echo sg_e(sg_season_url(['y' => $sgSThisYear])); ?>">回到 <?php echo (int) $sgSThisYear; ?> 年 →</a></p>
  </div>
  </section>
  </div>
  <?php else : ?>

  <?php /* 仪表：轮盘居中，四个读数环绕四角 */ ?>
  <div class="sg-stage-instrument">
    <?php $this->need('partials/season-wheel.php'); ?>

    <div class="sg-stage-gauges">
      <?php foreach ($sgSGauge as $sgGauge) : ?>
      <div class="sg-gauge sg-gauge--<?php echo sg_e($sgGauge[0]); ?>">
        <span class="sg-gauge-k"><?php echo sg_e($sgGauge[1]); ?></span>
        <b class="sg-gauge-v"><?php echo sg_e($sgGauge[2]); ?></b>
        <span class="sg-gauge-u"><?php echo sg_e($sgGauge[3]); ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($sgSCurTerm) : ?>
  <span class="sg-seal sg-seal--stage" aria-label="<?php echo sg_e('当前节气 ' . $sgSCurTerm['name']); ?>"><?php echo sg_e($sgSCurTerm['name']); ?></span>
  <?php endif; ?>
  </section>

  <?php /* 幕二 · 年表：与舞台同处一张纸，展陈与对照表仍挂同一条时间轴脊柱 */ ?>
  <section class="sg-season-chronicle" data-sg-reveal>
    <?php $this->need('partials/season-range.php'); ?>
    <?php $this->need('partials/season-table.php'); ?>
  </section>
  </div>
  <?php /* 纸面到此合上；跋在纸外，单独一条低对比页脚 */ ?>

  <?php /* 幕三 · 跋：年份、前后节气与口径，收成一条低对比的页脚 */ ?>
  <footer class="sg-season-colophon" data-sg-reveal>
    <nav class="sg-season-years" aria-label="年份与相邻节气">
      <div class="sg-years-row">
        <span class="sg-years-label">年份</span>
        <?php foreach ($sgSYears as $sgY) : ?>
        <a class="sg-year<?php echo $sgY === $sgSYear ? ' is-current' : ''; ?>" href="<?php echo sg_e($sgSYearUrl($sgY)); ?>"<?php echo $sgY === $sgSYear ? ' aria-current="true"' : ''; ?>><?php echo (int) $sgY; ?></a>
        <?php endforeach; ?>
      </div>
      <div class="sg-season-nav">
        <?php if ($sgSPrevUrl !== '') : ?>
        <a href="<?php echo sg_e($sgSPrevUrl); ?>">← 上一节气</a>
        <?php endif; ?>
        <a href="<?php echo sg_e(sg_season_url(['y' => $sgSYear])); ?>">回到当令</a>
        <?php if ($sgSNextUrl !== '') : ?>
        <a href="<?php echo sg_e($sgSNextUrl); ?>">下一节气 →</a>
        <?php endif; ?>
      </div>
    </nav>

    <div class="sg-season-note">
      <p>采用回归黄道，春分点为白羊宫 0°，与节气同源；不使用恒星黄道。</p>
      <p>宫界按中气实日逐年计算，不采用固定月日的通行星座表。</p>
      <p>此处为黄道十二宫（signs），非天文星座；黄道带上实际有 13 个星座，含蛇夫座。宫名取通行译名，古译作室女 / 人马 / 磨羯 / 宝瓶。</p>
      <p>刻度原点为春分，故 1 月的节气在表盘左上（太阳黄经 285°–345° 在春分之前），这是黄道顺序，不是排版错误；纪年仍用公历年，立春只作「岁首」刻度。</p>
    </div>
  </footer>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
