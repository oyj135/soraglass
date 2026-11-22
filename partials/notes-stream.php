<?php
/**
 * 「碎语」页 —— page-notes.php 与两个维护旧地址的兼容页（chatter / moments）共用。
 *
 * 2026-11-22 重构（第五十九轮）：
 *   结构 = miraii.cn/personal/moments 的 **概览面板**（eyebrow + 标题 + 副标题 + 搜索
 *           + 右侧三张统计卡：总动态 / 含媒体 / 图片）；
 *   内容展示 = 源站 fomal.cc/archives 的 **article-sort 时间轴**（hexo 主题
 *           layout/includes/mixins/article-sort.pug + _page/archives.styl）：
 *           竖轴 border-left + 圆点节点、年份大节点配生肖图标（复刻源站
 *           getAnimalIcon 彩蛋，走 iconfont 12 生肖 symbol）、
 *           条目 = 左侧 120px 封面（有图时，链到碎语正文）+ 日历图标日期 + 碎语文本。
 *   标签筛选 / 排序 / 搜索沿用原 bar 与 notes.ts 契约（data-sg-tl-month/items/item 不变）。
 *
 * 变量通过 $GLOBALS 传（Typecho 的 need() 是独立作用域，普通变量进不来）。
 */
$sgNotes = sg_notes(0);
$sgHead = $GLOBALS['SG_NOTES_HEAD'] ?? [];
$sgHead = array_merge([
    'title'       => '碎语',
    'sub'         => '短句与随想 · 想到什么写什么',
], is_array($sgHead) ? $sgHead : []);
/* 兼容已有 subtitle 字段，只移除指定的旧合并说明，不改后台内容。 */
$sgHead['sub'] = trim(str_replace('杂谈与说说合并后的', '', (string) $sgHead['sub']));

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';

/* 统计：总动态 / 含媒体 / 图片（miraii moments overview 同款三卡） */
$sgMediaNotes = 0;
$sgImageTotal = 0;
foreach ($sgNotes as $sgNote) {
    $sgImgs = is_array($sgNote['images'] ?? null) ? $sgNote['images'] : [];
    if ($sgImgs) {
        $sgMediaNotes++;
        $sgImageTotal += count($sgImgs);
    }
}

/* 按「年」分组（fomal archives 按年挂节点）；sg_notes() 已按时间倒序，这里只做归堆 */
$sgYearGroups = [];
foreach ($sgNotes as $sgNote) {
    $sgYear = substr((string) ($sgNote['date'] ?? ''), 0, 4);
    if ($sgYear === '' || !ctype_digit($sgYear)) {
        $sgYear = gmdate('Y', (int) ($sgNote['created'] ?? 0));
    }
    if (!isset($sgYearGroups[$sgYear])) {
        $sgYearGroups[$sgYear] = [];
    }
    $sgYearGroups[$sgYear][] = $sgNote;
}

/* 年份 → 生肖图标（复刻源站 getAnimalIcon：2020 鼠年起，(年-4) 对 12 取模） */
$sgZodiacList = ['rat', 'ox', 'tiger', 'rabbit', 'dragon', 'snake', 'horse', 'goat', 'monkey', 'rooster', 'dog', 'boar'];
$sgZodiacOf = static function (string $sgYear) use ($sgZodiacList): string {
    $sgIdx = (((((int) $sgYear) - 4) % 12) + 12) % 12;
    return 'icon-' . ($sgZodiacList[$sgIdx] ?? 'horse');
};

