<?php
/** 文章页侧栏作者名片（2026-11-18：与首页作者卡完全一致）——
 *  极光横幅 + 呼吸发光圆头像（链到关于页）+ 可展开状态徽章 + 马克笔名字/宽字距简介
 *  + 文章/标签/分类三格统计（分别链到归档/标签页/分类页）+ 全宽药丸「关于我」按钮
 *  + 底部社交图标行。复用首页 card-profile 的类名与样式。 */
$sgAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgCounts = sg_stats();
$sgName = sg_opt('sgAuthorName', (string) $this->options->title);
$sgBio = (string) $this->options->description;
$sgStatus = trim((string) sg_opt('sgAuthorStatus', ''));
$sgArchiveUrl = sg_page_url('archives');
$sgTagsUrl = sg_page_exists('label') ? sg_page_url('label') : $sgArchiveUrl;
$sgCatesUrl = sg_page_exists('categories') ? sg_page_url('categories') : $sgArchiveUrl;
$sgAvatarLink = sg_page_exists('about') ? sg_page_url('about') : rtrim((string) $this->options->siteUrl, '/') . '/';
$sgProfileSocial = sg_json('sgSocial', 'social', []);
?>
<section class="sg-glass sg-card-pad sg-profile sg-author-card">
  <div class="sg-profile-banner" aria-hidden="true"></div>
  <div class="sg-profile-top">
    <div class="sg-avatar-wrap">
      <a class="sg-avatar-ring" href="<?php echo sg_e($sgAvatarLink); ?>">
        <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgName); ?>">
      </a>
      <?php /* 头像右下角常驻青瓷装饰星（同首页 card-profile）；无状态为静态星，有状态 hover 展开 */ ?>
      <span class="sg-status-bubble<?php echo $sgStatus === '' ? ' sg-status-bubble--deco' : ''; ?>"<?php echo $sgStatus !== '' ? ' title="' . sg_e($sgStatus) . '"' : ' aria-hidden="true"'; ?>>
        <?php echo sg_icon('star'); ?>
        <?php if ($sgStatus !== '') : ?><span class="sg-status-text"><?php echo sg_e($sgStatus); ?></span><?php endif; ?>
      </span>
    </div>
    <p class="sg-profile-name"><?php echo sg_e($sgName); ?></p>
    <?php if ($sgBio !== '') : ?><p class="sg-profile-bio"><?php echo sg_e($sgBio); ?></p><?php endif; ?>
  </div>

  <div class="sg-stats">
    <a class="sg-stat" href="<?php echo sg_e($sgArchiveUrl); ?>">
      <span class="sg-stat-label">文章</span>
      <span class="sg-stat-num"><?php echo sg_e($sgCounts['posts']); ?></span>
    </a>
    <a class="sg-stat" href="<?php echo sg_e($sgTagsUrl); ?>">
      <span class="sg-stat-label">标签</span>
      <span class="sg-stat-num"><?php echo sg_e($sgCounts['tags']); ?></span>
    </a>
    <a class="sg-stat" href="<?php echo sg_e($sgCatesUrl); ?>">
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

  <?php if ($sgProfileSocial) : ?>
  <div class="sg-profile-social">
    <?php foreach ($sgProfileSocial as $sgProfileItem) : ?>
    <?php if (!is_array($sgProfileItem)) { continue; } ?>
    <a class="sg-social <?php echo sg_e(sg_social_brand_class($sgProfileItem)); ?>" href="<?php echo sg_e(sg_value($sgProfileItem, 'url', '#')); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e(sg_value($sgProfileItem, 'name', '链接')); ?>" aria-label="<?php echo sg_e(sg_value($sgProfileItem, 'name', '链接')); ?>">
      <?php echo sg_icon(sg_social_icon($sgProfileItem)); ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>