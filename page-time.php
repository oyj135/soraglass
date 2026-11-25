<?php
/**
 * 时光机（网站建设时光）
 *
 * @package custom
 *
 * 版式参考源站 Fomalhaut「旧时光」（www.fomal.cc/site/time）的年报式布局：
 * 「SITE CHANGELOG」徽章 → 大标题（夜间三色轮询发光）→ 统计行 → 年份筛选胶囊
 * → 大年份分区（竖轴 + 日期节点 + 编号轮次 + 轮内小序号子项）。
 *
 * 数据来自主题包 data/history.md —— 唯一母本是仓库 docs/HISTORY.md
 * （check.mjs 文档归置规则禁止源码树出现 .md，npm run release 时拷入部署包）。
 * 母本格式：`## 日期` 分组 + `- 轮次` 列表；轮次正文里的 `**(1) 小节**` 与
 * ①②③ 等小序号会自动拆成独立的编号子项。
 *
 * 站史里程碑（后台 sgTimeMilestones，留空用内置三条）会并入时间轴，
 * 所以统计行与年份筛选天然覆盖建站以来的全部年份。
 * 使用过的模型与占比（后台 sgTimeModels，每行「模型名|占比」，留空不输出）。
 *
 * 解析全部走「str_replace 圈号 → ASCII 哨兵 + 纯 ASCII 正则」，
 * 不用 /u 修饰符与多字节字符类，避免宿主 PCRE 编码差异导致切分失效。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

/* ---------- 行内格式：转义后加粗 / 代码 ---------- */

$sgTimeInline = static function (string $s): string {
    $s = sg_e($s);
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
    return $s;
};

/* ---------- 轮次正文按小序号拆子项 ----------
   ①②③…㊵ 圈号先统一改写成 "\x01(N)\x02" 哨兵，之后只按 ASCII 正则切，
   `**(1) 小节名**` 保持原样识别；两路都归到同一套子项结构。 */
$sgTimeSubs = static function (string $body): array {
    $map = ['①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5, '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
        '⑪' => 11, '⑫' => 12, '⑬' => 13, '⑭' => 14, '⑮' => 15, '⑯' => 16, '⑰' => 17, '⑱' => 18, '⑲' => 19, '⑳' => 20,
        '㉑' => 21, '㉒' => 22, '㉓' => 23, '㉔' => 24, '㉕' => 25, '㉖' => 26, '㉗' => 27, '㉘' => 28, '㉙' => 29, '㉚' => 30,
        '㉛' => 31, '㉜' => 32, '㉝' => 33, '㉞' => 34, '㉟' => 35, '㊱' => 36, '㊲' => 37, '㊳' => 38, '㊴' => 39, '㊵' => 40];
    $body = str_replace(array_keys($map), array_map(static fn (int $n): string => "\x01({$n})\x02", array_values($map)), $body);
    $subs = [];
    foreach (preg_split('/(?=\x01\(\d+\)\x02)|(?=\*\*\(\d+\))/', $body) ?: [$body] as $seg) {
        $seg = trim($seg);
        if ($seg === '') {
            continue;
        }
        $num = 0;
        $title = '';
        $text = $seg;
        if (preg_match('/^\x01\((\d+)\)\x02\s*([\s\S]*)$/', $seg, $m)) {
            $num = (int) $m[1];
            $rest = trim($m[2]);
            /* 圈号后的小节名：**加粗**：或 短标题： 两种写法 */
            if (preg_match('/^\*\*([^*]+)\*\*\s*：?\s*([\s\S]*)$/', $rest, $t)) {
                $title = trim($t[1]);
                $text = trim($t[2]);
            } elseif (preg_match('/^([^：]{1,72})：\s*([\s\S]*)$/', $rest, $t)) {
                $title = trim($t[1]);
                $text = trim($t[2]);
            } else {
                $text = $rest;
            }
        } elseif (preg_match('/^\*\*\((\d+)\)\s*([^*]*?)\*\*\s*([\s\S]*)$/', $seg, $m)) {
            $num = (int) $m[1];
            $title = trim($m[2]);
            $text = trim($m[3]);
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
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '# ') === 0) {
            continue; // 空行与文档一级标题不渲染
        }
        if (strpos($line, '## ') === 0 || strpos($line, '### ') === 0) {
            $groups[] = ['title' => trim(substr($line, 3)), 'items' => []];
            continue;
        }
        if (!isset($groups[count($groups) - 1]['items'])) {
            continue; // 标题前的散行不渲染
        }
        $text = $line;
        if (preg_match('/^(\d+)[.、．]\s*(.+)$/', $line, $m)) {
            $text = $m[2];
        } elseif (strpos($line, '- ') === 0 || strpos($line, '* ') === 0) {
            $text = trim(substr($line, 2));
        }
        if ($text === '') {
            continue;
        }
        /* 轮次 = 标题（首段）+ 正文（按小序号拆子项） */
        $ci = strpos($text, '**：');
        $lead = $text;
        $body = '';
        if ($ci !== false) {
            $lead = trim(str_replace('**', '', substr($text, 0, $ci)));
            $body = trim(substr($text, $ci + 3));
        } elseif (preg_match('/^(.*?）)\s*：\s*(.+)$/s', $text, $m) && strlen($m[1]) <= 192) {
            $lead = $m[1];
            $body = trim($m[2]);
        }
        $groups[count($groups) - 1]['items'][] = [
            'lead' => $sgTimeInline($lead),
            'body' => $body,
            'subs' => $body !== '' ? $sgTimeSubs($body) : [],
            'ms' => false,
        ];
    }
    return array_values(array_filter($groups, static fn (array $g): bool => $g['items'] !== []));
};

