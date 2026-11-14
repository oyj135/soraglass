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
/* 保持旧 JSON 字段，cover 可选；先归一化媒体地址，输出时统一转义。 */
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
        'cover' => sg_media($sgString($row, 'cover'), $avatar),
        'color' => $color,
    ];
}, $sgFriends);
$sgColumns = array_fill(0, min(3, count($sgFriends)), []);
foreach ($sgFriends as $sgIndex => $sgFriend) {
    $sgColumns[$sgIndex % count($sgColumns)][] = [$sgIndex, $sgFriend];
}
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="friends">
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgFriends) : $sgFirst = $sgFriends[0]; ?>
  <section class="sg-friends-board" data-sg-friends aria-label="友链海报墙">
    <div class="sg-friends-preview" data-sg-friend-preview>
      <img class="sg-friends-avatar" data-sg-friend-avatar src="<?php echo sg_e($sgFirst['avatar']); ?>" alt="" width="64" height="64">
      <h2 class="sg-friends-name" data-sg-friend-name><?php echo sg_e($sgFirst['name']); ?></h2>
      <p class="sg-friends-description" data-sg-friend-description><?php echo sg_e($sgFirst['description']); ?></p>
      <a class="sg-friends-visit" data-sg-friend-visit href="<?php echo sg_e($sgFirst['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sg_e('访问 ' . $sgFirst['name'] . '（新窗口）'); ?>">访问站点 <?php echo sg_icon('arrow-up-right'); ?></a>
    </div>
    <div class="sg-friend-wall" style="--sg-friend-columns:<?php echo count($sgColumns); ?>">
      <?php foreach ($sgColumns as $sgColumn) : ?>
      <div class="sg-friend-column">
        <?php foreach ($sgColumn as [$sgIndex, $sgFriend]) : ?>
        <div class="sg-friend-drift">
          <a class="sg-friend<?php echo $sgIndex === 0 ? ' is-selected' : ''; ?>" data-sg-friend
             data-name="<?php echo sg_e($sgFriend['name']); ?>" data-description="<?php echo sg_e($sgFriend['description']); ?>" data-avatar="<?php echo sg_e($sgFriend['avatar']); ?>"
             style="--sg-friend-color:<?php echo sg_e($sgFriend['color']); ?>"
             href="<?php echo sg_e($sgFriend['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sg_e('访问 ' . $sgFriend['name'] . '（新窗口）'); ?>">
            <img class="sg-friend-poster" src="<?php echo sg_e($sgFriend['cover']); ?>" alt="" loading="lazy" decoding="async">
            <span class="sg-friend-caption"><?php echo sg_e($sgFriend['name']); ?></span>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h2>还没有友链</h2>
    <p>有新的朋友时，会在这里相遇。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
