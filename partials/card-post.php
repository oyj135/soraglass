<?php
/**
 * 归档 / 分类 / 标签 / 搜索列表里的文章卡（参考图样式 2026-11-16）。
 * 结构：顶部大图（含「」引号摘要）+ 深色信息区（居中标题 / 日期 / 标签）。
 * 2026-11-17 起卡片标记统一由 sg_feature_card_html() 输出（与首页直查数据共用），
 * 本模板只负责从 Widget 上下文组装字段。
 */
$sgCardCtx = isset($sgCardArchive) && $sgCardArchive ? $sgCardArchive : $this;
$sgCardTitle = trim((string) $sgCardCtx->title);
/* 摘要一律走 sg_excerpt()：它内部用 ob 捕获 excerpt() 的输出并返回字符串。
   直接调 $archive->excerpt() 会在独立 widget（首页列表）里把原文 echo 到卡片 DOM 之外。 */
$sgCardDesc = trim(strip_tags(sg_excerpt($sgCardCtx, 88, '')));
// date 属性是 Typecho 的时间对象；(string) 转换取到时间戳
$sgCardStamp = 0;
try {
    $sgCardStamp = (int) (string) $sgCardCtx->date;
} catch (\Throwable $e) {
    $sgCardStamp = 0;
}
$sgCardAgo   = sg_time_ago($sgCardStamp);
$sgCardCate  = sg_first_category($sgCardCtx, '');
$sgCardTags  = '';
ob_start();
$sgCardCtx->tags(' · ', false, '');
$sgCardTags = trim((string) ob_get_clean());

echo sg_feature_card_html([
    'url'      => (string) $sgCardCtx->permalink(),
    'cover'    => sg_cover($sgCardCtx),
    'quote'    => $sgCardDesc,
    'title'    => $sgCardTitle,
    'dateText' => $sgCardAgo !== '' ? '发表于 ' . $sgCardAgo : '发表于 ' . $sgCardCtx->date('Y.m.d'),
    'cate'     => $sgCardCate,
    'tags'     => $sgCardTags,
]);
