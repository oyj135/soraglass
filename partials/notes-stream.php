<?php
/**
 * 「碎语」页 —— page-notes.php 与两个维护旧地址的兼容页（chatter / moments）共用。
 *
 * 2026-11-22 重构（第六十一轮定稿）：
 *   结构 = 全站标准 sg_page_head 页头（大标题 + 副标题压壁纸、居中，与项目/归档等独立页同款；
 *          miraii moments 的概览面板已按用户要求整块移除）+ 三列卡片瀑布流：
 *   卡片 = 头像/昵称 + 相对时间（sg_time_ago）+ 右上大号日数与年月 +
 *   浅色气泡正文（超 10 行可展开）+ 图片栅格（灯箱）+ 尾部标签/心情/地点（不输出原文链接）。
 *   筛选/排序沿用 notes.ts 契约（单组 data-sg-tl-month 承接全部卡片）。
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

/* 卡片作者栏：头像与名字取主题设置，没填头像就用内置的（头像/昵称被卡片引用，
   2026-11-22 曾在重写时丢失定义导致整页 Undefined variable 警告，已回补） */
$sgAuthor = trim((string) sg_opt('sgAuthorName', ''));
if ($sgAuthor === '') {
    $sgAuthor = (string) sg_opt('sgNavTitle', 'Sonora');
}
$sgAvatar = sg_media((string) sg_opt('sgAvatarUrl', ''), sg_theme_url('dist/img/avatar.svg'));
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="notes" data-sg-notes>
  <?php /* 页头：全站标准 sg_page_head（2026-11-22 第六十一轮，概览面板按要求移除） */ ?>
  <?php sg_page_head($sgHead); ?>

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

  <?php /* 卡片瀑布流（miraii moments 同构）：单一分组承接 notes.ts 契约
     （data-sg-tl-month/items/item），卡片按时间倒序/升序、标签筛选全部照旧。
     布局用 CSS 多列分栏（columns: 3）+ break-inside 实现矱子流。 */ ?>
  <section class="sg-moments-section" data-sg-tl-month="all">
    <div class="sg-moments-grid" data-sg-tl-items>
    <?php foreach ($sgNotes as $sgNote) : ?>
    <?php
    $sgBody  = trim((string) ($sgNote['body'] ?? ''));
    if ($sgBody === '') {
        $sgBody = (string) ($sgNote['text'] ?? '');
    }
    $sgTag   = (string) ($sgNote['tag'] ?? '');
    $sgPlace = (string) ($sgNote['location'] ?? '');
    $sgMood  = (string) ($sgNote['mood'] ?? '');
    $sgImgs  = is_array($sgNote['images'] ?? null) ? array_values($sgNote['images']) : [];
    $sgTs    = (int) ($sgNote['ts'] ?? 0);
    $sgCover = $sgImgs[0] ?? (string) ($sgNote['cover'] ?? '');
    $sgAgo   = sg_time_ago($sgTs);
    if ($sgAgo === '') {
        $sgAgo = date('Y-m-d', $sgTs);
    }
    ?>
    <article class="sg-moment" data-sg-tl-item data-ts="<?php echo $sgTs; ?>" data-tag="<?php echo sg_e($sgTag); ?>">
      <header class="sg-moment-head">
        <img class="sg-moment-avatar" src="<?php echo sg_e($sgAvatar); ?>" alt="" referrerpolicy="no-referrer" loading="lazy">
        <div class="sg-moment-who">
          <b><?php echo sg_e($sgAuthor); ?></b>
          <span class="sg-moment-ago"><?php echo sg_e($sgAgo); ?></span>
        </div>
        <div class="sg-moment-day" aria-hidden="true">
          <b><?php echo sg_e(date('j', $sgTs)); ?></b>
          <span><?php echo sg_e(date('Y.m', $sgTs)); ?></span>
        </div>
      </header>
      <?php if ($sgBody !== '') : ?>
      <div class="sg-moment-bubble"><?php echo nl2br(sg_e($sgBody)); ?></div>
      <?php endif; ?>
      <?php if ($sgImgs) : ?>
      <?php /* 1 张大图、2 张并排、3 张一行、4 张 2×2、其余九宫格；全部走全局灯箱。 */ ?>
      <?php
      $sgGrid = count($sgImgs);
      $sgSize = $sgGrid === 1 ? 'is-one' : ($sgGrid === 2 ? 'is-two' : ($sgGrid === 4 ? 'is-four' : 'is-many'));
      ?>
      <div class="sg-moment-media <?php echo $sgSize; ?>" data-sg-photo-group>
        <?php foreach ($sgImgs as $sgImgIndex => $sgImg) : ?>
        <a class="sg-photo" href="<?php echo sg_e($sgImg); ?>" data-sg-photo>
          <img src="<?php echo sg_e($sgImg); ?>" alt="<?php echo sg_e('碎语配图 ' . ((int) $sgImgIndex + 1)); ?>" loading="lazy" referrerpolicy="no-referrer">
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($sgTag !== '' || $sgPlace !== '' || $sgMood !== '') : ?>
      <footer class="sg-moment-foot">
        <?php if ($sgTag !== '') : ?><span class="sg-tl-tag">#<?php echo sg_e($sgTag); ?></span><?php endif; ?>
        <?php if ($sgMood !== '') : ?><span class="sg-moment-mood"><?php echo sg_e($sgMood); ?></span><?php endif; ?>
        <?php if ($sgPlace !== '') : ?><span class="sg-moment-loc"><?php echo sg_icon_color('pin'); ?><?php echo sg_e($sgPlace); ?></span><?php endif; ?>
      </footer>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
    </div>
  </section>
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
