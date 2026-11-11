<?php
/**
 * 照片墙
 *
 * @package custom
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
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '光影画廊 · 共 ' . $sgPhotoTotal . ' 张照片'),
    'search'      => true,
    'back'        => true,
    'center'      => true,
    'placeholder' => '搜索专辑或图注...',
];
?>
<main class="sg-main sg-main--wide sg-main--pad" data-sg-page="photowall">
  <?php $this->need('partials/page-head.php'); ?>

  <?php foreach ($sgAlbums as $sgAlbum) : ?>
  <?php if (!is_array($sgAlbum)) { continue; } ?>
  <section class="sg-album" data-sg-filter-item data-keywords="<?php echo sg_value($sgAlbum, 'title', '') . ' ' . sg_value($sgAlbum, 'description', ''); ?>">
    <div class="sg-album-head">
      <h2><?php echo sg_value($sgAlbum, 'title', '未命名专辑'); ?></h2>
      <span><?php echo sg_value($sgAlbum, 'date', ''); ?><?php if (sg_value($sgAlbum, 'description', '') !== '') : ?> · <?php echo sg_value($sgAlbum, 'description', ''); ?><?php endif; ?></span>
    </div>
    <div class="sg-photo-grid">
      <?php foreach ((array) ($sgAlbum['photos'] ?? []) as $sgPhoto) : ?>
      <?php if (!is_array($sgPhoto)) { continue; } ?>
      <a class="sg-photo" href="<?php echo sg_media(sg_value($sgPhoto, 'url', ''), sg_theme_url('dist/img/cover-default.svg')); ?>" data-sg-photo>
        <img src="<?php echo sg_media(sg_value($sgPhoto, 'url', ''), sg_theme_url('dist/img/cover-default.svg')); ?>" alt="<?php echo sg_value($sgPhoto, 'caption', ''); ?>" loading="lazy">
        <?php if (sg_value($sgPhoto, 'caption', '') !== '') : ?><span class="sg-photo-cap"><?php echo sg_value($sgPhoto, 'caption', ''); ?></span><?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if (!$sgAlbums) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有相册</h1>
    <p>到后台「设置外观 → 相册 JSON」添加，或直接把 Typecho 附件链接填进 photos。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
