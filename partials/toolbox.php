<?php
/** 左下悬浮工具箱：主题 / 音乐 / 回到顶部 */
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
  <button class="sg-tool" type="button" data-sg-totop aria-label="回到顶部"><?php echo sg_icon('arrow-up'); ?></button>
</div>
