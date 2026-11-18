<?php
/**
 * 首页作者卡（2026-11-18 改版，借 Fomalhaut card_author 的形态但保留 Soraglass 材质）：
 * 顶部极光横幅（纯 CSS 渐变，不引外部图）+ 压横幅的呼吸发光圆头像
 * + 右下角可展开状态徽章（sgAuthorStatus，留空不输出）+ 马克笔高亮名字 + 宽字距简介
 * + 上下细线 / 竖分隔的三格统计（文章→归档 / 标签→标签页 / 分类→分类页）
 * + 全宽药丸「关于我」按钮（hover 填主题色 + passing 图标动画）+ 底部社交图标行。
 * 统计值由 lib/stats.php 的 sg_stats() 自动查库。
 */
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
<div class="sg-profile-banner" aria-hidden="true"></div>
<div class="sg-profile-top">
  <div class="sg-avatar-wrap">
    <a class="sg-avatar-ring" href="<?php echo sg_e($sgAvatarLink); ?>">
      <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgName); ?>">
    </a>
    <?php if ($sgStatus !== '') : ?>
    <span class="sg-status-bubble" title="<?php echo sg_e($sgStatus); ?>">
      <?php echo sg_icon('message'); ?>
      <span class="sg-status-text"><?php echo sg_e($sgStatus); ?></span>
    </span>
    <?php endif; ?>
  </div>
  <h1 class="sg-profile-name"><?php echo sg_e($sgName); ?></h1>
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
  <a class="sg-social" href="<?php echo sg_e(sg_value($sgProfileItem, 'url', '#')); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e(sg_value($sgProfileItem, 'name', '链接')); ?>" aria-label="<?php echo sg_e(sg_value($sgProfileItem, 'name', '链接')); ?>">
    <?php echo sg_icon(sg_social_icon($sgProfileItem)); ?>
  </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>