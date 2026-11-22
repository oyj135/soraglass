<?php
/**
 * 碎语 —— 短句、随想与日常片段的聚合流（合并原「杂谈」与「说说」两页）。
 *
 * 数据源：**「碎语」分类下的文章**（分类缩略名固定为 `notes`，见 sg_notes()）。
 * 想发一条碎语就去后台写一篇短文章、勾上这个分类 —— 只有这一条维护路径，
 * 不再有「后台填 JSON」和「写文章」两套并存（2026-11-13 第二十轮按要求改的）。
 * 分类不存在时页面显示空态，不会报错。
 *
 * 正文结构见 partials/notes-stream.php；本文件只负责本页标题与副标题。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$GLOBALS['SG_NOTES_HEAD'] = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '短句与随想 · 想到什么写什么'),
    'center'      => true,
];

$this->need('partials/notes-stream.php');
$this->need('footer.php');
