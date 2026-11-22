<?php
/**
 * 时光机（网站建设时光）
 *
 * @package custom
 *
 * 参考源站 Fomalhaut「旧时光」（www.fomal.cc/site/time）：按日期分组的编号列表，
 * 逐条记录小站建设进程；日期节点压在一条竖轴上，每张卡内自动编号。
 *
 * 数据来自主题包 data/history.md —— 唯一母本是仓库 docs/HISTORY.md
 * （check.mjs 文档归置规则禁止源码树出现 .md，npm run release 时拷入部署包）。
 * 母本格式：`## 日期` 分组 + `- 条目` 列表，行内 `**加粗**` 与 `` `代码` `` 可用；
 * 文件缺失或为空时输出引导空态，不影响其它页面。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

/* 解析 history.md → [ ['title' => 日期, 'items' => ['<li>内联HTML', …]], … ]。
   只支持时光记录用得到的极小子集：## / ### 标题、数字或 - 列表、行内加粗与代码，其余原样转义。 */
$sgTimeParse = static function (string $raw): array {
    $groups = [];
    $inline = static function (string $s): string {
        $s = sg_e($s);
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
        return $s;
    };
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
        if ($text !== '') {
            $groups[count($groups) - 1]['items'][] = $inline($text);
        }
    }
    return array_values(array_filter($groups, static fn (array $g): bool => $g['items'] !== []));
};

$sgTimeFile = __DIR__ . '/data/history.md';
$sgTimeRaw = is_readable($sgTimeFile) ? (string) file_get_contents($sgTimeFile) : '';
$sgTimeGroups = $sgTimeRaw !== '' ? $sgTimeParse($sgTimeRaw) : [];
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="time">
  <?php sg_page_head([
      'title'  => (string) $this->title,
      'sub'    => sg_page_sub($this, '小站建设进程'),
      'search' => false,
      'center' => true,
  ]); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgTimeGroups) : ?>
  <ol class="sg-time" aria-label="网站建设时光轴">
    <?php foreach ($sgTimeGroups as $sgGroup) : ?>
    <li class="sg-time-item">
      <h2 class="sg-time-when"><?php echo sg_e($sgGroup['title']); ?></h2>
      <div class="sg-time-card">
        <ol class="sg-time-list">
          <?php foreach ($sgGroup['items'] as $sgItem) : ?>
          <li><?php echo $sgItem; ?></li>
          <?php endforeach; ?>
        </ol>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php else : ?>
  <div class="sg-time-empty sg-glass">
    <p>时光轴还没有记录。</p>
    <p class="sg-time-empty-hint">在仓库 <code>docs/HISTORY.md</code> 里按「## 日期 + - 条目」补几行，跑 <code>npm run release</code> 重新打包即可看到这里。</p>
  </div>
  <?php endif; ?>
</main>

<?php $this->need('footer.php'); ?>
