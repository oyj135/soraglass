<?php
/**
 * 碎语（旧「杂谈」页兼容）
 *
 * @package custom
 *
 * 「杂谈」已经合并进「碎语」。这个模板保留的唯一目的是：站长还没在后台
 * 建 notes 独立页、但站点里已经有 chatter 这个页面时，旧地址不会 404、
 * 也不会退化成空白页 —— 直接渲染合并后的碎语流。
 * 一旦后台建了 notes 页，导航与所有入口都会自动指向新页面，本文件可留可删。
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
