<?php
/**
 * 光影画廊（原「照片墙」）
 *
 * @package custom
 *
 * 结构与源站 app/photowall/PhotoWallClient.tsx 的意图一致，形态改成「视差相纸墙」：
 * 「光影画廊」标题 + 副标题 + 搜索 → 一条通栏的横向照片墙（相纸错落叠放、随滚动视差漂移）
 * → 月份胶囊点开专辑 → 详情页标题区（返回 / 日期 / 标题 / 简介 / 张数）+ CSS 多列瀑布流 → 灯箱。
 *
 * Typecho 没有二级路由，列表与详情都用同一份 albums 数据渲染，
 * 由 gallery.ts 通过 ?album=<id> + history.pushState 切换，后退键可回列表。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgAlbums = sg_albums();
$sgPhotoTotal = 0;
foreach ($sgAlbums as $sgCountAlbum) {
    if (is_array($sgCountAlbum) && is_array($sgCountAlbum['photos'] ?? null)) {
        $sgPhotoTotal += count($sgCountAlbum['photos']);
    }
}

$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '定格时间，封存泰拉与现实的每一次心跳 · 共 ' . $sgPhotoTotal . ' 张照片'),
    'search'      => true,
    'center'      => false,
    'placeholder' => '搜索相册名或照片描述...',
];

/**
 * 二级路由（?album=<id>）是「相册详情」，不是列表：页头那套
 * 「光影画廊 + 副标题 + 搜索」在详情里既重复又占掉首屏，整块不显示。
 *
 * 这里在服务端先判一次，让刷新 / 直接分享详情链接时不会先闪一下大标题
 * （客户端由 gallery.ts 在列表 ↔ 详情切换时同步 hidden）。
 * id 必须能在相册里找到，否则非法参数会把页头藏在列表页上。
 */
