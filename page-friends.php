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
$sgFriends = array_values(array_filter(sg_json('sgFriends', 'friends', []), 'is_array'));
$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '那些散落在赛博宇宙中的有趣灵魂与神经节点。'),
    'search' => false,
    'center' => true,
];
/* 友链卡（2026-11-17 第二轮，对齐 Fomalhaut _flink_style/volantis.styl 的 .site-card）：
   顶部站点截图 + 下方圆头像 / 名称 / 两行简介。截图字段 siteShot 可选，
   未填时与源站同款走 thum.io 在线截屏；兼容旧JSON，输出时统一转义。 */
$sgString = static function (array $row, string $key, string $fallback = ''): string {
    return isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== ''
        ? (string) $row[$key] : $fallback;
};
$sgFriends = array_map(static function (array $row) use ($sgString): array {
    $sgUrl = $sgString($row, 'url', '#');
    $sgShot = sg_bg_url($sgString($row, 'siteshot'));
    return [
        'name' => $sgString($row, 'name', '友链'),
        'url' => $sgUrl,
        'description' => $sgString($row, 'description'),
        'avatar' => sg_media($sgString($row, 'avatar'), sg_theme_url('dist/img/avatar.svg')),
        'siteshot' => $sgShot !== '' ? $sgShot : 'https://image.thum.io/get/allowJPG/wait/20/width/600/crop/950/' . $sgUrl,
    ];
}, $sgFriends);
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="friends">
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgFriends) : ?>
  <div class="sg-site-card-group">
    <?php foreach ($sgFriends as $sgFriend) : ?>
    <a class="sg-site-card" href="<?php echo sg_e($sgFriend['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e($sgFriend['name']); ?>" aria-label="<?php echo sg_e('访问 ' . $sgFriend['name'] . '（新窗口）'); ?>">
      <span class="sg-site-card-img"><img src="<?php echo sg_e($sgFriend['siteshot']); ?>" alt="<?php echo sg_e($sgFriend['name']); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>'"></span>
      <span class="sg-site-card-info">
        <img class="sg-site-card-avatar" src="<?php echo sg_e($sgFriend['avatar']); ?>" alt="" width="44" height="44" loading="lazy" decoding="async">
        <span class="sg-site-card-text">
          <span class="sg-site-card-title"><?php echo sg_e($sgFriend['name']); ?></span>
          <span class="sg-site-card-desc"><?php echo sg_e($sgFriend['description']); ?></span>
        </span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h2>还没有友链</h2>
    <p>有新的朋友时，会在这里相遇。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
