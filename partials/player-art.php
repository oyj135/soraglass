<?php
/** Shared album sleeve and rotating CD; the caller supplies $sgPlayerCover. */
$sgPlayerFallback = sg_theme_url('dist/img/cover-default.svg');
$sgPlayerCover = sg_media((string) ($sgPlayerCover ?? ''), $sgPlayerFallback);
?>
<div class="sg-album-art" data-sg-album-art aria-hidden="true">
  <div class="sg-album-cd">
    <div class="sg-album-rotor">
      <span class="sg-album-hub"></span>
    </div>
  </div>
  <div class="sg-album-sleeve">
    <img src="<?php echo sg_e($sgPlayerCover); ?>" alt="" data-sg-track-cover data-sg-fallback="<?php echo sg_e($sgPlayerFallback); ?>">
  </div>
</div>