$sgOpenId = isset($_GET['album']) ? trim((string) $_GET['album']) : '';
if ($sgOpenId !== '') {
    $sgKnownIds = [];
    foreach ($sgAlbums as $sgIndexOf => $sgOne) {
        if (is_array($sgOne)) {
            $sgKnownIds[] = (string) ($sgOne['id'] ?? ('album-' . $sgIndexOf));
        }
    }
    if (!in_array($sgOpenId, $sgKnownIds, true)) {
        $sgOpenId = '';
    }
}
?>
<main id="sg-main" class="sg-main sg-main--wide sg-main--pad" data-sg-page="photowall" data-sg-gallery data-sg-grid>
  <?php sg_page_head($sgHead + ['hidden' => $sgOpenId !== '']); ?>

  <?php
  /**
   * 视差相册墙（第四十四轮）：一条通栏的横向照片墙，不带任何底色。
   *
   * 照片用原图、按「共同高度」缩放 —— 高度取带子的 22%–58%（张数越多越小），
   * 宽度随各自比例走，所以横竖混排都是完整的一张，不会被裁成缩略图。每张相纸
   * 带一个 --px-depth（纵深 0.35–1.25）：滚动时横向位移 ∝ 纵深，越「近」的照片
   * 走得越快，叠在慢的上面就是视差；此外还有极慢的自漂移，鼠标在带子里移动时
   * 再叠一层轻微跟手 —— 驱动见 modules/parallax.ts。
   *
   * 位置 / 尺寸 / 倾角 / 纵深全部由下标算出的确定性哈希决定（不用随机数），
   * 同一张照片每次刷新都在同一格，不会「跳一下」。
   */
  $sgHash = static function (int $i, float $salt): float {
      $v = sin(($i + 1) * 12.9898 + $salt) * 43758.5453;
      return $v - floor($v);
  };

  $sgFlats = [];
  foreach ($sgAlbums as $sgIndex => $sgAlbum) {
      if (!is_array($sgAlbum) || !is_array($sgAlbum['photos'] ?? null)) {
          continue;
      }
      $sgAlbumId    = (string) ($sgAlbum['id'] ?? ('album-' . $sgIndex));
      $sgAlbumTitle = sg_value($sgAlbum, 'title', '未命名相册');
      $sgAlbumDate  = sg_value($sgAlbum, 'date', '');
      foreach (array_values($sgAlbum['photos']) as $sgPhoto) {
          if (!is_array($sgPhoto)) {
              continue;
          }
          $sgRaw = trim((string) ($sgPhoto['url'] ?? ''));
          if ($sgRaw === '') {
              continue;
          }
          $sgFlats[] = [
              'album'   => $sgAlbumId,
              'name'    => $sgAlbumTitle,
              'date'    => $sgAlbumDate,
              'url'     => sg_media($sgRaw),
              'thumb'   => sg_media((string) ($sgPhoto['thumb'] ?? '')),
              'caption' => trim((string) ($sgPhoto['caption'] ?? '')),
          ];
      }
  }
  /* 一屏最多 12 张：再多就等距抽样，密度不炸、首尾都留得住 */
  $sgPrints = $sgFlats;
  if (count($sgPrints) > 12) {
      $sgSampled = [];
      $sgStep    = count($sgPrints) / 12;
      for ($sgI = 0; $sgI < 12; $sgI++) {
          $sgSampled[] = $sgPrints[(int) floor($sgI * $sgStep)];
      }
      $sgPrints = $sgSampled;
  }
  $sgPrintCount = count($sgPrints);
  /* 张数越多、单张越小：3 张时高度到带子的 46–58%，12 张收到 24–36% */
  $sgBaseH = round(max(30.0, min(54.0, 60.0 - $sgPrintCount * 2.6)), 2);
  ?>
  <div class="sg-gallery-list" data-sg-gallery-list>
    <?php if ($sgPrints) : ?>
    <section class="sg-px" data-sg-px aria-label="光影画廊 · 视差相册">
      <div class="sg-px-stage" data-sg-px-stage data-sg-photo-group>
        <?php foreach ($sgPrints as $sgK => $sgPrint) : ?>
        <?php
          $sgLabel = $sgPrint['caption'] !== '' ? $sgPrint['caption'] : $sgPrint['name'];
          /* 一律用原图：缩略图放大会糊，相纸里要塞整张照片 */
          $sgSrc = $sgPrint['url'];
          /* 均匀铺开 + 轻微抖动，避免一眼看出等距网格 */
          $sgLeft  = round(-6 + ($sgK + 0.5) * (112 / max(1, $sgPrintCount)) + ($sgHash($sgK, 1.7) - 0.5) * 2.6, 2);
          /* 中心线用黄金比铺开：3 张也不会两张挤在同一高度 */
          $sgTop   = round(36 + fmod($sgK * 0.6180339 + 0.13, 1) * 28, 2);
          /* 共同高度：照片看起来一样高，宽度随各自比例走，整张不裁切 */
          $sgHeight = round($sgBaseH + ($sgHash($sgK, 3.3) - 0.5) * 12, 2);
          $sgRot   = round(-3.2 + $sgHash($sgK, 5.9) * 6.4, 2);
          $sgDepth = round(0.35 + $sgHash($sgK, 7.7) * 0.9, 2);
          $sgZ     = 1 + (int) round($sgHash($sgK, 2.3) * 8);
        ?>
        <a class="sg-px-print"
           href="<?php echo sg_e($sgPrint['url']); ?>"
           data-sg-photo
           data-search-photo
           data-caption="<?php echo sg_e($sgPrint['caption']); ?>"
           data-album-name="<?php echo sg_e($sgPrint['name']); ?>"
           data-keywords="<?php echo sg_e($sgLabel . ' ' . $sgPrint['name'] . ' ' . $sgPrint['date']); ?>"
           style="--px-x:<?php echo $sgLeft; ?>%;--px-y:<?php echo $sgTop; ?>%;--px-h:<?php echo $sgHeight; ?>%;--px-rot:<?php echo $sgRot; ?>deg;--px-depth:<?php echo $sgDepth; ?>;--px-z:<?php echo $sgZ; ?>">
          <span class="sg-px-frame">
            <img src="<?php echo sg_e($sgSrc); ?>" alt="<?php echo sg_e($sgLabel); ?>" loading="lazy" referrerpolicy="no-referrer">
            <span class="sg-px-cap">
              <b><?php echo sg_e($sgLabel); ?></b>
              <span class="sg-px-date"><?php echo sg_e($sgPrint['date']); ?></span>
            </span>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <?php if ($sgAlbums) : ?>
    <nav class="sg-px-months" aria-label="按月份查看相册">
      <?php foreach ($sgAlbums as $sgIndex => $sgAlbum) : ?>
      <?php
        if (!is_array($sgAlbum)) {
            continue;
        }
        $sgId    = (string) ($sgAlbum['id'] ?? ('album-' . $sgIndex));
        $sgTitle = sg_value($sgAlbum, 'title', '未命名相册');
        $sgDate  = sg_value($sgAlbum, 'date', '');
        $sgCover = sg_value($sgAlbum, 'coverThumb', sg_value($sgAlbum, 'cover', ''));
        $sgNum   = is_array($sgAlbum['photos'] ?? null) ? count($sgAlbum['photos']) : 0;
      ?>
      <button class="sg-px-month" type="button" data-sg-album="<?php echo sg_e($sgId); ?>" data-sg-album-open="<?php echo sg_e($sgId); ?>" data-keywords="<?php echo sg_e($sgTitle . ' ' . $sgDate); ?>">
        <?php if ($sgCover !== '') : ?>
        <img src="<?php echo sg_e(sg_media($sgCover)); ?>" alt="" loading="lazy" referrerpolicy="no-referrer">
        <?php endif; ?>
        <span class="sg-px-month-text">
          <b><?php echo sg_e($sgTitle); ?></b>
          <span><?php echo sg_e($sgDate); ?><?php echo $sgDate !== '' ? ' · ' : ''; ?><?php echo (int) $sgNum; ?> 张</span>
        </span>
      </button>
      <?php endforeach; ?>
      <span class="sg-px-hint" aria-hidden="true">滚动 · 让照片浮起来</span>
    </nav>
    <?php endif; ?>
  </div>

  <p class="sg-filter-empty" data-sg-gallery-empty hidden>在泰拉大陆的任何角落都没找到相关的记忆，换个词试试。</p>

  <?php if (!$sgAlbums) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有相册</h1>
    <p>到「设置外观 → 主题设置 → 相册图片（OSS 链接）」里一行贴一张图片地址，会自动按年月分册。</p>
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
        $sgThumb = sg_media((string) ($sgPhoto['thumb'] ?? ''), $sgUrl);
        $sgCap = trim((string) ($sgPhoto['caption'] ?? ''));
      ?>
      <a class="sg-photo" href="<?php echo sg_e($sgUrl); ?>" data-sg-photo>
        <img src="<?php echo sg_e($sgThumb !== '' ? $sgThumb : $sgUrl); ?>" alt="<?php echo sg_e($sgCap !== '' ? $sgCap : $sgTitle . ' 第 ' . ((int) $sgPhotoIndex + 1) . ' 张'); ?>" loading="lazy" referrerpolicy="no-referrer">
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
