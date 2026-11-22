<?php
/**
 * 时光机（网站建设时光）
 *
 * @package custom
 *
 * 版式参考源站 Fomalhaut「旧时光」（www.fomal.cc/site/time）的年报式布局：
 * 「SITE CHANGELOG」徽章 → 大标题 → 统计行（起止 / 历时 / 更新 / 条数 / 年份）
 * → 年份筛选胶囊 → 大年份分区（竖轴 + 日期节点 + 编号轮次 + 轮内小序号子项）。
 *
 * 数据来自主题包 data/history.md —— 唯一母本是仓库 docs/HISTORY.md
 * （check.mjs 文档归置规则禁止源码树出现 .md，npm run release 时拷入部署包）。
 * 母本格式：`## 日期` 分组 + `- 轮次` 列表；轮次正文里的 `**(1) 小节**`、
 * `（1）…` 与 ①②③ 等小序号会自动拆成独立的编号子项。
 *
 * 页头两块可后台设置（lib/admin.php）：
 *   sgTimeMilestones  站史里程碑（每行「日期|标题|说明」，留空用内置三条）
 *   sgTimeModels      使用过的模型与占比（每行「模型名|占比」，留空不输出）
 * 文件缺失或为空时输出引导空态，不影响其它页面。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

/* ---------- 解析 history.md ---------- */

/* 行内格式：转义后加粗 / 代码；小序号标记也一并转义 */
$sgTimeInline = null;
$sgTimeInline = static function (string $s) use (&$sgTimeInline): string {
    $s = sg_e($s);
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
    return $s;
};

/* 轮次正文按小序号拆子项：
   A. `**(1) 小节名**`（半角括号粗体，IMPLEMENTATION 抽取主形态）
   B. ①②③…㊵ 圈号开头（早期轮次直接写在段首） */
$sgTimeSubs = static function (string $body): array {
    $subs = [];
    $map = ['①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5, '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
        '⑪' => 11, '⑫' => 12, '⑬' => 13, '⑭' => 14, '⑮' => 15, '⑯' => 16, '⑰' => 17, '⑱' => 18, '⑲' => 19, '⑳' => 20,
        '㉑' => 21, '㉒' => 22, '㉓' => 23, '㉔' => 24, '㉕' => 25, '㉖' => 26, '㉗' => 27, '㉘' => 28, '㉙' => 29, '㉚' => 30,
        '㉛' => 31, '㉜' => 32, '㉝' => 33, '㉞' => 34, '㉟' => 35, '㊱' => 36, '㊲' => 37, '㊳' => 38, '㊴' => 39, '㊵' => 40];
    $segs = preg_split('/(?=\*\*\(\d+\))|(?=[' . implode('', array_keys($map)) . '])/u', $body) ?: [$body];
    foreach ($segs as $seg) {
        $seg = trim($seg);
        if ($seg === '') {
            continue;
        }
        $num = 0;
        $title = '';
        $text = $seg;
        if (preg_match('/^\*\*\((\d+)\)\s*([^*]*?)\*\*\s*(.*)$/s', $seg, $m)) {
            $num = (int) $m[1];
            $title = trim($m[2]);
            $text = trim($m[3]);
        } elseif ($seg[0] !== '' && isset($map[$seg[0]])) {
            $num = $map[$seg[0]];
            $rest = trim(mb_substr($seg, 1));
            if (preg_match('/^\*\*([^*]+)\*\*\s*：?\s*(.*)$/s', $rest, $m)) {
                $title = trim($m[1]);
                $text = trim($m[2]);
            } elseif (preg_match('/^([^：]{1,24})：\s*(.*)$/s', $rest, $m)) {
                $title = trim($m[1]);
                $text = trim($m[2]);
            } else {
                $text = $rest;
            }
        }
        if ($num === 0 && $title === '' && isset($subs[0]) && $subs[0]['num'] === 0) {
            $subs[0]['text'] .= ' ' . $text; // 首段无序号的前导文本并入第一个子项
            continue;
        }
        $subs[] = ['num' => $num, 'title' => $title, 'text' => $text];
    }
    return $subs;
};

