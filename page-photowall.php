<?php
/**
 * 光影画廊
 * @package custom
 * Natural-ratio album masonry; ?album= preserves shareable detail URLs.
 */
if (!defined('SG_ROOT')) { require_once __DIR__ . '/functions.php'; }
$this->need('header.php');
$sgAlbums = sg_albums();
$sgTotal = array_sum(array_map(static fn($a) => count($a['photos']), $sgAlbums));
$sgOpenId = (string) ($_GET['album'] ?? '');
if (!in_array($sgOpenId, array_column($sgAlbums, 'id'), true)) { $sgOpenId = ''; }
$sgHead = ['title' => (string) $this->title, 'sub' => sg_page_sub($this, '在光里停留，在时间里收藏。'), 'search' => true, 'center' => false, 'placeholder' => '搜索相册、照片、日期或主题…', 'hidden' => $sgOpenId !== ''];
?>
<main id="sg-main" class="sg-main sg-main--wide sg-main--pad" data-sg-page="photowall" data-sg-gallery>
  <?php sg_page_head($sgHead); ?>
  <div class="sg-gallery-list" data-sg-gallery-list <?php if ($sgOpenId !== '') echo 'hidden'; ?>>
    <?php if ($sgAlbums): ?>
    <div class="sg-curated-heading"><span>光影收藏</span><span><?php echo count($sgAlbums); ?> 本相册 · <?php echo $sgTotal; ?> 个瞬间</span></div>
    <div class="sg-curated-grid<?php echo count($sgAlbums) === 1 ? ' sg-curated-grid--one' : (count($sgAlbums) === 2 ? ' sg-curated-grid--two' : ''); ?>">
      <?php foreach ($sgAlbums as $i => $album):
        $keywords = $album['title'] . ' ' . $album['description'] . ' ' . $album['date'];
        foreach ($album['photos'] as $photo) { $keywords .= ' ' . $photo['caption'] . ' ' . $photo['date']; }
        $featured = !empty($album['featured']);
        $coverWidth = $coverHeight = 0;
        foreach ($album['photos'] as $photo) {
          if ($photo['url'] === $album['cover'] && $photo['width'] > 0 && $photo['height'] > 0) {
            $coverWidth = $photo['width']; $coverHeight = $photo['height']; break;
          }
        }
      ?>
      <a class="sg-curated-album" href="?album=<?php echo sg_e($album['id']); ?>" data-sg-album="<?php echo sg_e($album['id']); ?>" data-keywords="<?php echo sg_e($keywords); ?>">
        <div class="sg-curated-image">
          <?php if ($album['coverThumb'] !== ''): ?><img src="<?php echo sg_e($album['coverThumb']); ?>" alt="<?php echo sg_e($album['title']); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" decoding="async" <?php if ($coverWidth && $coverHeight) echo 'width="' . $coverWidth . '" height="' . $coverHeight . '"'; ?>><?php else: ?><span class="sg-curated-no-preview">原文件收藏</span><?php endif; ?>
        </div>
        <div class="sg-curated-meta"><div><?php if ($featured): ?><span class="sg-curated-featured">精选相册</span><?php endif; ?><span class="sg-curated-date"><?php echo sg_e($album['date'] ?: '时间未标记'); ?></span><h2><?php echo sg_e($album['title']); ?></h2><?php if ($album['description'] !== ''): ?><p><?php echo sg_e($album['description']); ?></p><?php endif; ?></div><span class="sg-curated-count"><?php echo count($album['photos']); ?> 张 <span aria-hidden="true">↗</span></span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <div class="sg-gallery-search-results" data-sg-gallery-found hidden><h2>相关瞬间 <span data-sg-gallery-photos-count></span></h2><div class="sg-gallery-masonry" data-sg-gallery-photos data-sg-photo-group></div></div>
    <?php else: ?>
    <div class="sg-glass sg-notfound"><span class="sg-notfound-code">空</span><h2>收藏即将开始</h2><p><?php echo isset($GLOBALS['SG_GALLERY_ERROR']) ? sg_e($GLOBALS['SG_GALLERY_ERROR']) : '这里会留给值得记住的瞬间。'; ?></p></div>
    <?php endif; ?>
  </div>
  <p class="sg-filter-empty" data-sg-gallery-empty hidden>没有找到相关的收藏，试试另一个词。</p>
  <?php foreach ($sgAlbums as $album): ?>
  <section class="sg-gallery-detail" data-sg-gallery-detail="<?php echo sg_e($album['id']); ?>" <?php if ($sgOpenId !== $album['id']) echo 'hidden'; ?>>
    <div class="sg-gallery-detail-head"><div class="sg-gallery-detail-top"><a class="sg-back" href="?" data-sg-gallery-back><?php echo sg_icon('arrow-left'); ?>返回画廊</a><span class="sg-gallery-detail-dot" aria-hidden="true"></span><span class="sg-gallery-detail-date"><?php echo sg_e($album['date']); ?></span></div><div class="sg-gallery-detail-row"><div class="sg-gallery-detail-title"><h1><?php echo sg_e($album['title']); ?></h1><?php if ($album['description'] !== ''): ?><p><?php echo sg_e($album['description']); ?></p><?php endif; ?></div><span class="sg-gallery-detail-count">共 <b><?php echo count($album['photos']); ?></b> 瞬间</span></div></div>
    <div class="sg-gallery-masonry" data-sg-photo-group>
      <?php foreach ($album['photos'] as $photo): $cap = $photo['caption'] ?: $album['title']; ?>
      <a class="sg-photo<?php echo !$photo['preview'] ? ' sg-photo--file' : ''; ?>" href="<?php echo sg_e($photo['url']); ?>" <?php echo $photo['preview'] ? 'data-sg-photo' : 'target="_blank" rel="noopener noreferrer"'; ?> data-search-photo data-keywords="<?php echo sg_e($cap . ' ' . $photo['date'] . ' ' . $album['title'] . ' ' . $album['description']); ?>">
        <?php if ($photo['preview']): ?><img src="<?php echo sg_e($photo['thumb']); ?>" alt="<?php echo sg_e($cap); ?>" loading="lazy" decoding="async" <?php if ($photo['width'] && $photo['height']) echo 'width="' . $photo['width'] . '" height="' . $photo['height'] . '"'; ?>><?php else: ?><span class="sg-photo-file-label">打开原文件 ↗</span><?php endif; ?>
        <span class="sg-photo-caption"><b><?php echo sg_e($cap); ?></b><?php if ($photo['date']): ?><time><?php echo sg_e($photo['date']); ?></time><?php endif; ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>
</main>
<?php $this->need('footer.php'); ?>
