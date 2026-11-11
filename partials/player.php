<?php
/** 音乐播放器（首页 5 列卡）。全局 <audio> 在 footer.php，这里只做 UI。 */
?>
<div class="sg-player" data-sg-player>
  <div class="sg-player-top">
    <div class="sg-player-cover">
      <img data-sg-track-cover src="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>" alt="">
    </div>
    <div class="sg-player-info">
      <span class="sg-player-badge">CLOUD MUSIC</span>
      <div class="sg-player-title" data-sg-track-title>未播放</div>
      <div class="sg-player-artist" data-sg-track-artist>空璃音乐盒</div>
      <div class="sg-player-lyric" data-sg-lyric>点击播放，开始今天的 BGM</div>
    </div>
  </div>
  <div class="sg-player-times">
    <span data-sg-time-current>00:00</span>
    <span data-sg-time-total>00:00</span>
  </div>
  <input class="sg-progress" type="range" min="0" max="1000" value="0" data-sg-progress aria-label="播放进度">
  <div class="sg-player-controls">
    <button type="button" data-sg-prev aria-label="上一首"><?php echo sg_icon('prev'); ?></button>
    <button type="button" class="sg-player-play" data-sg-play aria-label="播放/暂停">
      <span data-sg-icon-play><?php echo sg_icon('play'); ?></span>
      <span data-sg-icon-pause><?php echo sg_icon('pause'); ?></span>
    </button>
    <button type="button" data-sg-next aria-label="下一首"><?php echo sg_icon('next'); ?></button>
  </div>
</div>