$sgTimeParse = static function (string $raw) use ($sgTimeInline, $sgTimeSubs): array {
    $groups = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '# ')) {
            continue; // 空行与文档一级标题不渲染
        }
        if (str_starts_with($line, '## ') || str_starts_with($line, '### ')) {
            $groups[] = ['title' => trim(substr($line, 3)), 'items' => []];
            continue;
        }
        if (!isset($groups[count($groups) - 1]['items'])) {
            continue; // 标题前的散行不渲染
        }
        $text = $line;
        if (preg_match('/^(\d+)[.、．]\s*(.+)$/', $line, $m)) {
            $text = $m[2];
        } elseif (str_starts_with($line, '- ') || str_starts_with($line, '* ')) {
            $text = trim(substr($line, 2));
        }
        if ($text === '') {
            continue;
        }
        /* 轮次 = 标题（首段）+ 正文（按小序号拆子项） */
        $ci = mb_strpos($text, '**：');
        $lead = $text;
        $body = '';
        if ($ci !== false) {
            $lead = trim(str_replace('**', '', mb_substr($text, 0, $ci)));
            $body = trim(mb_substr($text, $ci + 3));
        } elseif (preg_match('/^(.*?）)\s*：\s*(.+)$/s', $text, $m) && mb_strlen($m[1]) <= 96) {
            $lead = $m[1];
            $body = trim($m[2]);
        }
        $groups[count($groups) - 1]['items'][] = [
            'lead' => $sgTimeInline($lead),
            'body' => $body,
            'subs' => $body !== '' ? $sgTimeSubs($body) : [],
        ];
    }
    return array_values(array_filter($groups, static fn (array $g): bool => $g['items'] !== []));
};

$sgTimeFile = __DIR__ . '/data/history.md';
$sgTimeRaw = is_readable($sgTimeFile) ? (string) file_get_contents($sgTimeFile) : '';
$sgTimeGroups = $sgTimeRaw !== '' ? $sgTimeParse($sgTimeRaw) : [];

/* ---------- 统计与年份聚合 ---------- */

$sgTimeTotal = 0;
$sgTimeYears = []; // year => ['count' => n, 'days' => n, 'groups' => [...]]
foreach ($sgTimeGroups as $gi => $sgGroup) {
    $sgYear = mb_substr($sgGroup['title'], 0, 4);
    $sgTimeTotal += count($sgGroup['items']);
    if (!isset($sgTimeYears[$sgYear])) {
        $sgTimeYears[$sgYear] = ['count' => 0, 'dates' => [], 'groups' => []];
    }
    $sgTimeYears[$sgYear]['count'] += count($sgGroup['items']);
    $sgTimeYears[$sgYear]['dates'][] = $sgGroup['title'];
    $sgTimeYears[$sgYear]['groups'][] = $gi;
}
krsort($sgTimeYears); // 年份倒序

$sgTimeDates = array_map(static fn (array $g): string => $g['title'], $sgTimeGroups);
$sgTimeEarliest = $sgTimeDates ? min($sgTimeDates) : '';
$sgTimeLatest = $sgTimeDates ? max($sgTimeDates) : '';
$sgTimeSpanDays = 0;
$sgTimeSpanY = 0;
$sgTimeSpanM = 0;
$sgTimeSinceDays = 0;
if ($sgTimeEarliest !== '' && $sgTimeLatest !== '') {
    try {
        $sgA = new DateTimeImmutable($sgTimeEarliest);
        $sgB = new DateTimeImmutable($sgTimeLatest);
        $sgTimeSpanDays = (int) $sgA->diff($sgB)->days;
        $sgTimeSpanY = $sgA->diff($sgB)->y;
        $sgTimeSpanM = $sgA->diff($sgB)->m;
        $sgTimeSinceDays = (int) $sgB->diff(new DateTimeImmutable(date('Y-m-d')))->days;
    } catch (Throwable $e) {
        // 日期解析失败时统计留 0，不阻塞页面
    }
}

/* ---------- 后台可设数据：站史里程碑 / 使用过的模型 ---------- */

$sgTimeMilestonesDefault = "2023-11-02|拥有属于自己的博客|大二这年，用 WordPress 搭起了自己的第一台博客，主题 Astra\n"
    . "2025-12-18|博客迁移到 Typecho|从 WordPress 整体迁移，主题换用 Butterfly 移植版\n"
    . "2026-11-06|开始开发自己的主题|决定基于 Typecho 框架，开发一个属于自己的博客主题 —— Soraglass（空璃）";
