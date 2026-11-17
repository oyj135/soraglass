<?php
/**
 * Soraglass · 空璃
 *
 * 二次元毛玻璃 Typecho 主题，视觉与信息架构对齐 XHBlogs，模板与样式独立实现。
 *
 * @package Soraglass
 * @version 1.2.0
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';

// 宽幅卡：后台可选「光影画廊 / 最新文章 / 自定义」，默认放相册卡（对齐源站 latestAlbum 海报）
$sgPoster = sg_home_poster($this);
$sgCoverFallback = $sgPoster['cover'] !== '' ? $sgPoster['cover'] : sg_theme_url('dist/img/cover-default.svg');

// 碎语轮播：读 JSON 短记（合并了原来的「杂谈 / 说说」两页），取最新 4 条
$sgNotes = array_slice(sg_notes(), 0, 4);
$sgNotesLink = sg_page_url('notes');

// 封面轮播：单独取最近的文章（跳过碎语），不受列表「每页条数上限」影响
$sgSlides = sg_home_slides(5);

// 正文文章列表（2026-11-17 首页重构）：独立取普通文章，跳过碎语，取固定 2 篇。
// 碎语有两种挂法：分类（slug=notes 或名称「碎语」）与「碎语」标签，两个 cid 集合并集一起剔。
// 用 Widget_Archive@sg_home_list（type=index）而非 Widget_Contents_Post_Recent——
// 后者不查 text，摘要恒空；这里列表卡要显示引号摘要，必须用 Archive。
$sgHomeListHtml = '';
$sgHomeListCount = 0;
$sgHomeListLimit = 2;
try {
    $sgNoteCids = array_values(array_unique(array_merge(sg_note_cids(), sg_chatter_tag_cids())));
    ob_start();
    $this->widget('Widget_Archive@sg_home_list', 'pageSize=' . ($sgHomeListLimit + count($sgNoteCids)) . '&type=index')->to($sgList);
    while ($sgList->next()) {
        if (in_array((int) $sgList->cid, $sgNoteCids, true)) {
            continue;
        }
        ++$sgHomeListCount;
        $sgCardArchive = $sgList;
        $this->need('partials/card-post.php');
        if ($sgHomeListCount >= $sgHomeListLimit) {
            break;
        }
    }
    $sgHomeListHtml = (string) ob_get_clean();
} catch (\Throwable $e) {
    $sgHomeListHtml = '';
}
unset($sgCardArchive);
?>
<main id="sg-main" class="sg-main sg-main--home" data-sg-page="index">
  <?php /* 100vh 全屏图片首屏：参考 Fomalhaut #page-header.full_page。命名空间 .sg-front*，与首页主区 .sg-hero* 隔离。 */ ?>
  <?php $this->need('partials/home-front.php'); ?>

  <form class="sg-search" role="search" method="get" action="<?php echo sg_e($sgHome); ?>">
    <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
    <input class="sg-search-input" type="search" name="s" placeholder="搜寻标题、描述或标签..." aria-label="站内搜索">
  </form>

  <?php
  /* 2026-11-16 首页重构：正文两栏 —— 左侧主内容（轮播 / 文章列表 / 歌词 / 三卡 / 日报），
     右侧五卡（作者 / 音乐 / 日历 / 春节倒计时 / 小站资讯），对齐 Fomalhaut 74% : 26%。
     所有既有模块 HTML 全部保留，只是换到新容器里，不删任何内容。 */
  ?>
  <div class="sg-home-body">
    <div class="sg-home-main">
      <div class="sg-stack">
        <?php
        /* 个人刊物：封面轮播全宽，右栏内容已移到旁边的 sg-home-aside。 */
        ?>
        <div class="sg-hero">
        <section class="sg-glass sg-post-carousel sg-hero-cover" data-sg-carousel data-sg-interval="5000" data-sg-hover>
          <?php if ($sgSlides) : ?>
            <?php foreach ($sgSlides as $sgSlideIndex => $sgSlide) : ?>
              <article class="sg-post-slide<?php echo $sgSlideIndex === 0 ? ' is-active' : ''; ?>" data-sg-slide>
                <a class="sg-slide-media" href="<?php echo sg_e($sgSlide['url']); ?>" tabindex="-1" aria-hidden="true">
                  <img class="sg-slide-cover" src="<?php echo sg_e($sgSlide['cover']); ?>" alt="" <?php echo $sgSlideIndex === 0 ? '' : 'loading="lazy"'; ?>>
                </a>
                <a class="sg-slide-body" href="<?php echo sg_e($sgSlide['url']); ?>">
                  <span class="sg-slide-chips">
                    <span class="sg-chip--tag"><?php echo sg_e($sgSlide['category']); ?></span>
                    <span class="sg-chip--mono"><?php echo sg_e($sgSlide['date']); ?></span>
                  </span>
                  <h2 class="sg-slide-title"><?php echo sg_e($sgSlide['title']); ?></h2>
                  <span class="sg-slide-desc"><?php echo sg_e($sgSlide['desc']); ?></span>
                </a>
              </article>
            <?php endforeach; ?>
          <?php else : ?>
            <article class="sg-post-slide is-active" data-sg-slide>
              <div class="sg-slide-media"><img class="sg-slide-cover" src="<?php echo sg_e($sgCoverFallback); ?>" alt=""></div>
              <div class="sg-slide-body">
                <span class="sg-slide-chips"><span class="sg-chip--tag">空璃</span></span>
                <h2 class="sg-slide-title">还没有文章，去后台写下第一篇吧</h2>
                <span class="sg-slide-desc">发布后这里会自动变成封面轮播。</span>
              </div>
            </article>
          <?php endif; ?>
          <div class="sg-dots" data-sg-dots></div>
        </section>
        </div>

        <?php
        /* 首页文章列表：multicard 卡片，形态与归档/分类/标签一致（复用 .sg-feature-card）。 */
        ?>
        <?php if ($sgHomeListHtml !== '') : ?>
        <section class="sg-home-latest" id="sg-home-content" aria-label="最新文章">
          <div class="sg-home-section-head">
            <h2 class="sg-home-section-title"><?php echo sg_icon('book-open'); ?>最新文章</h2>
          </div>
          <div class="sg-home-grid">
            <?php echo $sgHomeListHtml; ?>
          </div>
        </section>
        <?php endif; ?>

        <?php $this->need('partials/lyric-bar.php'); ?>

        <?php
        /* 第二行起：相册卡、碎语卡各占整行（2026-11-16 第三轮按用户要求）。
           昼夜切换卡已移除——模式切换统一在右侧悬浮按钮组里。 */
        ?>
        <div class="sg-home-stories">
          <a class="sg-poster-wide" href="<?php echo sg_e($sgPoster['link'] !== '' ? $sgPoster['link'] : $sgHome); ?>" data-sg-hover data-sg-spotlight>
            <img src="<?php echo sg_e($sgPoster['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
            <span class="sg-veil"></span>
            <div class="sg-poster-body">
              <span class="sg-poster-kicker"><?php echo sg_icon('image'); ?><?php echo sg_e($sgPoster['kicker']); ?></span>
              <h3><?php echo sg_e($sgPoster['title']); ?></h3>
              <p><?php echo sg_e($sgPoster['desc']); ?></p>
            </div>
          </a>

          <div class="sg-glass sg-chatter" data-sg-carousel data-sg-hover>
            <?php if ($sgNotes) : ?>
              <?php foreach ($sgNotes as $sgNoteIndex => $sgNote) : ?>
                <?php $sgNote['active'] = $sgNoteIndex === 0; ?>
                <?php echo sg_note_slide($sgNote); ?>
              <?php endforeach; ?>
            <?php else : ?>
              <article class="sg-chatter-slide is-active" data-sg-slide>
                <div class="sg-chatter-body sg-chatter-body--empty">
                  <span class="sg-chatter-meta">
                    <span class="sg-chip--tag"><?php echo sg_icon('tag'); ?>碎语</span>
                    <time>空空如也</time>
                  </span>
                  <h3>还没有碎语</h3>
                  <p>后台「碎语 JSON」里写两条随手记，这里就会活起来。</p>
                  <a class="sg-chatter-link" href="<?php echo sg_e($sgNotesLink); ?>">
                    去看看<?php echo sg_icon('arrow-up-right'); ?>
                  </a>
                </div>
              </article>
            <?php endif; ?>
            <span class="sg-dots" data-sg-dots></span>
          </div>
        </div>

        <?php $this->need('partials/briefing.php'); ?>
      </div>
    </div>

    <?php /* 右侧五卡：作者 → 音乐 → 日历 → 春节倒计时 → 小站资讯。命名空间 .sg-h*，兼顾现有 .sg-hero-side 压缩样式。 */ ?>
    <aside class="sg-home-aside" aria-label="站点侧栏">
      <section class="sg-glass sg-card-pad sg-profile" data-sg-hover data-sg-spotlight><?php $this->need('partials/card-profile.php'); ?></section>
      <section class="sg-glass sg-player-card" data-sg-hover data-sg-spotlight><?php $this->need('partials/player.php'); ?></section>
      <?php $this->need('partials/side-calendar.php'); ?>
      <?php $this->need('partials/side-countdown.php'); ?>
      <?php $this->need('partials/side-webinfo.php'); ?>
    </aside>
  </div>
</main>
<?php $this->need('footer.php'); ?>
