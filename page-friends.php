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
/* 友链卡（2026-11-19 第六轮，改按源站 volantis 形态：_config flink_style: volantis
   + custom.css 的覆盖）—— 16:10 站点截图在上，下面是圆头像 / 站名（1 行）/ 简介（2 行）。
   截图字段 siteShot 可选，未填时与源站同款走 thum.io 在线截屏（失败由 onerror 摘掉图区，
   卡片自动退化为纯文字卡）；兼容旧JSON，输出时统一转义。 */
$sgString = static function (array $row, string $key, string $fallback = ''): string {
    return isset($row[$key]) && is_scalar($row[$key]) && (string) $row[$key] !== ''
        ? (string) $row[$key] : $fallback;
};
$sgFriends = array_map(static function (array $row) use ($sgString): array {
    $sgUrl = $sgString($row, 'url', '#');
    $sgShot = sg_bg_url($sgString($row, 'siteshot'));
    /* 站名下面那行小字：源站 h3 里的 span（「2 Hours ago」），
       这里放站点域名 —— 静止时面板只有头像 + 站名会显得右半边空。 */
    $sgHost = (string) parse_url($sgUrl, PHP_URL_HOST);
    $sgHost = (string) preg_replace('/^www\./i', '', $sgHost);

    return [
        'name' => $sgString($row, 'name', '友链'),
        'url' => $sgUrl,
        'host' => $sgHost,
        'description' => $sgString($row, 'description'),
        'avatar' => sg_media($sgString($row, 'avatar'), sg_theme_url('dist/img/avatar.svg')),
        'siteshot' => $sgShot !== '' ? $sgShot : 'https://image.thum.io/get/allowJPG/wait/20/width/600/crop/950/' . $sgUrl,
    ];
}, $sgFriends);
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="friends">
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgFriends) : ?>
  <div class="sg-flink-list">
    <?php foreach ($sgFriends as $sgFriend) : ?>
    <?php /* 第四十一轮：展开改成「壳 + 浮层抽屉」。
               壳（.sg-flink-shell）就是整卡链接、也承担卡片全部外观与圆角裁剪；
               简介搬到卡外的 .sg-flink-drawer，绝对定位挂在卡片下沿，悬停才展开。
               这样卡片布局高度恒定（1:1 由 .sg-flink-item 撑住），
               网格行高不重算，页面下方内容不会被推走。 */ ?>
    <div class="sg-flink-item">
      <a class="sg-flink-shell" href="<?php echo sg_e($sgFriend['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e($sgFriend['name']); ?>" aria-label="<?php echo sg_e('访问 ' . $sgFriend['name'] . '（新窗口）'); ?>">
        <?php /* 截图失败（thum.io 超时 / 站点拒绝）时只隐藏图片，保留占位层 ——
                   弯曲卡的几何靠这层撑住，摘掉节点会让卡片塌成文字条 */ ?>
        <span class="sg-flink-shot"><img src="<?php echo sg_e($sgFriend['siteshot']); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.style.display='none'"></span>
        <span class="sg-flink-info">
          <span class="sg-flink-icon"><img src="<?php echo sg_e($sgFriend['avatar']); ?>" alt="<?php echo sg_e($sgFriend['name']); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?php echo sg_e(sg_theme_url('dist/img/avatar.svg')); ?>'"></span>
          <span class="sg-flink-name"><?php echo sg_e($sgFriend['name']); ?></span>
          <?php if ($sgFriend['host'] !== '') : ?>
          <span class="sg-flink-host"><?php echo sg_e($sgFriend['host']); ?></span>
          <?php endif; ?>
        </span>
      </a>
      <span class="sg-flink-drawer">
        <span class="sg-flink-desc" title="<?php echo sg_e($sgFriend['description']); ?>"><?php echo sg_e($sgFriend['description']); ?></span>
      </span>
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
