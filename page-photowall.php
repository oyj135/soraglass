<?php
/**
 * 光影画廊（原「照片墙」）
 *
 * @package custom
 *
 * 结构与源站 app/photowall/PhotoWallClient.tsx 一致：
 * 「光影画廊」标题 + 副标题 + 搜索 →（搜索时）匹配的单张照片 + 相关相册 → 三层层叠拍立得
 * → 点开专辑 → 详情页标题区（返回 / 日期 / 标题 / 简介 / 张数）+ CSS 多列瀑布流 → 灯箱。
 *
 * Typecho 没有二级路由，列表与详情都用同一份 albums 数据渲染，
 * 由 gallery.ts 通过 ?album=<id> + history.pushState 切换，后退键可回列表。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgAlbums = sg_json('sgAlbums', 'albums', []);
$sgPhotoTotal = 0;
foreach ($sgAlbums as $sgCountAlbum) {
    if (is_array($sgCountAlbum) && is_array($sgCountAlbum['photos'] ?? null)) {
        $sgPhotoTotal += count($sgCountAlbum['photos']);
    }
}

/**
 * 取相册里第 N 张图，越界时回落到封面、再回落到占位图。
 * 拍立得三层需要三张，缺图时不能让中间层空着。
 */
$sgPick = static function (array $album, int $index, string $fallback): string
{
    $photos = is_array($album['photos'] ?? null) ? array_values($album['photos']) : [];
    if (isset($photos[$index]) && is_array($photos[$index])) {
        $url = trim((string) ($photos[$index]['url'] ?? ''));
        if ($url !== '') {
            return sg_media($url, $fallback);
        }
    }
    $cover = trim((string) ($album['cover'] ?? ''));
    return $cover !== '' ? sg_media($cover, $fallback) : $fallback;
};

