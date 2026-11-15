<?php
/**
 * AI 日报（首页最下方那块）。
 *
 * 结构对齐源站 components/DailyBriefing.tsx：
 *   封面横幅（DAILY SIGNAL / 日期 / 大标题 / 导语 / 频道计数 / 展开按钮 / 期号翻页）
 *   └ 展开区（本期来信 + 往期查看 + 只看收藏 + 双栏目录 + 页脚说明）
 *
 * 数据只有一个来源：lib/ai.php 的 sg_ai_issues()（读取缓存，响应后由模型从 RSS 候选里挑）。
 * 没有 AI 期就不输出这一块 —— 静态「每日选刊 JSON」已在第二十二轮删除。
 *
 * 全部内容服务端渲染，JS 只做「切期 / 展开 / 收藏 / 弹窗」，不重建 DOM。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}

/* 第二十一轮起：首页这一块**只用 AI 日报**。
   模型每天挑一份（见 lib/ai.php），拿不到（没配 Key / 没配 RSS / 接口报错）就整块不输出 ——
   不再有「静态选刊」兜底，免得页面上一半是手写数据一半是模型数据，分不清哪条该信。 */
$sgIssues = sg_ai_issues(5);
$sgAiIssue = $sgIssues[0] ?? null;

if (!is_array($sgAiIssue) || empty($sgAiIssue['items'])) {
    /* 站长视角：整块不输出时也要给一行原因，否则「我填了怎么没显示」完全无从查起。
       访客什么也看不到，源码里也没有。 */
    if (sg_user_is_admin()) {
        $sgAiWhy = sg_ai_error();
        if ($sgAiWhy === '') {
            $sgAiWhy = sg_ai_enabled()
                ? '这一期还没生成（可能正在请求模型），刷新一次试试。'
                : '还没配置：填写模型、API Key 与 RSS 源后即可自动生成主题。';
        }
        echo '<div class="sg-briefing-diag" data-sg-briefing-diag>'
            . '<b>AI 日报没有输出</b>：' . sg_e($sgAiWhy)
            . '</div>';
    }
    return;
}

$sgChannels = [
    ['id' => 'ai', 'name' => '模型与应用'],
    ['id' => 'paper', 'name' => '研究与探索'],
];

/* 箭头只翻所选日期的主题，历史日期由「往期查看」切换。 */
$sgFirst = $sgIssues[0];
$sgTotal = count(array_filter($sgIssues, static fn($issue) => $issue['date'] === $sgFirst['date']));
$sgDates = array_values(array_unique(array_column($sgIssues, 'date')));

/** 某一期里真正有条目的栏目（空栏目直接不渲染，避免大片「这一栏还没有条目」） */
$sgIssueChannels = static function (array $issue) use ($sgChannels): array {
    $list = [];
    foreach ($sgChannels as $channel) {
        foreach ((array) $issue['items'] as $item) {
            if (($item['channel'] ?? '') === $channel['id']) {
                $list[] = $channel;
                break;
            }
        }
    }
    return $list;
};

// 卡片背景图：后台「AI 日报背景图」一行贴一个地址，两张及以上自动轮播；
// 留空则回落到每期选刊的封面，翻「‹ 2/3 ›」时跟着换。
$sgBgList = sg_bg_list((string) sg_opt('sgBriefingBg', ''));
$sgBgTotal = $sgBgList ? count($sgBgList) : count($sgIssues);
?>