$sgTimeFile = __DIR__ . '/data/history.md';
$sgTimeRaw = is_readable($sgTimeFile) ? (string) file_get_contents($sgTimeFile) : '';
$sgTimeGroups = $sgTimeRaw !== '' ? $sgTimeParse($sgTimeRaw) : [];

/* ---------- 站史里程碑（后台 sgTimeMilestones，留空用内置三条） ---------- */

$sgTimeMilestonesDefault = "2023-11-02|拥有属于自己的博客|大二这年，用 WordPress 搭起了自己的第一台博客，主题 Astra\n"
    . "2025-12-18|博客迁移到 Typecho|从 WordPress 整体迁移，主题换用 Butterfly 移植版\n"
    . "2026-11-06|开始开发自己的主题|决定基于 Typecho 框架，开发一个属于自己的博客主题 —— Soraglass（空璃）";
$sgTimeMilestonesRaw = trim(sg_opt('sgTimeMilestones', ''));
$sgTimeMilestonesRaw = $sgTimeMilestonesRaw !== '' ? $sgTimeMilestonesRaw : $sgTimeMilestonesDefault;
$sgTimeMilestones = [];
foreach (preg_split('/\r\n|\r|\n/', $sgTimeMilestonesRaw) ?: [] as $sgMsLine) {
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

/* ---------- 里程碑并入时间轴：日期归组后按日期倒序 ---------- */

$sgTimeMerged = [];
foreach ($sgTimeMilestones as $sgMs) {
    $sgTimeMerged[$sgMs['date']][] = [
        'lead' => $sgTimeInline($sgMs['title']),
        'body' => $sgMs['desc'],
        'subs' => [],
        'ms' => true,
    ];
}
foreach ($sgTimeGroups as $sgGroup) {
    foreach ($sgGroup['items'] as $sgItem) {
        $sgTimeMerged[$sgGroup['title']][] = $sgItem;
    }
}
krsort($sgTimeMerged); // 日期（YYYY-MM-DD 字典序）倒序 = 新的在上
$sgTimeGroups = [];
foreach ($sgTimeMerged as $sgDate => $sgItems) {
    $sgTimeGroups[] = ['title' => $sgDate, 'items' => $sgItems];
}

/* ---------- 统计与年份聚合 ---------- */

$sgTimeTotal = 0;
$sgTimeYears = []; // year => ['count' => n, 'groups' => [global indices]]
foreach ($sgTimeGroups as $sgGi => $sgGroup) {
    $sgYear = substr($sgGroup['title'], 0, 4);
    $sgTimeTotal += count($sgGroup['items']);
    if (!isset($sgTimeYears[$sgYear])) {
        $sgTimeYears[$sgYear] = ['count' => 0, 'dates' => [], 'groups' => []];
    }
    $sgTimeYears[$sgYear]['count'] += count($sgGroup['items']);
    $sgTimeYears[$sgYear]['dates'][] = $sgGroup['title'];
    $sgTimeYears[$sgYear]['groups'][] = $sgGi;
}
krsort($sgTimeYears); // 年份倒序

$sgTimeDates = array_map(static fn (array $g): string => $g['title'], $sgTimeGroups);
$sgTimeEarliest = $sgTimeDates ? min($sgTimeDates) : '';
$sgTimeLatest = $sgTimeDates ? max($sgTimeDates) : '';
$sgTimeSpanDays = 0;
$sgTimeSpanY = 0;
$sgTimeSpanM = 0;
$sgTimeSpanD = 0;
$sgTimeSinceDays = 0;
if ($sgTimeEarliest !== '' && $sgTimeLatest !== '') {
    try {
        $sgA = new DateTimeImmutable($sgTimeEarliest);
        $sgB = new DateTimeImmutable($sgTimeLatest);
        $sgTimeDiff = $sgA->diff($sgB);
        $sgTimeSpanDays = (int) $sgTimeDiff->days;
        $sgTimeSpanY = (int) $sgTimeDiff->y;
        $sgTimeSpanM = (int) $sgTimeDiff->m;
        $sgTimeSpanD = (int) $sgTimeDiff->d;
        $sgTimeSinceDays = (int) $sgB->diff(new DateTimeImmutable(date('Y-m-d')))->days;
    } catch (Throwable $e) {
        // 日期解析失败时统计留 0，不阻塞页面
    }
}

/* ---------- 使用过的模型与占比（后台 sgTimeModels，留空不输出） ---------- */

$sgTimeModels = [];
foreach (preg_split('/\r\n|\r|\n/', trim(sg_opt('sgTimeModels', ''))) ?: [] as $sgModelLine) {
    $sgModelLine = trim($sgModelLine);
    if ($sgModelLine === '' || strpos($sgModelLine, '|') === false) {
        continue;
    }
    $sgModelParts = explode('|', $sgModelLine, 2);
    $sgModelName = trim($sgModelParts[0]);
    $sgModelRatio = (float) str_replace('%', '', trim($sgModelParts[1] ?? ''));
    if ($sgModelName === '' || $sgModelRatio <= 0) {
        continue;
    }
    $sgTimeModels[] = ['name' => $sgModelName, 'ratio' => min(100, $sgModelRatio)];
}
usort($sgTimeModels, static fn (array $a, array $b): int => $b['ratio'] <=> $a['ratio']);
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="time">
  <?php /* 大底板：整页内容收纳进一张纸面卡（与书签盒同语言，2026-11-25）；纯视觉容器，无 data 挂点。 */ ?>
  <div class="sg-time-sheet">
  <?php /* 页头：徽章 + 大标题 + 统计行 + 年份筛选（版式参考源站「旧时光」年报） */ ?>
  <header class="sg-time-hero">
    <span class="sg-time-badge">SITE CHANGELOG</span>
    <h1 class="sg-time-title"><?php echo sg_e((string) $this->title); ?></h1>
    <?php if ($sgTimeTotal > 0) : ?>
    <p class="sg-time-stats">
      <span>从 <b><?php echo sg_e($sgTimeEarliest); ?></b> 记到 <b><?php echo sg_e($sgTimeLatest); ?></b></span>
      <i>·</i>
      <span>历时 <b><?php echo $sgTimeSpanDays; ?></b> 天<?php
        if ($sgTimeSpanY > 0 && $sgTimeSpanM > 0) : ?>（约 <?php echo $sgTimeSpanY; ?> 年 <?php echo $sgTimeSpanM; ?> 个月）<?php
        elseif ($sgTimeSpanY > 0 && $sgTimeSpanD > 0) : ?>（约 <?php echo $sgTimeSpanY; ?> 年 <?php echo $sgTimeSpanD; ?> 天）<?php
        elseif ($sgTimeSpanY > 0) : ?>（整 <?php echo $sgTimeSpanY; ?> 年）<?php
        elseif ($sgTimeSpanM > 0) : ?>（约 <?php echo $sgTimeSpanM; ?> 个月）<?php
        endif; ?></span>
      <i>·</i>
      <span><?php echo $sgTimeSinceDays > 0 ? '更新 <b>' . $sgTimeSinceDays . '</b> 天前' : '更新于<b>今天</b>'; ?></span>
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
  <?php foreach ($sgTimeYears as $sgYear => $sgYearInfo) : ?>
  <section class="sg-time-year" data-sg-time-year="<?php echo sg_e($sgYear); ?>">
    <div class="sg-time-year-head">
      <h2 class="sg-time-year-num"><?php echo sg_e($sgYear); ?></h2>
      <span class="sg-time-year-meta"><?php echo (int) $sgYearInfo['count']; ?> 条记录 · <?php echo count($sgYearInfo['dates']); ?> 天</span>
    </div>
    <ol class="sg-time">
      <?php foreach ($sgYearInfo['groups'] as $sgGi) : $sgGroup = $sgTimeGroups[$sgGi]; ?>
      <li class="sg-time-item"<?php echo $sgGi === 0 ? ' data-sg-time-latest' : ''; ?>>
        <h3 class="sg-time-when"><span class="sg-time-when-year"><?php echo sg_e(substr($sgGroup['title'], 0, 4)); ?></span><span class="sg-time-when-day"><?php echo sg_e(substr($sgGroup['title'], 5)); ?></span></h3>
        <?php if ($sgGi === 0) : ?><span class="sg-time-latest">最新记录</span><?php endif; ?>
        <div class="sg-time-card">
          <ol class="sg-time-rounds">
            <?php foreach ($sgGroup['items'] as $sgRi => $sgItem) : ?>
            <li class="sg-time-round">
              <span class="sg-time-round-num" aria-hidden="true"><?php echo $sgRi + 1; ?></span>
              <div class="sg-time-round-body">
                <p class="sg-time-round-title"><?php echo $sgItem['lead']; ?><?php echo !empty($sgItem['ms']) ? ' <span class="sg-time-ms-tag">里程碑</span>' : ''; ?></p>
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
  </div>
</main>

<?php $this->need('footer.php'); ?>