$sgTimeMilestones = [];
foreach (preg_split('/\r\n|\r|\n/', trim(sg_opt('sgTimeMilestones', '')) !== '' ? sg_opt('sgTimeMilestones') : $sgTimeMilestonesDefault) ?: [] as $sgMsLine) {
    $sgMsLine = trim($sgMsLine);
    if ($sgMsLine === '') {
        continue;
    }
    $sgMsParts = array_map(static fn (string $p): string => trim($p), explode('|', $sgMsLine, 3));
    if (!isset($sgMsParts[0]) || $sgMsParts[0] === '') {
        continue;
    }
    $sgTimeMilestones[] = [
        'date' => $sgMsParts[0],
        'title' => $sgMsParts[1] ?? '',
        'desc' => $sgMsParts[2] ?? '',
    ];
}

$sgTimeModels = [];
foreach (preg_split('/\r\n|\r|\n/', trim(sg_opt('sgTimeModels', ''))) ?: [] as $sgModelLine) {
    $sgModelLine = trim($sgModelLine);
    if ($sgModelLine === '' || !str_contains($sgModelLine, '|')) {
        continue;
    }
    [$sgModelName, $sgModelRatio] = array_map(static fn (string $p): string => trim($p), explode('|', $sgModelLine, 2) + ['', '']);
    if ($sgModelName === '') {
        continue;
    }
    $sgModelRatio = (float) str_replace('%', '', $sgModelRatio);
    if ($sgModelRatio <= 0) {
        continue;
    }
    $sgTimeModels[] = ['name' => $sgModelName, 'ratio' => min(100, $sgModelRatio)];
}
usort($sgTimeModels, static fn (array $a, array $b): int => $b['ratio'] <=> $a['ratio']);
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="time">
  <?php /* 页头：徽章 + 大标题 + 统计行 + 年份筛选（版式参考源站「旧时光」年报） */ ?>
  <header class="sg-time-hero">
    <span class="sg-time-badge">SITE CHANGELOG</span>
    <h1 class="sg-time-title"><?php echo sg_e((string) $this->title); ?></h1>
    <?php if ($sgTimeTotal > 0) : ?>
    <p class="sg-time-stats">
      <span>从 <b><?php echo sg_e($sgTimeEarliest); ?></b> 记到 <b><?php echo sg_e($sgTimeLatest); ?></b></span>
      <i>·</i>
      <span>历时 <b><?php echo $sgTimeSpanDays; ?></b> 天<?php if ($sgTimeSpanY > 0) : ?>（约 <?php echo $sgTimeSpanY; ?> 年 <?php echo $sgTimeSpanM; ?> 个月）<?php endif; ?></span>
      <i>·</i>
      <span>更新 <b><?php echo $sgTimeSinceDays; ?></b> 天</span>
      <i>·</i>
      <span>共 <b><?php echo $sgTimeTotal; ?></b> 条记录</span>
      <i>·</i>
      <span>横跨 <b><?php echo count($sgTimeYears); ?></b> 个年份</span>
    </p>
    <div class="sg-time-yearpills" role="group" aria-label="按年份筛选" data-sg-time-pills>
      <?php $sgMaxYearCount = max(array_column(array_values($sgTimeYears), 'count')); ?>
      <?php foreach ($sgTimeYears as $sgYear => $sgInfo) : ?>
      <button class="sg-time-yearpill" type="button" data-sg-time-pill="<?php echo sg_e($sgYear); ?>" aria-pressed="false">
        <span class="sg-time-yearpill-year"><?php echo sg_e($sgYear); ?></span>
        <span class="sg-time-yearpill-count"><?php echo (int) $sgInfo['count']; ?> 条</span>
        <span class="sg-time-yearpill-bar" aria-hidden="true"><span style="width:<?php echo (int) round($sgInfo['count'] / max(1, $sgMaxYearCount) * 100); ?>%"></span></span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </header>

  <?php if ($sgTimeMilestones) : ?>
  <section class="sg-time-milestones" aria-label="站史里程碑">
    <h2 class="sg-time-section-title">站史 · 里程碑</h2>
    <ol class="sg-time-ms-list">
      <?php foreach ($sgTimeMilestones as $sgMs) : ?>
      <li class="sg-time-ms">
        <time class="sg-time-ms-date"><?php echo sg_e($sgMs['date']); ?></time>
        <div class="sg-time-ms-body">
          <h3><?php echo sg_e($sgMs['title']); ?></h3>
          <?php if ($sgMs['desc'] !== '') : ?><p><?php echo sg_e($sgMs['desc']); ?></p><?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endif; ?>

  <?php if ($sgTimeModels) : ?>
  <section class="sg-time-models" aria-label="使用过的模型与占比">
    <h2 class="sg-time-section-title">使用过的模型</h2>
    <ul class="sg-time-model-list">
      <?php foreach ($sgTimeModels as $sgModel) : ?>
      <li class="sg-time-model">
        <span class="sg-time-model-name"><?php echo sg_e($sgModel['name']); ?></span>
        <span class="sg-time-model-bar" aria-hidden="true"><span style="width:<?php echo (int) $sgModel['ratio']; ?>%"></span></span>
        <span class="sg-time-model-ratio"><?php echo (int) $sgModel['ratio']; ?>%</span>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($sgTimeGroups) : ?>
  <?php /* 最新记录徽章落在第一个年份分区的首个日期组上 */ ?>
  <?php foreach ($sgTimeYears as $sgYear => $sgYearInfo) : ?>
  <section class="sg-time-year" data-sg-time-year="<?php echo sg_e($sgYear); ?>">
    <div class="sg-time-year-head">
      <h2 class="sg-time-year-num"><?php echo sg_e($sgYear); ?></h2>
      <span class="sg-time-year-meta"><?php echo (int) $sgYearInfo['count']; ?> 条记录 · <?php echo count($sgYearInfo['dates']); ?> 天</span>
    </div>
    <ol class="sg-time">
      <?php foreach ($sgYearInfo['groups'] as $sgGi) : $sgGroup = $sgTimeGroups[$sgGi]; ?>
      <li class="sg-time-item"<?php echo $sgGi === 0 ? ' data-sg-time-latest' : ''; ?>>
        <h3 class="sg-time-when"><span class="sg-time-when-year"><?php echo sg_e(mb_substr($sgGroup['title'], 0, 4)); ?></span><span class="sg-time-when-day"><?php echo sg_e(mb_substr($sgGroup['title'], 5)); ?></span></h3>
        <?php if ($sgGi === 0) : ?><span class="sg-time-latest">最新记录</span><?php endif; ?>
        <div class="sg-time-card">
          <ol class="sg-time-rounds">
            <?php foreach ($sgGroup['items'] as $sgRi => $sgItem) : ?>
            <li class="sg-time-round">
              <span class="sg-time-round-num" aria-hidden="true"><?php echo $sgRi + 1; ?></span>
              <div class="sg-time-round-body">
                <p class="sg-time-round-title"><?php echo $sgItem['lead']; ?></p>
                <?php if (count($sgItem['subs']) > 1 || (count($sgItem['subs']) === 1 && $sgItem['subs'][0]['num'] > 0)) : ?>
                <ol class="sg-time-subs">
                  <?php foreach ($sgItem['subs'] as $sgSub) : ?>
                  <li>
                    <span class="sg-time-sub-num" aria-hidden="true"><?php echo $sgSub['num'] > 0 ? $sgSub['num'] : '·'; ?></span>
                    <div class="sg-time-sub-text"><?php if ($sgSub['title'] !== '') : ?><strong><?php echo sg_e($sgSub['title']); ?></strong><?php echo $sgSub['text'] !== '' ? '：' : ''; endif; echo $sgTimeInline($sgSub['text']); ?></div>
                  </li>
                  <?php endforeach; ?>
                </ol>
                <?php elseif ($sgItem['body'] !== '') : ?>
                <p class="sg-time-round-text"><?php echo $sgTimeInline($sgItem['body']); ?></p>
                <?php endif; ?>
              </div>
            </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
  </section>
  <?php endforeach; ?>
  <?php else : ?>
  <div class="sg-time-empty sg-glass">
    <p>时光轴还没有记录。</p>
    <p class="sg-time-empty-hint">在仓库 <code>docs/HISTORY.md</code> 里按「## 日期 + - 条目」补几行，跑 <code>npm run release</code> 重新打包即可看到这里。</p>
  </div>
  <?php endif; ?>
</main>

<?php $this->need('footer.php'); ?>
