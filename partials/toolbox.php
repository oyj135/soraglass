<?php
/** 左下悬浮工具箱：只保留音乐馆入口（昼夜/阅读模式已并入右侧按钮组 partials/rightside.php） */
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
if ($sgMusicPage === null) {
    return;
}
?>
<div class="sg-toolbox">
  <a class="sg-tool" href="<?php echo sg_e($sgMusicPage); ?>" aria-label="音乐馆"><?php echo sg_icon('music'); ?></a>
</div>