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
?>
<main id="sg-main" class="sg-main" data-sg-page="index">
  <form class="sg-search" role="search" method="get" action="<?php echo sg_e($sgHome); ?>">
    <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
    <input class="sg-search-input" type="search" name="s" placeholder="搜寻标题、描述或标签..." aria-label="站内搜索">
  </form>

  <div class="sg-stack">
    <?php
    /* 主编区（2026-11-14 第三十六轮）：封面轮播从第二屏提到首屏当主视觉，
       个人卡与播放器退到右侧副栏。
       改之前是「个人卡 7 + 播放器 5」并排、轮播压在第二行只露 100px —— 四块等权、
       整屏没有一张图，视线没有落点。改成「一张封面 + 两个副卡」，
       右栏两卡靠 flex:1 均分左列高度，不写死像素。 */
    ?>
    <div class="sg-grid sg-grid-12 sg-hero">
      <section class="sg-glass sg-post-carousel sg-col-7 sg-hero-cover" data-sg-carousel data-sg-interval="5000" data-sg-hover>
        <?php $GLOBALS['SG_SLIDE_SEEN'] = false; ?>
        <?php
        /* 碎语不进首页封面轮播（2026-11-13）：碎语在下方有专门的轮播区块。
           用「总数 > 碎语数」判断是否还有普通文章，避免全是碎语时轮播空着。 */
        $sgHasPlainPost = true;
        try {
            $sgHasPlainPost = (int) $this->getTotal() > count(sg_note_cids());
        } catch (\Throwable $e) {
            $sgHasPlainPost = true;
        }
        ?>
        <?php if ($this->have() && $sgHasPlainPost) : ?>
          <?php while ($this->next()) : ?>
            <?php if (!sg_is_note((int) $this->cid)) : ?>
            <?php $this->need('partials/slide-post.php'); ?>
            <?php endif; ?>
          <?php endwhile; ?>
        <?php else : ?>
          <article class="sg-post-slide is-active">
            <img class="sg-slide-cover" src="<?php echo sg_e($sgCoverFallback); ?>" alt="">
            <span class="sg-slide-veil"></span>
            <div class="sg-slide-body">
              <span class="sg-slide-chips"><span class="sg-chip--tag">空璃</span></span>
              <h2 class="sg-slide-title">还没有文章，去后台写下第一篇吧</h2>
              <span class="sg-slide-desc">发布后这里会自动变成封面轮播。</span>
            </div>
          </article>
        <?php endif; ?>
        <div class="sg-dots" data-sg-dots></div>
      </section>

      <div class="sg-col-5 sg-hero-side">
        <section class="sg-glass sg-card-pad sg-profile" data-sg-hover data-sg-spotlight><?php $this->need('partials/card-profile.php'); ?></section>
        <section class="sg-glass sg-card-pad sg-player-card" data-sg-hover data-sg-spotlight><?php $this->need('partials/player.php'); ?></section>
      </div>
    </div>

    <?php $this->need('partials/lyric-bar.php'); ?>

    <?php
    /* 第二行（第三十六轮）：原来是 4:8 的「轮播 + 海报 / 碎语 / 昼夜」，
       轮播上移后改成 5:4:3 三卡同排 —— 海报仍是最大的一块，碎语居中，
       昼夜收成最小的一个记号（切主题的入口另外还有左下浮动按钮与抽屉）。 */
    ?>
    <div class="sg-grid sg-grid-12">
      <a class="sg-poster-wide sg-col-5" href="<?php echo sg_e($sgPoster['link'] !== '' ? $sgPoster['link'] : $sgHome); ?>" data-sg-hover data-sg-spotlight>
        <img src="<?php echo sg_e($sgPoster['cover']); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
        <span class="sg-veil"></span>
        <div class="sg-poster-body">
          <span class="sg-poster-kicker"><?php echo sg_icon('image'); ?><?php echo sg_e($sgPoster['kicker']); ?></span>
          <h3><?php echo sg_e($sgPoster['title']); ?></h3>
          <p><?php echo sg_e($sgPoster['desc']); ?></p>
        </div>
      </a>

      <div class="sg-glass sg-chatter sg-col-4" data-sg-carousel data-sg-hover>
        <?php $GLOBALS['SG_SLIDE_SEEN'] = false; ?>
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

      <?php $this->need('partials/theme-block.php'); ?>
    </div>

    <?php $this->need('partials/briefing.php'); ?>
  </div>
</main>
<?php $this->need('footer.php'); ?>
