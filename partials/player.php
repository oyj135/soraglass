<?php
/**
 * 首页音乐卡（5 列）。
 *
 * 版式参考华为音乐播放页（2026-11-14 第三十八轮改）：纵向中轴居中 ——
 * 圆盘唱片当主角、衬线歌名压在轴心、细轨进度配圆点、底部一行「胶囊 + 三键 + 圆钮」。
 * 改之前是横向小卡（76px 唱片 + 左侧信息），在 5 列副栏里几乎读不出「正在播放」。
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
  <span class="sg-player-badge">CLOUD MUSIC</span>

  <div class="sg-player-disc">
    <img src="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>" alt="" data-sg-track-cover data-sg-fallback="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>">
  </div>

  <div class="sg-player-info">
    <a class="sg-player-title" href="<?php echo sg_e($sgMusicUrl); ?>" data-sg-track-title>未播放</a>
    <div class="sg-player-artist" data-sg-track-artist>空璃音乐盒</div>
  </div>

  <div class="sg-player-lyric" data-sg-lyric data-sg-typewriter>点击播放，开始今天的 BGM</div>

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