$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '定格时间，封存泰拉与现实的每一次心跳 · 共 ' . $sgPhotoTotal . ' 张照片'),
    'search'      => true,
    'center'      => false,
    'placeholder' => '搜索相册名或照片描述...',
];
?>
<main class="sg-main sg-main--wide sg-main--pad" data-sg-page="photowall" data-sg-gallery data-sg-grid>
  <?php sg_page_head($sgHead); ?>

  <!-- 搜索结果区：默认隐藏，输入关键词后由 gallery.ts 打开 -->
  <section class="sg-gallery-found" data-sg-gallery-found hidden>
    <h3 class="sg-gallery-group" data-sg-gallery-photos-head hidden>
      <i class="is-indigo" aria-hidden="true"></i>匹配的单张照片 (<b data-sg-gallery-photos-count>0</b>)
    </h3>
    <div class="sg-gallery-masonry sg-gallery-found-photos" data-sg-gallery-photos>
      <?php foreach ($sgAlbums as $sgIndex => $sgAlbum) : ?>
      <?php
        if (!is_array($sgAlbum) || !is_array($sgAlbum['photos'] ?? null)) {
            continue;
        }
        $sgAlbumTitle = sg_value($sgAlbum, 'title', '未命名专辑');
        $sgAlbumId = (string) ($sgAlbum['id'] ?? ('album-' . $sgIndex));
        foreach (array_values($sgAlbum['photos']) as $sgPhoto) {
            if (!is_array($sgPhoto)) {
                continue;
            }
            $sgUrl = trim((string) ($sgPhoto['url'] ?? ''));
            if ($sgUrl === '') {
                continue;
            }
            $sgCap = trim((string) ($sgPhoto['caption'] ?? ''));
      ?>
      <a class="sg-photo" href="<?php echo sg_e(sg_media($sgUrl)); ?>"
         data-sg-photo
         data-search-photo
         data-caption="<?php echo sg_e($sgCap); ?>"
         data-album-name="<?php echo sg_e($sgAlbumTitle); ?>">
        <img src="<?php echo sg_e(sg_media($sgUrl)); ?>" alt="<?php echo sg_e($sgCap !== '' ? $sgCap : $sgAlbumTitle); ?>" loading="lazy">
        <?php if ($sgCap !== '') : ?><span class="sg-photo-cap"><?php echo sg_e($sgCap); ?></span><?php endif; ?>
      </a>
      <?php } ?>
      <?php endforeach; ?>
    </div>
  </section>

  <h3 class="sg-gallery-group" data-sg-gallery-albums-head hidden>
    <i class="is-purple" aria-hidden="true"></i>相关相册 (<b data-sg-gallery-albums-count>0</b>)
  </h3>

  <div class="sg-gallery" data-sg-gallery-list>
    <?php foreach ($sgAlbums as $sgIndex => $sgAlbum) : ?>
    <?php
      if (!is_array($sgAlbum)) {
          continue;
      }
      $sgId     = (string) ($sgAlbum['id'] ?? ('album-' . $sgIndex));
      $sgTitle  = sg_value($sgAlbum, 'title', '未命名专辑');
      $sgDesc   = sg_value($sgAlbum, 'description', '');
      $sgDate   = sg_value($sgAlbum, 'date', '');
      $sgPhotos = is_array($sgAlbum['photos'] ?? null) ? array_values($sgAlbum['photos']) : [];
      $sgCover  = sg_value($sgAlbum, 'cover', '');

      // 占位图 id 固定（1015 + 序号），同一相册每次刷新都是同一张，不会闪
      $sgFallback = sg_placeholder(1015 + (int) $sgIndex, 800, 600);
      $sgLayerBack  = $sgPick($sgAlbum, 2, $sgFallback);
      $sgLayerMid   = $sgPick($sgAlbum, 1, $sgFallback);
      $sgLayerFront = $sgCover !== '' ? sg_media($sgCover, $sgFallback) : $sgPick($sgAlbum, 0, $sgFallback);

      // 搜索命中：专辑名 + 描述 + 全部图注
      $sgCaptions = [];
      foreach ($sgPhotos as $sgOne) {
          if (is_array($sgOne) && trim((string) ($sgOne['caption'] ?? '')) !== '') {
              $sgCaptions[] = (string) $sgOne['caption'];
          }
      }
      $sgKeywords = $sgTitle . ' ' . $sgDesc . ' ' . implode(' ', $sgCaptions);
    ?>
    <article class="sg-glass sg-album-card" data-sg-album="<?php echo sg_e($sgId); ?>" data-keywords="<?php echo sg_e($sgKeywords); ?>" data-sg-spotlight>
      <button class="sg-album-open" type="button" data-sg-album-open="<?php echo sg_e($sgId); ?>" aria-label="打开专辑：<?php echo sg_e($sgTitle); ?>">
        <span class="sg-polaroid-stack">
          <img class="sg-polaroid" src="<?php echo sg_e($sgLayerBack); ?>" alt="" loading="lazy">
          <img class="sg-polaroid" src="<?php echo sg_e($sgLayerMid); ?>" alt="" loading="lazy">
          <img class="sg-polaroid" src="<?php echo sg_e($sgLayerFront); ?>" alt="<?php echo sg_e($sgTitle); ?>" loading="lazy">
        </span>
      </button>

      <div class="sg-album-meta">
        <h2><?php echo sg_e($sgTitle); ?></h2>
        <span><?php echo sg_e($sgDate); ?><?php echo $sgDate !== '' ? ' · ' : ''; ?><?php echo count($sgPhotos); ?> 张照片</span>
        <?php if ($sgDesc !== '') : ?><p><?php echo sg_e($sgDesc); ?></p><?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <p class="sg-filter-empty" data-sg-gallery-empty hidden>在泰拉大陆的任何角落都没找到相关的记忆，换个词试试。</p>

  <?php if (!$sgAlbums) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有相册</h1>
    <p>到后台「设置外观 → 相册 JSON」添加，或直接把 Typecho 附件链接填进 photos。</p>
  </div>
  <?php endif; ?>

  <?php foreach ($sgAlbums as $sgIndex => $sgAlbum) : ?>
  <?php
    if (!is_array($sgAlbum)) {
        continue;
    }
    $sgId     = (string) ($sgAlbum['id'] ?? ('album-' . $sgIndex));
    $sgTitle  = sg_value($sgAlbum, 'title', '未命名专辑');
    $sgDesc   = sg_value($sgAlbum, 'description', '');
    $sgDate   = sg_value($sgAlbum, 'date', '');
    $sgPhotos = is_array($sgAlbum['photos'] ?? null) ? array_values($sgAlbum['photos']) : [];
  ?>
  <section class="sg-gallery-detail" data-sg-gallery-detail="<?php echo sg_e($sgId); ?>" hidden>
    <div class="sg-gallery-detail-head">
      <div class="sg-gallery-detail-top">
        <button class="sg-back" type="button" data-sg-gallery-back><?php echo sg_icon('arrow-left'); ?>返回画廊</button>
        <span class="sg-gallery-detail-dot" aria-hidden="true"></span>
        <span class="sg-gallery-detail-date"><?php echo sg_e($sgDate); ?></span>
      </div>
      <div class="sg-gallery-detail-row">
        <div class="sg-gallery-detail-title">
          <h1><?php echo sg_e($sgTitle); ?></h1>
          <?php if ($sgDesc !== '') : ?><p><?php echo sg_e($sgDesc); ?></p><?php endif; ?>
        </div>
        <span class="sg-gallery-detail-count">共 <b><?php echo count($sgPhotos); ?></b> 瞬间</span>
      </div>
    </div>

    <?php if ($sgPhotos) : ?>
    <div class="sg-gallery-masonry" data-sg-photo-group>
      <?php foreach ($sgPhotos as $sgPhotoIndex => $sgPhoto) : ?>
      <?php
        if (!is_array($sgPhoto)) {
            continue;
        }
        $sgUrl = trim((string) ($sgPhoto['url'] ?? ''));
        $sgUrl = $sgUrl !== '' ? sg_media($sgUrl, sg_placeholder(1015 + (int) $sgIndex, 800, 600)) : sg_placeholder(1015 + (int) $sgIndex, 800, 600);
        $sgCap = trim((string) ($sgPhoto['caption'] ?? ''));
      ?>
      <a class="sg-photo" href="<?php echo sg_e($sgUrl); ?>" data-sg-photo>
        <img src="<?php echo sg_e($sgUrl); ?>" alt="<?php echo sg_e($sgCap !== '' ? $sgCap : $sgTitle . ' 第 ' . ((int) $sgPhotoIndex + 1) . ' 张'); ?>" loading="lazy">
        <?php if ($sgCap !== '') : ?><span class="sg-photo-cap"><?php echo sg_e($sgCap); ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else : ?>
    <p class="sg-filter-empty">这个相册还没有照片，往 photos 里加几条就会出现在这里。</p>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>
</main>
<?php $this->need('footer.php'); ?>
