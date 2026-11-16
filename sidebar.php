<?php
/**
 * 文章页 / 独立页右侧栏补充卡片。
 *
 * 2026-11-16 定稿：参考站侧栏只保留「作者卡 / 公告 / 目录」三张。
 * 作者卡（partials/author-card.php）与目录（partials/toc.php）由模板单独输出，
 * 本文件只负责公告卡；后台 sgSidebar 设置项保留兼容，但 recent/categories/tags/webinfo
 * 已不再渲染（避免同一张卡在文章页出现两次，也对齐参考站的侧栏信息层级）。
 */
$sgCardKeys = array_values(array_unique(array_filter(array_map(
    'trim',
    explode(',', sg_opt('sgSidebar', 'notice'))
))));
/* 只认 notice；历史配置里残留的其它 key 在这里被过滤掉 */
$sgCardKeys = array_values(array_intersect($sgCardKeys, ['notice']));

if (!$sgCardKeys) {
    return;
}

$sgNotice = trim(sg_opt('sgNotice', ''));
if ($sgNotice === '') {
    return;
}
?>
<section class="sg-glass sg-side-card sg-side-notice">
  <h3 class="sg-side-title">公告</h3>
  <p><?php echo nl2br(sg_e($sgNotice)); ?></p>
</section>
