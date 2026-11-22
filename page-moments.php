<?php
/**
 * 碎语（旧「说说」页兼容）
 *
 * （内部兼容模板：刻意不带 @package custom，不出现在后台模板下拉；渲染旧地址时按 template 文件名解析，不受影响）
 *
 * 与 page-chatter.php 同理：「说说」已合并进「碎语」，
 * 旧地址继续渲染同一份碎语流，内容不会丢、也不会 404。
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$GLOBALS['SG_NOTES_HEAD'] = [
    'title'       => '碎语',
    'sub'         => '随手记 · 想到什么写什么',
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索碎语...',
];

$this->need('partials/notes-stream.php');
$this->need('footer.php');
