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
    $sgCenterSub = $sgSMode === 'term' && $sgSYear === $sgSThisYear && $sgSNow && $sgSTerm === (int) $sgSNow['term']
        ? '黄经 ' . $sgSCurTerm['lon'] . '° · 距' . $sgSNow['nextName'] . ' ' . $sgSNow['nextDays'] . ' 天'
        : '黄经 ' . $sgSCurTerm['lon'] . '° · ' . $sgSDateRange($sgSCurTerm, $sgSCurTerm);
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
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad sg-season" data-sg-page="season">
  <?php /* 第 1 段：页头 —— 标题 / 副题 / 一句说明 / 唯一一枚节气朱印 */ ?>
  <header class="sg-season-head">
    <div class="sg-season-head-main">
      <h1 class="sg-season-title"><?php echo sg_e((string) $this->title); ?></h1>
      <p class="sg-season-sub"><?php echo sg_e($sgSSub); ?></p>
      <p class="sg-season-intro">一条黄道，两种读法：节气把黄道分成二十四段，宫分成十二段。宫不是天上的星座，是同一条黄道的十二等分。</p>
    </div>
    <?php if ($sgSCurTerm) : ?>
    <span class="sg-seal" aria-label="<?php echo sg_e('当前节气 ' . $sgSCurTerm['name']); ?>"><?php echo sg_e($sgSCurTerm['name']); ?></span>
    <?php endif; ?>
  </header>

  <?php if ($sgSMode === 'none') : ?>
  <?php /* 整年无字 / 表缺年份 */ ?>
  <section class="sg-season-empty">
    <p>这一年还没有落笔。</p>
    <p><a href="<?php echo sg_e(sg_season_url(['y' => $sgSThisYear])); ?>">回到 <?php echo (int) $sgSThisYear; ?> 年 →</a></p>
  </section>
  <?php else : ?>

  <?php /* 第 2 段：当令条 —— 现在是何时（B 双读） */ ?>
  <section class="sg-season-now" aria-label="当令">
    <div class="sg-now-main">
      <?php if ($sgSNow) : ?>
      <span class="sg-now-term"><?php echo sg_e($sgSNow['termName']); ?></span>
      <span class="sg-now-gong"><?php echo sg_e($sgSNow['gongName'] . '宫'); ?></span>
      <p class="sg-now-gloss"><?php echo sg_e($sgSNow['gloss']); ?></p>
      <?php else : ?>
      <span class="sg-now-term">岁候</span>
      <p class="sg-now-gloss">节气表覆盖 1900–2100，当前时间不在表内。</p>
      <?php endif; ?>
    </div>
    <?php if ($sgSNow) : ?>
    <div class="sg-now-meta">
      <b><?php echo (int) $sgSNow['nextDays']; ?> 天</b>
      <span>距<?php echo sg_e($sgSNow['nextName']); ?></span>
      <span class="sg-now-hou"><?php echo sg_e($sgSHouNames[$sgSNow['houIndex']] . ' · ' . $sgSNow['houName']); ?></span>
    </div>
    <?php endif; ?>
  </section>

  <?php
  /* Typecho 的 $this->need() 在 Widget 方法作用域里 require，模板局部变量传不进去，
     所以页面态统一放进 $GLOBALS['SG_SEASON']（与 page-notes.php 的 SG_NOTES_HEAD 同一惯例）。 */
  if ($sgSMode === 'gong') {
      $sgSRangeEyebrow = '这一宫';
      $sgSRangeTitle = $sgSCurGong[1] . '宫';
      $sgSRangeSub = $sgSTerms[$sgSTerm]['name'] . ' – ' . $sgSTerms[$sgSTerm + 1]['name'] . ' · ' . $sgSCurGong[2];
  } elseif ($sgSMode === 'day') {
      $sgSRangeEyebrow = '这一候';
      $sgSRangeTitle = $sgSCurTerm['name'] . ' · ' . ($sgSHouNames[$sgSHouIndex] ?? '一候');
      $sgSRangeSub = $sgSDateRange(['ts' => $sgSFrom], ['end' => $sgSTo]) . ' · ' . $sgSCurTerm['hou'][$sgSHouIndex];
  } else {
      $sgSRangeEyebrow = '这一段';
      $sgSRangeTitle = $sgSCurTerm['name'];
      $sgSRangeSub = $sgSDateRange($sgSCurTerm, $sgSCurTerm) . ' · ' . $sgSCurTerm['gloss'];
  }
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
      ],
      'range' => $sgSRange,
      'groups' => $sgSGroups,
      'rangeMeta' => ['eyebrow' => $sgSRangeEyebrow, 'title' => $sgSRangeTitle, 'sub' => $sgSRangeSub],
      'table' => ['gongs' => $sgTableGongs, 'terms' => $sgTableTerms],
  ];
  ?>

  <?php /* 第 3 段：双刻度轮盘 */ ?>
  <?php $this->need('partials/season-wheel.php'); ?>

  <?php /* 第 4 段：区间展陈 */ ?>
  <?php $this->need('partials/season-range.php'); ?>

  <?php /* 第 5 段：对照长表 */ ?>
  <?php $this->need('partials/season-table.php'); ?>

  <?php /* 第 6 段：年份行 + 前后节气 */ ?>
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

  <?php /* 第 7 段：口径声明 */ ?>
  <footer class="sg-season-note">
    <p>采用回归黄道，春分点为白羊宫 0°，与节气同源；不使用恒星黄道。</p>
    <p>宫界按中气实日逐年计算，不采用固定月日的通行星座表。</p>
    <p>此处为黄道十二宫（signs），非天文星座；黄道带上实际有 13 个星座，含蛇夫座。宫名取通行译名，古译作室女 / 人马 / 磨羯 / 宝瓶。</p>
    <p>刻度原点为春分，故 1 月的节气在表盘左上（太阳黄经 285°–345° 在春分之前），这是黄道顺序，不是排版错误；纪年仍用公历年，立春只作「岁首」刻度。</p>
  </footer>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
