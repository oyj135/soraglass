<?php
/**
 * 「碎语」时间轴 —— page-notes.php 与两个维护旧地址的兼容页（chatter / moments）共用。
 *
 * 第二十一轮重做：碎语不再是「小号文章列表」，而是**一条一条的时间轴**。
 * 定位：比文章短、比说说重 —— 一句正文 + 最多九张图 + 心情与地点，
 * 按「年·月」分组、左侧一条竖轴，扫一眼能看出「哪天在做什么」。
 * 与首页的「碎语」轮播同源（sg_notes()），但这里给的是完整形态。
 *
 * 变量通过 $GLOBALS 传（Typecho 的 need() 是独立作用域，普通变量进不来）。
 */
$sgNotes = sg_notes();
$sgHead = $GLOBALS['SG_NOTES_HEAD'] ?? [];
$sgHead = array_merge([
    'title'       => '碎语',
    'sub'         => '短句与随想 · 想到什么写什么',
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索碎语...',
], is_array($sgHead) ? $sgHead : []);
/* 兼容已有 subtitle 字段，只移除指定的旧合并说明，不改后台内容。 */
$sgHead['sub'] = trim(str_replace('杂谈与说说合并后的', '', (string) $sgHead['sub']));

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';

/* 按「年-月」分组；sg_notes() 已按时间倒序，这里只做归堆 */
$sgGroups = [];
foreach ($sgNotes as $sgNote) {
    $sgKey = (string) ($sgNote['group'] ?? '');
    if ($sgKey === '') {
        $sgKey = 'misc';
    }
    if (!isset($sgGroups[$sgKey])) {
        $sgGroups[$sgKey] = [
            'label' => (string) ($sgNote['groupLabel'] ?? '未归类'),
            'items' => [],
        ];
    }
    $sgGroups[$sgKey]['items'][] = $sgNote;
}

/* 标签胶囊：按出现次数排序，最多 8 个，避免一排胶囊把页头压垮 */
$sgTagCount = [];
foreach ($sgNotes as $sgNote) {
    $sgTag = (string) ($sgNote['tag'] ?? '');
    if ($sgTag !== '') {
        $sgTagCount[$sgTag] = ($sgTagCount[$sgTag] ?? 0) + 1;
    }
}
arsort($sgTagCount);
$sgTags = array_slice(array_keys($sgTagCount), 0, 8);

