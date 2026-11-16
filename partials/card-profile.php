<?php
/**
 * 首页个人卡：头像左置 + 渐变描边 + 三项统计 + 方形社交图标。
 * 统计值留空时由 lib/config.php 的 sg_stats() 自动查库。
 */
$sgAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgSocial = sg_json('sgSocial', 'social', []);
$sgCounts = sg_stats();
$sgName = sg_opt('sgAuthorName', (string) $this->options->title);
$sgBio = (string) $this->options->description;
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<div class="sg-profile-head">
  <a class="sg-avatar-ring" href="<?php echo sg_e($sgHome); ?>">
    <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgName); ?>">
  </a>
  <div class="sg-profile-main">
    <h1 class="sg-profile-name"><?php echo sg_e($sgName); ?></h1>
    <?php if ($sgBio !== '') : ?><p class="sg-profile-bio"><?php echo sg_e($sgBio); ?></p><?php endif; ?>
  </div>
</div>

<?php /* P1-2：关于我按钮——关于页存在时才出现，避免死链 */ ?>
<?php if (sg_page_exists('about')) : ?>
<a class="sg-author-btn" href="<?php echo sg_e(sg_page_url('about')); ?>">
  <?php echo sg_icon('user'); ?>关于我
</a>
<?php endif; ?>

<div class="sg-profile-foot">
  <div class="sg-stats">
    <div class="sg-stat"><span class="sg-stat-num"><?php echo sg_e($sgCounts['posts']); ?></span><span class="sg-stat-label">文章</span></div>
    <span class="sg-stat-divider"></span>
    <div class="sg-stat"><span class="sg-stat-num is-purple"><?php echo sg_e($sgCounts['notes']); ?></span><span class="sg-stat-label">碎语</span></div>
    <span class="sg-stat-divider"></span>
    <div class="sg-stat"><span class="sg-stat-num is-pink"><?php echo sg_e($sgCounts['photos']); ?></span><span class="sg-stat-label">照片</span></div>
  </div>
  <?php if ($sgSocial) : ?>
  <div class="sg-socials">
    <?php foreach ($sgSocial as $item) : ?>
    <?php if (!is_array($item)) { continue; } ?>
    <a class="sg-social" href="<?php echo sg_value($item, 'url', '#'); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_value($item, 'name', '链接'); ?>" aria-label="<?php echo sg_value($item, 'name', '链接'); ?>">
      <?php echo sg_icon(sg_social_icon($item)); ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