/* 标签胶囊：按出现次数排序，最多 8 个 */
$sgTagCount = [];
foreach ($sgNotes as $sgNote) {
    $sgTag = (string) ($sgNote['tag'] ?? '');
    if ($sgTag !== '') {
        $sgTagCount[$sgTag] = ($sgTagCount[$sgTag] ?? 0) + 1;
    }
}
arsort($sgTagCount);
$sgTags = array_slice(array_keys($sgTagCount), 0, 8);
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="notes" data-sg-notes>
  <?php /* 概览面板（miraii moments overview）：eyebrow + 标题 + 副标题 + 搜索 + 三张统计卡 */ ?>
  <section class="sg-moments sg-glass">
    <div class="sg-moments-intro">
      <span class="sg-moments-eyebrow">💬 Moments</span>
      <h1 class="sg-moments-title"><?php echo sg_e((string) $sgHead['title']); ?></h1>
      <?php if ((string) $sgHead['sub'] !== '') : ?><p class="sg-moments-sub"><?php echo sg_e((string) $sgHead['sub']); ?></p><?php endif; ?>
      <div class="sg-moments-search">
        <span class="sg-moments-search-ico"><?php echo sg_icon_color('history'); ?></span>
        <input class="sg-search-input sg-moments-search-input" type="search" name="s"
               placeholder="搜索碎语..." aria-label="搜索碎语" autocomplete="off">
      </div>
    </div>
    <div class="sg-moments-stats" role="group" aria-label="碎语统计">
      <div class="sg-moments-stat"><b><?php echo count($sgNotes); ?></b><span>总动态</span></div>
      <div class="sg-moments-stat"><b><?php echo $sgMediaNotes; ?></b><span>含媒体</span></div>
      <div class="sg-moments-stat"><b><?php echo $sgImageTotal; ?></b><span>图片</span></div>
    </div>
  </section>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin:2rem auto 0;max-width:62rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgNotes) : ?>
  <div class="sg-notes-bar">
    <?php if (count($sgTags) > 1) : ?>
    <div class="sg-notes-tags" role="group" aria-label="按标签筛选">
      <button class="sg-notes-tag is-active" type="button" data-sg-notes-tag="">全部<span><?php echo count($sgNotes); ?></span></button>
      <?php foreach ($sgTags as $sgTagName) : ?>
      <button class="sg-notes-tag" type="button" data-sg-notes-tag="<?php echo sg_e($sgTagName); ?>"><?php echo sg_e($sgTagName); ?><span><?php echo (int) $sgTagCount[$sgTagName]; ?></span></button>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="sg-notes-tags"><span class="sg-notes-count">共 <?php echo count($sgNotes); ?> 条</span></div>
    <?php endif; ?>

    <div class="sg-notes-sort" role="group" aria-label="排序">
      <button class="is-active" type="button" data-sg-notes-sort="desc" aria-pressed="true">最新</button>
      <button type="button" data-sg-notes-sort="asc" aria-pressed="false">最早</button>
    </div>
  </div>

  <?php /* 内容时间轴：fomal.cc/archives 的 article-sort 同构（竖轴 + 年份生肖节点 + 日期/文本条目） */ ?>
  <div class="sg-asort">
    <p class="sg-asort-title">碎语总览 - <?php echo count($sgNotes); ?></p>
    <?php foreach ($sgYearGroups as $sgYear => $sgYearNotes) : ?>
    <section class="sg-asort-year" data-sg-tl-month="<?php echo sg_e((string) $sgYear); ?>">
      <h2 class="sg-asort-year-head">
        <span class="sg-asort-year-num"><?php echo sg_e((string) $sgYear); ?></span>
        <svg class="sg-asort-zodiac" aria-hidden="true"><use href="#<?php echo sg_e($sgZodiacOf($sgYear)); ?>"></use></svg>
        <b><?php echo count($sgYearNotes); ?> 条</b>
      </h2>
      <div class="sg-asort-list" data-sg-tl-items>
        <?php foreach ($sgYearNotes as $sgNote) : ?>
        <?php
        $sgBody  = trim((string) ($sgNote['body'] ?? ''));
        if ($sgBody === '') {
            $sgBody = (string) ($sgNote['text'] ?? '');
        }
        $sgTag   = (string) ($sgNote['tag'] ?? '');
        $sgPlace = (string) ($sgNote['location'] ?? '');
        $sgMood  = (string) ($sgNote['mood'] ?? '');
        $sgLink  = (string) ($sgNote['url'] ?? '');
        $sgImgs  = is_array($sgNote['images'] ?? null) ? array_values($sgNote['images']) : [];
        $sgTs    = (int) ($sgNote['ts'] ?? 0);
        $sgCover = $sgImgs[0] ?? (string) ($sgNote['cover'] ?? '');
        $sgKeywords = implode(' ', array_filter([$sgBody, $sgTag, $sgMood, $sgPlace]));
        ?>
        <div class="sg-asort-item<?php echo $sgCover === '' ? ' no-cover' : ''; ?>" data-sg-tl-item data-ts="<?php echo $sgTs; ?>" data-tag="<?php echo sg_e($sgTag); ?>" data-keywords="<?php echo sg_e($sgKeywords); ?>">
          <?php if ($sgCover !== '') : ?>
          <a class="sg-asort-img" href="<?php echo sg_e($sgLink); ?>" title="<?php echo sg_e($sgBody !== '' ? mb_substr($sgBody, 0, 40) : '查看碎语'); ?>">
            <img src="<?php echo sg_e($sgCover); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
            <?php if (count($sgImgs) > 1) : ?><span class="sg-asort-more"><?php echo count($sgImgs); ?> 图</span><?php endif; ?>
          </a>
          <?php endif; ?>
          <div class="sg-asort-info">
            <p class="sg-asort-time">
              <?php echo sg_icon_color('calendar'); ?>
              <time datetime="<?php echo sg_e(gmdate('c', $sgTs)); ?>" title="<?php echo sg_e(date('Y-m-d H:i', $sgTs)); ?>"><?php echo sg_e(date('Y-m-d', $sgTs)); ?></time>
              <?php if ((string) ($sgNote['time'] ?? '') !== '') : ?><span class="sg-asort-time-hm"><?php echo sg_e((string) $sgNote['time']); ?></span><?php endif; ?>
              <?php if ($sgTag !== '') : ?><span class="sg-tl-tag">#<?php echo sg_e($sgTag); ?></span><?php endif; ?>
              <?php if ($sgPlace !== '') : ?><span class="sg-asort-loc"><?php echo sg_icon_color('pin'); ?><?php echo sg_e($sgPlace); ?></span><?php endif; ?>
              <?php if ($sgMood !== '') : ?><span class="sg-asort-mood"><?php echo sg_e($sgMood); ?></span><?php endif; ?>
            </p>
            <?php if ($sgLink !== '') : ?>
            <a class="sg-asort-text" href="<?php echo sg_e($sgLink); ?>"><?php echo nl2br(sg_e($sgBody)); ?></a>
            <?php else : ?>
            <span class="sg-asort-text"><?php echo nl2br(sg_e($sgBody)); ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endforeach; ?>
  </div>

  <p class="sg-filter-empty" data-sg-notes-empty hidden>没有匹配的碎语，换个词试试。</p>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有碎语</h1>
    <p>写一篇文章、勾上「碎语」分类（缩略名 <code>notes</code>）并发布，这里就会按时间排起来。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon_color('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php endif; ?>
</main>
