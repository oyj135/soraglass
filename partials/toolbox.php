<?php
/** 左下悬浮工具箱：昼夜切换 / 音乐馆（「回到顶部」箭头已按需求下线） */
$sgMusicPage = null;
try {
    $this->widget('Widget_Contents_Page_List')->to($sgToolPages);
    while ($sgToolPages->next()) {
        if ((string) $sgToolPages->slug === 'music') {
            $sgMusicPage = (string) $sgToolPages->permalink;
            break;
        }
    }
} catch (\Throwable $e) {
    $sgMusicPage = null;
}
?>
<div class="sg-toolbox">
  <button class="sg-tool" type="button" data-sg-theme-toggle aria-label="切换昼夜模式">
    <?php echo sg_icon('sun', 'sg-i-sun'); ?>
    <?php echo sg_icon('moon', 'sg-i-moon'); ?>
  </button>
  <?php if ($sgMusicPage !== null) : ?>
  <a class="sg-tool" href="<?php echo sg_e($sgMusicPage); ?>" aria-label="音乐馆"><?php echo sg_icon('music'); ?></a>
  <?php endif; ?>
</div>
