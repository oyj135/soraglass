<?php
/**
 * 友链（2026-11-22 第六十七轮：正文可直接粘友链数组 JSON，数据源优先级见下）
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

/* 数据源优先级（sg_friends_for_page）：① 本页正文 JSON（正文不再当文章渲染）；
   ② 后台「友链 JSON」；③ 主题内置示例。正文不是 JSON 时当页面介绍渲染。 */
$sgRaw = sg_page_raw_text($this);
$sgFromBody = false;
$sgFriends = sg_friends_for_page($this, $sgRaw, $sgFromBody);
$sgFriendProse = ($sgRaw !== '' && !$sgFromBody) ? $sgRaw : '';
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

  <?php if ($sgFriendProse !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgFriends) : ?>
  <div class="sg-flink-list">
    <?php foreach ($sgFriends as $sgFriend) : ?>
    <?php /* 第四十二轮回到源站形态：整卡一个 <a>，内容是「16:10 截图 + 头像/站名/域名/简介」，
               简介常显两行，不再有抽屉与展开；悬停只有上浮 + 光晕 + 截图微放大。 */ ?>
    <a class="sg-flink-item" href="<?php echo sg_e($sgFriend['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e($sgFriend['name']); ?>" aria-label="<?php echo sg_e('访问 ' . $sgFriend['name'] . '（新窗口）'); ?>">
      <?php /* 截图失败（thum.io 超时 / 站点拒绝）时只隐藏图片，16:10 的占位层还在，卡片不会塌 */ ?>
      <span class="sg-flink-shot"><img src="<?php echo sg_e($sgFriend['siteshot']); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.style.display='none'"></span>
      <span class="sg-flink-info">
        <span class="sg-flink-icon"><img src="<?php echo sg_e($sgFriend['avatar']); ?>" alt="<?php echo sg_e($sgFriend['name']); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?php echo sg_e(sg_theme_url('dist/img/avatar.svg')); ?>'"></span>
        <span class="sg-flink-name"><?php echo sg_e($sgFriend['name']); ?></span>
        <?php if ($sgFriend['host'] !== '') : ?>
        <span class="sg-flink-host"><?php echo sg_e($sgFriend['host']); ?></span>
        <?php endif; ?>
        <span class="sg-flink-desc" title="<?php echo sg_e($sgFriend['description']); ?>"><?php echo sg_e($sgFriend['description']); ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h2>还没有友链</h2>
    <p>两种填法任选：① 编辑本页，正文直接粘贴 JSON（以 <code>[</code> 开头的友链数组，字段：name / url / description / avatar / siteshot）；② 后台 → 外观 → 主题设置 →「友链 JSON（兜底）」。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
