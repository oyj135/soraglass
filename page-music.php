<?php
/**
 * 音乐
 *
 * @package custom
 *
 * 结构对齐源站 app/music/MusicClient.tsx：左侧唱片 + 曲名 + 进度 + 控制，
 * 右侧「歌词 / 歌单」分段面板（歌词滚动高亮、歌单搜索）。
 * 数据统一来自 sg_playlist()：后台只填写 Meting 歌单接口地址。
 * 常驻音频与首页共用，配置说明维护在使用手册。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgTracks = sg_playlist();
$sgFirst = $sgTracks[0] ?? [];
$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '在代码的缝隙里找一点共鸣'),
    'search' => false,
    'center' => true,
];
?>
<main id="sg-main" class="sg-main sg-main--wide" data-sg-page="music">
  <?php sg_page_head($sgHead); ?>

  <div class="sg-music-page">
    <!-- 左：唱片与传输控制 -->
    <section class="sg-glass sg-music-panel sg-music" data-sg-music>
      <div class="sg-player-top"><i class="sg-player-state-dot" aria-hidden="true"></i><span data-sg-playback-status>等待播放</span></div>
      <div class="sg-music-stage">
        <?php $sgPlayerCover = (string) ($sgFirst['cover'] ?? ''); require __DIR__ . '/partials/player-art.php'; ?>
      </div>

      <div class="sg-music-meta">
        <h2 data-sg-track-title><?php echo sg_value($sgFirst, 'title', '还没有歌'); ?></h2>
        <p data-sg-track-artist><?php echo sg_value($sgFirst, 'artist', '空璃音乐盒'); ?></p>
      </div>

      <canvas class="sg-player-wave" data-sg-wave aria-hidden="true"></canvas>

      <div class="sg-player-progress">
        <span data-sg-time-current>00:00</span>
        <input class="sg-progress" type="range" min="0" max="1000" value="0" data-sg-progress aria-label="播放进度">
        <span data-sg-time-total>00:00</span>
      </div>

      <div class="sg-music-controls">
        <button class="sg-music-mode" type="button" data-sg-mode data-mode="loop" aria-label="播放模式：列表循环">
          <span data-sg-mode-icon><?php echo sg_icon('repeat'); ?></span>
          <span data-sg-mode-label>列表循环</span>
        </button>

        <div class="sg-music-center">
          <button type="button" data-sg-prev aria-label="上一首"><?php echo sg_icon('prev'); ?></button>
          <button type="button" class="sg-music-play" data-sg-play aria-label="播放/暂停" aria-pressed="false">
            <span class="sg-i-play"><?php echo sg_icon('play'); ?></span>
            <span class="sg-i-pause"><?php echo sg_icon('pause'); ?></span>
          </button>
          <button type="button" data-sg-next aria-label="下一首"><?php echo sg_icon('next'); ?></button>
        </div>

        <div class="sg-music-sound">
          <button type="button" class="sg-music-mute" data-sg-mute aria-label="静音"><?php echo sg_icon('volume'); ?></button>
          <div class="sg-music-volume"><input id="sg-music-volume" class="sg-progress" type="range" min="0" max="100" value="80" data-sg-volume aria-label="音量"></div>
        </div>
      </div>
    </section>

    <!-- 右：歌词 / 歌单 -->
    <section class="sg-glass sg-music-side" data-sg-music-side>
      <div class="sg-music-tabs">
        <div class="sg-mtab-group" role="tablist" aria-label="歌词与歌单">
          <button id="sg-music-tab-lyrics" class="sg-mtab is-active" type="button" role="tab" aria-selected="true" aria-controls="sg-music-panel-lyrics" tabindex="0" data-sg-music-tab="lyrics">歌词</button>
          <button id="sg-music-tab-playlist" class="sg-mtab" type="button" role="tab" aria-selected="false" aria-controls="sg-music-panel-playlist" tabindex="-1" data-sg-music-tab="playlist">歌单</button>
        </div>
      </div>

      <div class="sg-music-body">
        <div id="sg-music-panel-lyrics" class="sg-music-lyrics" role="tabpanel" aria-labelledby="sg-music-tab-lyrics" tabindex="0" data-sg-lyrics data-sg-music-panel="lyrics">
          <div class="sg-music-empty" data-sg-lyrics-empty>
            <?php echo sg_icon('disc'); ?>
            <p>正在等待旋律…</p>
          </div>
        </div>

        <div id="sg-music-panel-playlist" class="sg-playlist" role="tabpanel" aria-labelledby="sg-music-tab-playlist" tabindex="0" data-sg-music-panel="playlist" hidden>
          <div class="sg-playlist-search">
            <?php echo sg_icon('search'); ?>
            <input type="search" placeholder="搜索音轨或歌手..." aria-label="搜索音轨" data-sg-playlist-search>
          </div>

          <div class="sg-playlist-list" data-sg-playlist>
            <p class="sg-music-empty" role="status" aria-live="polite" data-sg-playlist-empty hidden>没有找到匹配的音轨。</p>
            <?php foreach ($sgTracks as $sgIndex => $sgTrack) : ?>
            <?php if (!is_array($sgTrack)) { continue; } ?>
            <button class="sg-song" type="button" data-sg-track="<?php echo (int) $sgIndex; ?>">
              <span class="sg-song-cover">
                <img src="<?php echo sg_e(sg_media((string) ($sgTrack['cover'] ?? ''), sg_theme_url('dist/img/cover-default.svg'))); ?>" alt="" loading="lazy" data-sg-cover data-sg-fallback="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>">
              </span>
              <span class="sg-song-main">
                <span class="sg-song-title"><?php echo sg_value($sgTrack, 'title', '未命名'); ?></span>
                <span class="sg-song-artist"><?php echo sg_value($sgTrack, 'artist', '未知歌手'); ?></span>
              </span>
            </button>
            <?php endforeach; ?>
          </div>

          <?php if (!$sgTracks) : ?>
          <p class="sg-music-empty" style="padding:2rem 0"><?php echo sg_user_is_admin() ? '歌单暂不可用。请到「设置外观 → Meting 歌单接口地址」检查接口设置。' : '歌单暂未准备好，稍后再来听听吧。'; ?></p>
          <?php endif; ?>
        </div>
      </div>

    </section>
  </div>
</main>
<?php $this->need('footer.php'); ?>
