<?php
/**
 * 每日选刊（首页最下方那块）。
 *
 * 结构对齐源站 components/DailyBriefing.tsx：
 *   封面横幅（DAILY SIGNAL / 日期 / 大标题 / 导语 / 频道计数 / 展开按钮 / 期号翻页）
 *   └ 展开区（本期来信 + 往期查看 + 只看收藏 + 双栏目录 + 页脚说明）
 *
 * 数据两种格式都吃：
 *   新：{"channels":[{"id","name"}],"issues":[{"date","weekday","label","title","desc","cover","notice","items":[...]}]}
 *   旧：[{"date","title","desc","cover","label","meta":[...]}]（没有 items，只出横幅）
 * 旧格式是上一版线上已经存进 options 的结构，直接抛弃会让后台配置一夜消失。
 *
 * 全部内容服务端渲染，JS 只做「切期 / 展开 / 收藏 / 弹窗」，不重建 DOM。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}

$sgRaw = sg_json('sgBriefing', 'briefing', []);
$sgChannels = [];
$sgIssues = [];

$sgNormalizeItems = static function (array $items, array $channels): array {
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $out[] = [
            'id'         => (string) ($item['id'] ?? md5($title)),
            'channel'    => (string) ($item['channel'] ?? $item['category'] ?? ($channels[0]['id'] ?? '')),
            'title'      => $title,
            'summary'    => (string) ($item['summary'] ?? $item['desc'] ?? ''),
            'source'     => (string) ($item['source'] ?? ''),
            'sourceDate' => (string) ($item['sourceDate'] ?? $item['date'] ?? ''),
            'url'        => (string) ($item['url'] ?? ''),
            'note'       => (string) ($item['note'] ?? $item['readingNote'] ?? ''),
            'highlights' => array_values(array_filter(array_map('strval', (array) ($item['highlights'] ?? [])))),
        ];
    }
    return $out;
};

if (isset($sgRaw['issues']) && is_array($sgRaw['issues'])) {
    foreach (($sgRaw['channels'] ?? []) as $channel) {
        if (is_array($channel) && isset($channel['id'])) {
            $sgChannels[] = ['id' => (string) $channel['id'], 'name' => (string) ($channel['name'] ?? $channel['id'])];
        }
    }
    foreach ($sgRaw['issues'] as $issue) {
        if (!is_array($issue)) {
            continue;
        }
        $items = $sgNormalizeItems((array) ($issue['items'] ?? []), $sgChannels);
        $sgIssues[] = [
            'date'     => (string) ($issue['date'] ?? ''),
            'weekday'  => (string) ($issue['weekday'] ?? ''),
            'label'    => (string) ($issue['label'] ?? '每日选刊'),
            'kicker'   => (string) ($issue['kicker'] ?? '每日拾光'),
            'title'    => (string) ($issue['title'] ?? ''),
            'desc'     => (string) ($issue['desc'] ?? ''),
            'cover'    => (string) ($issue['cover'] ?? ''),
            'notice'   => (string) ($issue['notice'] ?? ''),
            'items'    => $items,
        ];
    }
} elseif ($sgRaw) {
    // 兼容旧的扁平数组：每项当成一期没有条目的选刊
    foreach ($sgRaw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $sgIssues[] = [
            'date'    => (string) ($row['date'] ?? ''),
            'weekday' => '',
            'label'   => (string) ($row['label'] ?? '每日选刊'),
            'kicker'  => (string) ($row['badge'] ?? 'DAILY SIGNAL'),
            'title'   => (string) ($row['title'] ?? ''),
            'desc'    => (string) ($row['desc'] ?? ''),
            'cover'   => (string) ($row['cover'] ?? ''),
            'notice'  => '',
            'items'   => [],
        ];
    }
}

if (!$sgIssues) {
    return;
}

if (!$sgChannels) {
    $sgChannels = [['id' => 'note', 'name' => '笔记']];
}

$sgTotal = count($sgIssues);
$sgFirst = $sgIssues[0];
?>

<section class="sg-glass sg-briefing" data-sg-briefing data-sg-spotlight>
  <div class="sg-briefing-hero">
    <div class="sg-briefing-covers" aria-hidden="true">
      <?php foreach ($sgIssues as $sgIndex => $sgIssue) : ?>
      <?php $sgCover = sg_media($sgIssue['cover'], sg_placeholder(1015 + (int) $sgIndex, 1200, 700)); ?>
      <img class="sg-briefing-cover<?php echo $sgIndex === 0 ? ' is-active' : ''; ?>"
           src="<?php echo sg_e($sgCover); ?>" alt=""
           data-sg-briefing-cover="<?php echo (int) $sgIndex; ?>"
           <?php echo $sgIndex === 0 ? '' : 'loading="lazy"'; ?>>
      <?php endforeach; ?>
    </div>
    <span class="sg-briefing-scrim" aria-hidden="true"></span>

    <button class="sg-briefing-banner" type="button" data-sg-briefing-toggle aria-expanded="false" aria-controls="sg-briefing-reader">
      <span class="sg-briefing-top">
        <span class="sg-briefing-tag">DAILY SIGNAL</span>
        <span class="sg-briefing-date" data-sg-briefing-date><?php echo sg_e($sgFirst['date']); ?></span>
        <span class="sg-briefing-label" data-sg-briefing-label>/ <?php echo sg_e($sgFirst['label']); ?></span>
      </span>

      <span class="sg-briefing-mid">
        <span class="sg-briefing-kicker" data-sg-briefing-kicker><?php echo sg_e($sgFirst['kicker']); ?></span>
        <span class="sg-briefing-title" data-sg-briefing-title><?php echo sg_e($sgFirst['title']); ?></span>
        <span class="sg-briefing-desc" data-sg-briefing-desc><?php echo sg_e($sgFirst['desc']); ?></span>
      </span>

      <span class="sg-briefing-foot">
        <span class="sg-briefing-meta" data-sg-briefing-meta>
          <?php foreach ($sgChannels as $sgChannel) : ?>
          <?php $sgCount = 0; foreach ($sgFirst['items'] as $sgItem) { if ($sgItem['channel'] === $sgChannel['id']) { $sgCount++; } } ?>
          <span><?php echo sg_e($sgChannel['name']); ?><b><?php echo str_pad((string) $sgCount, 2, '0', STR_PAD_LEFT); ?></b></span>
          <?php endforeach; ?>
          <span class="is-unread" data-sg-briefing-unread><i></i><?php echo count($sgFirst['items']); ?> 条未读</span>
        </span>
        <span class="sg-briefing-more">
          <span data-sg-briefing-more-text>展开今日选刊</span>
          <span class="sg-briefing-sign"><?php echo sg_icon('plus'); ?></span>
        </span>
      </span>
    </button>

    <div class="sg-briefing-pager" role="group" aria-label="选刊期号">
      <button type="button" data-sg-briefing-prev aria-label="上一期"<?php echo $sgTotal < 2 ? ' disabled' : ''; ?>><?php echo sg_icon('chevron-left'); ?></button>
      <span data-sg-briefing-index>1/<?php echo $sgTotal; ?></span>
      <button type="button" data-sg-briefing-next aria-label="下一期"<?php echo $sgTotal < 2 ? ' disabled' : ''; ?>><?php echo sg_icon('chevron-right'); ?></button>
      <button type="button" class="sg-briefing-auto" data-sg-briefing-auto aria-label="暂停自动切换" aria-pressed="false"<?php echo $sgTotal < 2 ? ' disabled' : ''; ?>><?php echo sg_icon('pause'); ?></button>
    </div>
  </div>

  <div class="sg-briefing-body" data-sg-briefing-body>
    <div class="sg-briefing-inner">
      <div class="sg-briefing-subhead">
        <div class="sg-briefing-subtitle">
          <h2 data-sg-briefing-headline>本期来信</h2>
          <span data-sg-briefing-subline><?php echo sg_e($sgFirst['date']); ?><?php echo $sgFirst['weekday'] !== '' ? ' · ' . sg_e($sgFirst['weekday']) : ''; ?> · <?php echo count($sgFirst['items']); ?> 则选刊</span>
        </div>
        <div class="sg-briefing-tools">
          <button type="button" data-sg-briefing-archive-toggle aria-expanded="false"><?php echo sg_icon('history'); ?>往期查看</button>
          <button type="button" data-sg-briefing-saved aria-pressed="false"><?php echo sg_icon('bookmark'); ?>只看收藏</button>
        </div>
      </div>

      <div class="sg-briefing-archive" data-sg-briefing-archive hidden>
        <?php if ($sgTotal > 1) : ?>
        <label class="sg-briefing-archive-label" for="sg-briefing-select">翻阅日期</label>
        <select id="sg-briefing-select" data-sg-briefing-select aria-label="选择选刊日期">
          <?php foreach ($sgIssues as $sgIndex => $sgIssue) : ?>
          <option value="<?php echo (int) $sgIndex; ?>"><?php echo sg_e($sgIssue['date']); ?><?php echo $sgIndex === 0 ? ' · 最新' : ''; ?></option>
          <?php endforeach; ?>
        </select>
        <?php else : ?>
        <span class="sg-briefing-archive-label">只有一期，暂时没得翻。</span>
        <?php endif; ?>
      </div>

      <?php foreach ($sgIssues as $sgIndex => $sgIssue) : ?>
      <?php
        $sgCounts = [];
        foreach ($sgChannels as $sgChannelRow) {
            $sgNum = 0;
            foreach ($sgIssue['items'] as $sgRow) {
                if ($sgRow['channel'] === $sgChannelRow['id']) {
                    $sgNum++;
                }
            }
            $sgCounts[] = ['name' => $sgChannelRow['name'], 'count' => $sgNum];
        }
      ?>
      <nav class="sg-briefing-cols"
           data-sg-briefing-issue="<?php echo (int) $sgIndex; ?>"
           data-date="<?php echo sg_e($sgIssue['date']); ?>"
           data-weekday="<?php echo sg_e($sgIssue['weekday']); ?>"
           data-label="<?php echo sg_e($sgIssue['label']); ?>"
           data-kicker="<?php echo sg_e($sgIssue['kicker']); ?>"
           data-title="<?php echo sg_e($sgIssue['title']); ?>"
           data-desc="<?php echo sg_e($sgIssue['desc']); ?>"
           data-notice="<?php echo sg_e($sgIssue['notice']); ?>"
           data-counts="<?php echo sg_e((string) json_encode($sgCounts, JSON_UNESCAPED_UNICODE)); ?>"
           aria-label="第 <?php echo (int) $sgIndex + 1; ?> 期目录"
           <?php echo $sgIndex === 0 ? '' : 'hidden'; ?>>
        <?php foreach ($sgChannels as $sgChannel) : ?>
        <?php
          $sgGroup = array_values(array_filter($sgIssue['items'], static fn($item) => $item['channel'] === $sgChannel['id']));
        ?>
        <div class="sg-briefing-col">
          <div class="sg-briefing-col-head">
            <i aria-hidden="true"></i>
            <h3><?php echo sg_e($sgChannel['name']); ?></h3>
            <span><?php echo str_pad((string) count($sgGroup), 2, '0', STR_PAD_LEFT); ?></span>
          </div>
          <?php if (!$sgGroup) : ?>
          <p class="sg-briefing-none">这一栏还没有条目。</p>
          <?php endif; ?>
          <?php foreach ($sgGroup as $sgItemIndex => $sgItem) : ?>
          <button class="sg-briefing-item" type="button"
                  data-sg-briefing-item
                  data-id="<?php echo sg_e($sgItem['id']); ?>"
                  data-title="<?php echo sg_e($sgItem['title']); ?>"
                  data-summary="<?php echo sg_e($sgItem['summary']); ?>"
                  data-source="<?php echo sg_e($sgItem['source']); ?>"
                  data-source-date="<?php echo sg_e($sgItem['sourceDate']); ?>"
                  data-url="<?php echo sg_e($sgItem['url']); ?>"
                  data-note="<?php echo sg_e($sgItem['note']); ?>"
                  data-channel="<?php echo sg_e($sgChannel['name']); ?>">
            <span class="sg-briefing-num"><?php echo str_pad((string) ($sgItemIndex + 1), 2, '0', STR_PAD_LEFT); ?></span>
            <span class="sg-briefing-item-main">
              <span class="sg-briefing-item-title"><?php echo sg_e($sgItem['title']); ?></span>
              <?php if ($sgItem['summary'] !== '') : ?><span class="sg-briefing-item-sum"><?php echo sg_e($sgItem['summary']); ?></span><?php endif; ?>
              <span class="sg-briefing-item-meta">
                <?php if ($sgItem['source'] !== '') : ?><span><?php echo sg_e($sgItem['source']); ?></span><?php endif; ?>
                <?php if ($sgItem['sourceDate'] !== '') : ?><span>· <?php echo sg_e($sgItem['sourceDate']); ?></span><?php endif; ?>
                <i class="sg-briefing-dot" aria-hidden="true"></i>
              </span>
            </span>
            <span class="sg-briefing-item-arrow" aria-hidden="true"><?php echo sg_icon('arrow-up-right'); ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
      </nav>
      <?php endforeach; ?>

      <div class="sg-briefing-footnote">
        <p data-sg-briefing-notice><?php echo sg_e($sgFirst['notice']); ?></p>
        <button type="button" data-sg-briefing-collapse>收起目录<?php echo sg_icon('chevron-up'); ?></button>
      </div>
    </div>
  </div>

  <p class="sg-briefing-status" role="status" aria-live="polite" data-sg-briefing-status></p>
</section>

<dialog class="sg-reader" data-sg-briefing-dialog aria-labelledby="sg-reader-title">
  <div class="sg-reader-inner">
    <div class="sg-reader-head">
      <span class="sg-reader-kind" data-sg-reader-kind></span>
      <button class="sg-reader-close" type="button" data-sg-reader-close aria-label="关闭阅读窗口"><?php echo sg_icon('close'); ?></button>
    </div>
    <article class="sg-reader-article">
      <p class="sg-reader-meta" data-sg-reader-meta></p>
      <h2 id="sg-reader-title" data-sg-reader-title></h2>
      <p class="sg-reader-summary" data-sg-reader-summary></p>
      <div class="sg-reader-highlights" data-sg-reader-highlights></div>
      <div class="sg-reader-note" data-sg-reader-note hidden></div>
    </article>
    <div class="sg-reader-foot">
      <button type="button" class="sg-reader-save" data-sg-reader-save aria-pressed="false"><?php echo sg_icon('bookmark'); ?>留着慢慢读</button>
      <a class="sg-reader-link" data-sg-reader-link href="#" target="_blank" rel="noopener noreferrer">查看原文<?php echo sg_icon('arrow-up-right'); ?></a>
    </div>
  </div>
</dialog>
