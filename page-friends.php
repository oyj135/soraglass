<?php
/**
 * 友链
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgFriends = sg_json('sgFriends', 'friends', []);
$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '那些散落在赛博宇宙中的有趣灵魂与神经节点。'),
    'search' => false,
    'back'   => true,
    'center' => true,
];
?>
<main class="sg-main sg-main--pad" data-sg-page="friends">
  <?php $this->need('partials/page-head.php'); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <div class="sg-card-grid">
    <?php foreach ($sgFriends as $sgFriend) : ?>
    <?php if (!is_array($sgFriend)) { continue; } ?>
    <a class="sg-glass sg-friend" href="<?php echo sg_value($sgFriend, 'url', '#'); ?>" target="_blank" rel="noopener noreferrer">
      <span class="sg-friend-avatar">
        <img src="<?php echo sg_media(sg_value($sgFriend, 'avatar', ''), sg_theme_url('dist/img/avatar.svg')); ?>" alt="" loading="lazy">
      </span>
      <span class="sg-friend-main">
        <span class="sg-friend-head">
          <span class="sg-friend-name"><?php echo sg_value($sgFriend, 'name', ''); ?></span>
          <span class="sg-friend-status"><i style="background:<?php echo sg_value($sgFriend, 'themeColor', '#6366f1'); ?>"></i>ONLINE</span>
        </span>
        <span class="sg-friend-desc"><?php echo sg_value($sgFriend, 'description', ''); ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$sgFriends) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有友链</h1>
    <p>到后台「设置外观 → 友链 JSON」添加，字段：name / url / avatar / description。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
