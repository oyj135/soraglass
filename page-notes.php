<?php
/**
 * 碎语 —— 短句、随想与日常片段的聚合流（合并原「杂谈」与「说说」两页）。
 *
 * 数据源：后台「碎语 JSON」→ dist/data/notes.json。
 * 刻意不按文章分类聚合：碎语是随手记，不该和文章抢同一个分类位。
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
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索碎语...',
];

$this->need('partials/notes-stream.php');
$this->need('footer.php');
