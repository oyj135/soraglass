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

/* 身份带（P1-1）：搜索上方一条轻量身份区，站点名 + 一句话介绍 + 社交图标。
   不用 h1——首页 h1 已由个人卡占用，这里只是可跳过的标识行。 */
$sgIdentityOn = sg_opt('sgIdentity', 'on') === 'on';
$sgIdName = trim(sg_opt('sgAuthorName', (string) $this->options->title));
$sgIdMotto = trim((string) $this->options->description);
$sgIdAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgIdSocial = sg_json('sgSocial', 'social', []);
?>
<main id="sg-main" class="sg-main" data-sg-page="index">
  <?php if ($sgIdentityOn && ($sgIdName !== '' || $sgIdMotto !== '')) : ?>
  <section class="sg-glass sg-identity" data-sg-hover data-sg-spotlight>
    <a class="sg-identity-avatar" href="<?php echo sg_e($sgHome); ?>" tabindex="-1" aria-hidden="true">
      <img src="<?php echo sg_e($sgIdAvatar); ?>" alt="">
    </a>
    <div class="sg-identity-text">
      <p class="sg-identity-name">
        <?php echo sg_e($sgIdName !== '' ? $sgIdName : (string) $this->options->title); ?>
        <?php if ($sgIdName !== '' && $sgIdName !== (string) $this->options->title) : ?>
        <span class="sg-identity-sub"><?php echo sg_e((string) $this->options->title); ?></span>
        <?php endif; ?>
      </p>
      <?php if ($sgIdMotto !== '') : ?>
      <p class="sg-identity-motto"><?php echo sg_e($sgIdMotto); ?></p>
      <?php endif; ?>
    </div>
    <?php if ($sgIdSocial) : ?>
    <div class="sg-socials sg-identity-socials">
      <?php foreach ($sgIdSocial as $sgIdSocialItem) : ?>
      <?php $sgIdSocialUrl = trim((string) ($sgIdSocialItem['url'] ?? '')); ?>
      <?php if ($sgIdSocialUrl === '') : ?>
      <?php continue; ?>
      <?php endif; ?>
      <a class="sg-social" href="<?php echo sg_e($sgIdSocialUrl); ?>" target="_blank" rel="noopener" aria-label="<?php echo sg_e((string) ($sgIdSocialItem['name'] ?? '')); ?>">
        <?php echo sg_icon(sg_social_icon($sgIdSocialItem)); ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <form class="sg-search" role="search" method="get" action="<?php echo sg_e($sgHome); ?>">
    <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
    <input class="sg-search-input" type="search" name="s" placeholder="搜寻标题、描述或标签..." aria-label="站内搜索">
  </form>

  <div class="sg-stack">
    <?php
    /* 个人刊物：保留 7:5 主副栏，封面图片与阅读信息分区；右栏按内容自然高。 */
    ?>
    <div class="sg-grid sg-grid-12 sg-hero">
      <section class="sg-glass sg-post-carousel sg-col-7 sg-hero-cover" data-sg-carousel data-sg-interval="5000" data-sg-hover>
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

      <div class="sg-col-5 sg-hero-side">
        <section class="sg-glass sg-card-pad sg-profile" data-sg-hover data-sg-spotlight><?php $this->need('partials/card-profile.php'); ?></section>
        <section class="sg-glass sg-player-card" data-sg-hover data-sg-spotlight><?php $this->need('partials/player.php'); ?></section>
      </div>
    </div>

    <?php $this->need('partials/lyric-bar.php'); ?>

    <?php
    /* 第二行（第三十六轮）：原来是 4:8 的「轮播 + 海报 / 碎语 / 昼夜」，
       轮播上移后改成 5:4:3 三卡同排 —— 海报仍是最大的一块，碎语居中，
       昼夜收成最小的一个记号（切主题的入口另外还有左下浮动按钮与抽屉）。 */
    ?>
    <div class="sg-grid sg-grid-12 sg-home-stories">
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
