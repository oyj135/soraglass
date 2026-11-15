<?php
/**
 * 首页音乐卡（5 列）。
 *
 * 用户确认的 A 款：方形专辑封面、右侧旋转 CD 和下方彩色音浪。
 * 与音乐页共享封面结构、传输控件和常驻音频，保持原首页位置。
 *
 * 功能挂点一个都没动（player.ts 全按 data-sg-* 查找）：
 *   data-sg-player / -track-title / -track-artist / -track-cover / -lyric /
 *   -progress / -time-current / -time-total / -play / -prev / -next
 * 新增两个与音乐页同款的挂点：data-sg-mode（随机/循环/单曲）与 data-sg-mute（静音）。
 * player.ts 里这两处原来只绑在 [data-sg-music] 上，本轮改成遍历所有播放器根节点。
 *
 * 整卡仍可点跳音乐页；按钮与进度条由 player.ts 拦冒泡。
 */
$sgMusicUrl = sg_page_url('music');
?>
<div class="sg-player" data-sg-player data-href="<?php echo sg_e($sgMusicUrl); ?>">
  <div class="sg-player-top"><i class="sg-player-state-dot" aria-hidden="true"></i><span data-sg-playback-status>等待播放</span></div>
  <?php $sgPlayerCover = ''; require __DIR__ . '/player-art.php'; ?>

  <div class="sg-player-info">
    <a class="sg-player-title" href="<?php echo sg_e($sgMusicUrl); ?>" data-sg-track-title>未播放</a>
    <div class="sg-player-artist" data-sg-track-artist>空璃音乐盒</div>
  </div>

  <div class="sg-player-lyric" data-sg-lyric data-sg-typewriter>点击播放，开始今天的 BGM</div>

  <canvas class="sg-player-wave" data-sg-wave aria-hidden="true"></canvas>

  <div class="sg-player-progress">
    <span data-sg-time-current>00:00</span>
    <input class="sg-progress" type="range" min="0" max="1000" value="0" data-sg-progress aria-label="播放进度">
    <span data-sg-time-total>00:00</span>
  </div>

  <div class="sg-player-controls">
    <button class="sg-player-mode" type="button" data-sg-mode data-mode="loop" aria-label="播放模式：列表循环">
      <span data-sg-mode-icon><?php echo sg_icon('repeat'); ?></span>
      <span data-sg-mode-label>列表循环</span>
    </button>

    <span class="sg-player-transport">
      <button type="button" data-sg-prev aria-label="上一首"><?php echo sg_icon('prev'); ?></button>
      <button type="button" class="sg-player-play" data-sg-play aria-label="播放/暂停" aria-pressed="false">
        <span class="sg-i-play"><?php echo sg_icon('play'); ?></span>
        <span class="sg-i-pause"><?php echo sg_icon('pause'); ?></span>
      </button>
      <button type="button" data-sg-next aria-label="下一首"><?php echo sg_icon('next'); ?></button>
    </span>

    <button type="button" class="sg-player-mute" data-sg-mute aria-label="静音"><?php echo sg_icon('volume'); ?></button>
  </div>
</div>
