<?php
/** 文章页侧栏作者名片（2026-11-17：内容与首页作者卡完全一致）——
 *  居中圆头像（链到关于页）+ 名字/简介 + 文章/标签/分类三格统计 + 全宽「关于我」按钮。
 *  复用首页 card-profile 的类名与样式（sg-profile-top / sg-avatar-ring / sg-stats），
 *  社交图标与首页作者卡一致地不在此卡输出（首屏社交不受影响）。 */
$sgAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgCounts = sg_stats();
$sgName = sg_opt('sgAuthorName', (string) $this->options->title);
$sgBio = (string) $this->options->description;
$sgArchiveUrl = sg_page_url('archives');
$sgAvatarLink = sg_page_exists('about') ? sg_page_url('about') : rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<section class="sg-glass sg-card-pad sg-profile sg-author-card">
  <div class="sg-profile-top">
    <a class="sg-avatar-ring" href="<?php echo sg_e($sgAvatarLink); ?>">
      <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgName); ?>">
    </a>
    <p class="sg-profile-name"><?php echo sg_e($sgName); ?></p>
    <?php if ($sgBio !== '') : ?><p class="sg-profile-bio"><?php echo sg_e($sgBio); ?></p><?php endif; ?>
  </div>

  <div class="sg-stats">
    <a class="sg-stat" href="<?php echo sg_e($sgArchiveUrl); ?>">
      <span class="sg-stat-label">文章</span>
      <span class="sg-stat-num"><?php echo sg_e($sgCounts['posts']); ?></span>
    </a>
    <a class="sg-stat" href="<?php echo sg_e($sgArchiveUrl); ?>">
      <span class="sg-stat-label">标签</span>
      <span class="sg-stat-num"><?php echo sg_e($sgCounts['tags']); ?></span>
    </a>
    <a class="sg-stat" href="<?php echo sg_e($sgArchiveUrl); ?>">
      <span class="sg-stat-label">分类</span>
      <span class="sg-stat-num"><?php echo sg_e($sgCounts['categories']); ?></span>
    </a>
  </div>

  <?php /* 关于我按钮——关于页存在时才出现，避免死链 */ ?>
  <?php if (sg_page_exists('about')) : ?>
  <a class="sg-author-btn" href="<?php echo sg_e(sg_page_url('about')); ?>">
    <?php echo sg_icon('user'); ?>关于我
  </a>
  <?php endif; ?>
</section>
