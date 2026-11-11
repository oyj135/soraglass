<?php
/**
 * 音乐
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgPlaylist = sg_json('sgPlaylist', 'playlist', []);
$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '音乐馆 · 共 ' . count($sgPlaylist) . ' 首'),
    'search' => false,
    'back'   => true,
    'center' => true,
];
?>
<main class="sg-main sg-main--narrow" data-sg-page="music">
  <?php $this->need('partials/page-head.php'); ?>

  <section class="sg-glass sg-card-pad" style="margin-bottom:1.5rem">
    <?php $this->need('partials/player.php'); ?>
  </section>

  <section class="sg-glass sg-music">
    <h3 class="sg-side-title" style="padding:0 0 0 .5rem">歌单</h3>
    <?php foreach ($sgPlaylist as $sgIndex => $sgTrack) : ?>
    <?php if (!is_array($sgTrack)) { continue; } ?>
    <button class="sg-track" type="button" data-sg-track="<?php echo (int) $sgIndex; ?>">
      <span class="sg-track-index"><?php echo str_pad((string) ((int) $sgIndex + 1), 2, '0', STR_PAD_LEFT); ?></span>
      <span class="sg-track-main">
        <span class="sg-track-title"><?php echo sg_value($sgTrack, 'title', '未命名'); ?></span>
        <span class="sg-track-artist"><?php echo sg_value($sgTrack, 'artist', ''); ?></span>
      </span>
      <?php echo sg_icon('music'); ?>
    </button>
    <?php endforeach; ?>
    <p class="sg-page-sub" style="margin-top:1rem">歌单来自后台「歌单 JSON」或 dist/data/playlist.json。线上请填可直链的音频地址。</p>
  </section>
</main>
<?php $this->need('footer.php'); ?>
