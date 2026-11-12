<?php
/**
 * 首页音乐卡（5 列）。
 *
 * 结构对齐源站 components/CloudPlayer.tsx：唱片封面 + CLOUD MUSIC 徽标 + 曲名/歌手
 * + 逐字歌词行 + 两侧时间夹一条进度条 + 上一首/播放/下一首。
 * 全局 <audio> 在 footer.php，这里只做 UI；点击卡片空白处跳音乐页，
 * 点按钮/进度条不跳（player.ts 里拦冒泡，对应源站的 safeXxx 系列）。
 */
$sgMusicUrl = sg_page_url('music');
?>
<div class="sg-player" data-sg-player data-href="<?php echo sg_e($sgMusicUrl); ?>">
  <div class="sg-player-top">
    <div class="sg-player-disc">
      <img src="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>" alt="" data-sg-track-cover data-sg-fallback="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>">
      <span class="sg-player-hole" aria-hidden="true"></span>
    </div>
    <div class="sg-player-info">
      <span class="sg-player-badge">CLOUD MUSIC</span>
      <div class="sg-player-title" data-sg-track-title>未播放</div>
      <div class="sg-player-artist" data-sg-track-artist>空璃音乐盒</div>
    </div>
  </div>

  <div class="sg-player-lyric" data-sg-lyric data-sg-typewriter>点击播放，开始今天的 BGM</div>

  <div class="sg-player-progress">
    <span data-sg-time-current>00:00</span>
    <input class="sg-progress" type="range" min="0" max="1000" value="0" data-sg-progress aria-label="播放进度">
    <span data-sg-time-total>00:00</span>
  </div>

  <div class="sg-player-controls">
    <button type="button" data-sg-prev aria-label="上一首"><?php echo sg_icon('prev'); ?></button>
    <button type="button" class="sg-player-play" data-sg-play aria-label="播放/暂停" aria-pressed="false">
      <span class="sg-i-play"><?php echo sg_icon('play'); ?></span>
      <span class="sg-i-pause"><?php echo sg_icon('pause'); ?></span>
    </button>
    <button type="button" data-sg-next aria-label="下一首"><?php echo sg_icon('next'); ?></button>
  </div>
</div>
