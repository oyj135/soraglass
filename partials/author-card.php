<?php
/** 文章页侧栏作者名片 */
$sgAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgSocial = sg_json('sgSocial', 'social', []);
$sgName = sg_opt('sgAuthorName', (string) $this->options->title);
$sgBio = (string) $this->options->description;
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<section class="sg-glass sg-author-card">
  <a class="sg-author-avatar" href="<?php echo sg_e($sgHome); ?>">
    <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgName); ?>">
  </a>
  <h3 class="sg-author-name"><?php echo sg_e($sgName); ?></h3>
  <?php if ($sgBio !== '') : ?><p class="sg-author-bio"><?php echo sg_e($sgBio); ?></p><?php endif; ?>
  <?php if ($sgSocial) : ?>
  <div class="sg-socials">
    <?php foreach ($sgSocial as $item) : ?>
    <?php if (!is_array($item)) { continue; } ?>
    <a class="sg-social" href="<?php echo sg_value($item, 'url', '#'); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_value($item, 'name', '链接'); ?>">
      <?php echo sg_icon(sg_social_icon($item)); ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
