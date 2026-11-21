<?php
/**
 * 首页歌词栏（7:5 行与 4:8 行之间）。
 *
 * 对应源站 components/LyricBar.tsx：左侧五根声波、中间逐字歌词 + 闪烁光标、
 * 右侧音符图标。深色通栏条两种主题共用（源站写的是 bg-slate-900/80，
 * 暗色再压到 slate-950/90），所以颜色走 --sg-lyric-* 两个 token，不跟昼夜卡面变。
 *
 * 文案由 player.ts 写入：它同时喂音乐卡里的 [data-sg-lyric] 和这里的
 * [data-sg-lyric-text]；播放/暂停只切根节点上的 .is-playing（声波与音符的动画开关）。
 */
?>
<div class="sg-lyric-bar" data-sg-lyric-bar>
  <span class="sg-lyric-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
  <p class="sg-lyric-text" data-sg-lyric-text>点击播放，开始今天的 BGM</p>
  <span class="sg-lyric-icon" aria-hidden="true"><?php echo sg_icon_color('music'); ?></span>
</div>