<section class="sg-glass sg-briefing" data-sg-briefing data-sg-spotlight>
  <div class="sg-briefing-hero">
    <div class="sg-briefing-covers" aria-hidden="true"
         data-sg-briefing-bg
         data-sg-bg-source="<?php echo $sgBgList ? 'custom' : 'issue'; ?>"
         data-sg-bg-count="<?php echo $sgBgTotal > 0 ? (int) $sgBgTotal : 1; ?>"
         data-sg-bg-interval="7000">
      <?php if ($sgBgList) : ?>
      <!-- 后台指定了背景图：同组内自动循环，翻期时跳到对应序号并暂停 12 秒 -->
      <?php foreach ($sgBgList as $sgBgIndex => $sgBgSrc) : ?>
      <img class="sg-briefing-cover<?php echo $sgBgIndex === 0 ? ' is-active' : ''; ?>"
           src="<?php echo sg_e($sgBgSrc); ?>" alt="" referrerpolicy="no-referrer"
           data-sg-bg-index="<?php echo (int) $sgBgIndex; ?>"
           <?php echo $sgBgIndex === 0 ? '' : 'loading="lazy"'; ?>>
      <?php endforeach; ?>
      <?php else : ?>
      <?php foreach ($sgIssues as $sgIndex => $sgIssue) : ?>
      <?php $sgCover = sg_media($sgIssue['cover'], sg_placeholder(1015 + (int) $sgIndex, 1200, 700)); ?>
      <img class="sg-briefing-cover<?php echo $sgIndex === 0 ? ' is-active' : ''; ?>"
           src="<?php echo sg_e($sgCover); ?>" alt="" referrerpolicy="no-referrer"
           data-sg-bg-index="<?php echo (int) $sgIndex; ?>"
           <?php echo $sgIndex === 0 ? '' : 'loading="lazy"'; ?>>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <span class="sg-briefing-scrim" aria-hidden="true"></span>

    <button class="sg-briefing-banner" type="button" data-sg-briefing-toggle aria-expanded="false" aria-controls="sg-briefing-reader">
      <span class="sg-briefing-top">
        <span class="sg-briefing-tag">DAILY SIGNAL</span>
        <span class="sg-briefing-date" data-sg-briefing-date><?php echo sg_e($sgFirst['date']); ?></span>
        <span class="sg-briefing-label" data-sg-briefing-label>/ <?php echo sg_e($sgFirst['label']); ?></span>
        <?php if (($sgFirst['theme'] ?? '') !== '') : ?>
        <span class="sg-briefing-theme" data-sg-briefing-theme><?php echo sg_e((string) $sgFirst['theme']); ?></span>
        <?php endif; ?>
      </span>

      <span class="sg-briefing-mid">
        <span class="sg-briefing-kicker" data-sg-briefing-kicker><?php echo sg_e($sgFirst['kicker']); ?></span>
        <span class="sg-briefing-title" data-sg-briefing-title><?php echo sg_e($sgFirst['title']); ?></span>
        <span class="sg-briefing-desc" data-sg-briefing-desc><?php echo sg_e($sgFirst['desc']); ?></span>
      </span>

      <span class="sg-briefing-foot">
        <span class="sg-briefing-meta" data-sg-briefing-meta>
          <?php foreach ($sgChannels as $sgChannel) : ?>
          <?php
            $sgCount = 0;
            foreach ($sgFirst['items'] as $sgItem) {
                if ($sgItem['channel'] === $sgChannel['id']) {
                    $sgCount++;
                }
            }
            if ($sgCount === 0) {
                continue;
            }
          ?>
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
        <?php if (count($sgDates) > 1) : ?>
        <label class="sg-briefing-archive-label" for="sg-briefing-select">翻阅日期</label>
        <select id="sg-briefing-select" data-sg-briefing-select aria-label="选择选刊日期">
          <?php foreach ($sgDates as $sgIndex => $sgDate) : ?>
          <option value="<?php echo sg_e($sgDate); ?>"><?php echo sg_e($sgDate); ?><?php echo $sgIndex === 0 ? ' · 最新' : ''; ?></option>
          <?php endforeach; ?>
        </select>
        <?php else : ?>
        <span class="sg-briefing-archive-label">目前只有一个生成日期。</span>
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
      <?php $sgCols = $sgIssueChannels($sgIssue); ?>
      <nav class="sg-briefing-cols<?php echo count($sgCols) < 2 ? ' is-single' : ''; ?>"
           data-sg-briefing-issue="<?php echo (int) $sgIndex; ?>"
           data-date="<?php echo sg_e($sgIssue['date']); ?>"
           data-weekday="<?php echo sg_e($sgIssue['weekday']); ?>"
           data-theme="<?php echo sg_e((string) ($sgIssue['theme'] ?? '')); ?>"
           data-label="<?php echo sg_e($sgIssue['label']); ?>"
           data-kicker="<?php echo sg_e($sgIssue['kicker']); ?>"
           data-title="<?php echo sg_e($sgIssue['title']); ?>"
           data-desc="<?php echo sg_e($sgIssue['desc']); ?>"
           data-notice="<?php echo sg_e($sgIssue['notice']); ?>"
           data-counts="<?php echo sg_e((string) json_encode($sgCounts, JSON_UNESCAPED_UNICODE)); ?>"
           aria-label="第 <?php echo (int) $sgIndex + 1; ?> 期目录"
           <?php echo $sgIndex === 0 ? '' : 'hidden'; ?>>
        <?php foreach ($sgCols as $sgChannel) : ?>
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
                  data-title-zh="<?php echo sg_e($sgItem['titleZh'] ?? ''); ?>"
                  data-points="<?php echo sg_e((string) json_encode($sgItem['points'] ?? [], JSON_UNESCAPED_UNICODE)); ?>"
                  data-tags="<?php echo sg_e((string) json_encode($sgItem['tags'] ?? [], JSON_UNESCAPED_UNICODE)); ?>"
                  data-minutes="<?php echo (int) ($sgItem['minutes'] ?? 1); ?>"
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
      <p class="sg-reader-sub" data-sg-reader-sub hidden></p>
      <p class="sg-reader-summary" data-sg-reader-summary></p>
      <div class="sg-reader-highlights" data-sg-reader-highlights></div>
      <div class="sg-reader-note" data-sg-reader-note hidden></div>
      <div class="sg-reader-tags" data-sg-reader-tags hidden></div>
    </article>
    <div class="sg-reader-foot">
      <button type="button" class="sg-reader-save" data-sg-reader-save aria-pressed="false"><?php echo sg_icon('bookmark'); ?>留着慢慢读</button>
      <a class="sg-reader-link" data-sg-reader-link href="#" target="_blank" rel="noopener noreferrer">查看原文<?php echo sg_icon('arrow-up-right'); ?></a>
    </div>
  </div>
</dialog>