/* 卡片作者栏：头像与名字取主题设置，没填头像就用内置的 */
$sgAuthor = trim((string) sg_opt('sgAuthorName', ''));
if ($sgAuthor === '') {
    $sgAuthor = (string) sg_opt('sgNavTitle', 'Sonora');
}
$sgAvatar = sg_media((string) sg_opt('sgAvatarUrl', ''), sg_theme_url('dist/img/avatar.svg'));
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="notes" data-sg-notes>
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
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

  <div class="sg-timeline">
    <?php foreach ($sgGroups as $sgGroupKey => $sgGroup) : ?>
    <section class="sg-tl-month" data-sg-tl-month="<?php echo sg_e((string) $sgGroupKey); ?>">
      <h2 class="sg-tl-month-label">
        <span><?php echo sg_e($sgGroup['label']); ?></span>
        <b><?php echo count($sgGroup['items']); ?></b>
      </h2>
      <ol class="sg-tl-items" data-sg-tl-items>
        <?php foreach ($sgGroup['items'] as $sgNote) : ?>
        <?php
        $sgBody  = trim((string) ($sgNote['body'] ?? ''));
        if ($sgBody === '') {
            $sgBody = (string) ($sgNote['text'] ?? '');
        }
        $sgTitle = (string) ($sgNote['title'] ?? '');
        $sgTag   = (string) ($sgNote['tag'] ?? '');
        $sgMood  = (string) ($sgNote['mood'] ?? '');
        $sgPlace = (string) ($sgNote['location'] ?? '');
        $sgLink  = (string) ($sgNote['url'] ?? '');
        $sgImgs  = is_array($sgNote['images'] ?? null) ? array_values($sgNote['images']) : [];
        $sgTs    = (int) ($sgNote['ts'] ?? 0);
        $sgKeywords = implode(' ', array_filter([$sgTitle, $sgBody, $sgTag, $sgMood, $sgPlace]));
        ?>
        <li class="sg-tl-item" data-sg-tl-item data-ts="<?php echo $sgTs; ?>" data-tag="<?php echo sg_e($sgTag); ?>" data-keywords="<?php echo sg_e($sgKeywords); ?>">
          <span class="sg-tl-dot" aria-hidden="true"></span>
          <article class="sg-glass sg-tl-card">
            <header class="sg-tl-author">
              <img class="sg-tl-avatar" src="<?php echo sg_e($sgAvatar); ?>" alt="" referrerpolicy="no-referrer" loading="lazy">
              <div class="sg-tl-who">
                <b><?php echo sg_e($sgAuthor); ?></b>
                <span class="sg-tl-when">
                  <?php echo sg_icon_color('clock'); ?>
                  <time datetime="<?php echo sg_e(date('c', $sgTs)); ?>"><?php echo sg_e((string) ($sgNote['date'] ?? '')); ?><?php if ((string) ($sgNote['time'] ?? '') !== '') : ?> <?php echo sg_e((string) $sgNote['time']); ?><?php endif; ?></time>
                </span>
              </div>
              <?php if ($sgTag !== '') : ?><span class="sg-tl-tag">#<?php echo sg_e($sgTag); ?></span><?php endif; ?>
              <?php if ($sgMood !== '') : ?><span class="sg-tl-mood"><?php echo sg_e($sgMood); ?></span><?php endif; ?>
            </header>

            <?php if ($sgTitle !== '') : ?>
            <h3 class="sg-tl-title">
              <?php if ($sgLink !== '') : ?><a href="<?php echo sg_e($sgLink); ?>"><?php echo sg_e($sgTitle); ?></a><?php else : ?><?php echo sg_e($sgTitle); ?><?php endif; ?>
            </h3>
            <?php endif; ?>

            <?php if ($sgBody !== '') : ?>
            <p class="sg-tl-text"><?php echo nl2br(sg_e($sgBody)); ?></p>
            <?php endif; ?>

            <?php if ($sgImgs) : ?>
            <?php
            /* 1 张给大图、2 张并排、3 张一行、4 张 2×2、其余九宫格；
               全部走全局灯箱（data-sg-photo + data-sg-photo-group）。 */
            $sgGrid = count($sgImgs);
            $sgSize = $sgGrid === 1 ? 'is-one' : ($sgGrid === 2 ? 'is-two' : ($sgGrid === 4 ? 'is-four' : 'is-many'));
            ?>
            <div class="sg-tl-media <?php echo $sgSize; ?>" data-sg-photo-group>
              <?php foreach ($sgImgs as $sgImgIndex => $sgImg) : ?>
              <a class="sg-photo" href="<?php echo sg_e($sgImg); ?>" data-sg-photo>
                <img src="<?php echo sg_e($sgImg); ?>" alt="<?php echo sg_e($sgTitle !== '' ? $sgTitle . ' 第 ' . ((int) $sgImgIndex + 1) . ' 张' : '碎语配图 ' . ((int) $sgImgIndex + 1)); ?>" loading="lazy" referrerpolicy="no-referrer">
              </a>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <footer class="sg-tl-foot">
              <?php if ($sgPlace !== '') : ?><span class="sg-tl-loc"><?php echo sg_icon('map-pin'); ?><?php echo sg_e($sgPlace); ?></span><?php endif; ?>
              <?php if ($sgLink !== '') : ?><a class="sg-tl-more" href="<?php echo sg_e($sgLink); ?>">原文<?php echo sg_icon('arrow-up-right'); ?></a><?php endif; ?>
            </footer>
          </article>
        </li>
        <?php endforeach; ?>
      </ol>
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
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php endif; ?>
</main>
