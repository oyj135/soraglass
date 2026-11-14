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
/* 恢复头像/名称/简介卡；兼容旧JSON，输出时统一转义。 */
$sgString = static function (array $row, string $key, string $fallback = ''): string {
    return isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== ''
        ? (string) $row[$key] : $fallback;
};
$sgFriends = array_map(static function (array $row) use ($sgString): array {
    $avatar = sg_media($sgString($row, 'avatar'), sg_theme_url('dist/img/avatar.svg'));
    $color = $sgString($row, 'themeColor', '#35616b');
    /* 兼容 hex / rgb(a) / hsl(a)，颜色字段不允许注入其他 CSS 声明。 */
    if (!preg_match('/^(?:#[a-f0-9]{3,8}|(?:rgb|hsl)a?\([0-9.,%\s+\/-]+\))$/i', $color)) {
        $color = '#35616b';
    }
    return [
        'name' => $sgString($row, 'name', '友链'),
        'url' => $sgString($row, 'url', '#'),
        'description' => $sgString($row, 'description'),
        'avatar' => $avatar,
        'color' => $color,
    ];
}, $sgFriends);
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="friends">
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgFriends) : ?>
  <div class="sg-friend-grid">
    <?php foreach ($sgFriends as $sgFriend) : ?>
    <div class="sg-friend-cell">
      <a class="sg-glass sg-friend" href="<?php echo sg_e($sgFriend['url']); ?>" target="_blank" rel="noopener noreferrer" data-sg-hover aria-label="<?php echo sg_e('访问 ' . $sgFriend['name'] . '（新窗口）'); ?>">
        <span class="sg-friend-avatar"><img src="<?php echo sg_e($sgFriend['avatar']); ?>" alt="" width="60" height="60" loading="lazy" decoding="async"></span>
        <span class="sg-friend-main">
          <span class="sg-friend-head">
            <span class="sg-friend-name"><?php echo sg_e($sgFriend['name']); ?></span>
            <span class="sg-friend-status"><i style="background:<?php echo sg_e($sgFriend['color']); ?>"></i>ONLINE</span>
          </span>
          <span class="sg-friend-desc"><?php echo sg_e($sgFriend['description']); ?></span>
        </span>
      </a>
    </div>
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
