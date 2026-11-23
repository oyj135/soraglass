<?php
if (!defined('SG_ROOT')) {
    define('SG_ROOT', __DIR__);
}
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/http.php';
require_once __DIR__ . '/lib/sidebar-data.php';
require_once __DIR__ . '/lib/fields.php';
require_once __DIR__ . '/lib/season.php';
require_once __DIR__ . '/lib/playlist.php';
require_once __DIR__ . '/lib/gallery.php';
require_once __DIR__ . '/lib/stats.php';
require_once __DIR__ . '/lib/assets.php';
require_once __DIR__ . '/lib/github.php';
require_once __DIR__ . '/lib/ai.php';
require_once __DIR__ . '/lib/ai-daily.php';
require_once __DIR__ . '/lib/icons.php';
require_once __DIR__ . '/lib/admin.php';

function themeInit($archive): void
{
    sg_context($archive);

    if (!($archive instanceof \Widget\Archive)) {
        return;
    }

    sg_sidebar_dispatch();
    sg_sidebar_geo_dispatch();

    // 模板不输出分页条；0 表示沿用 Typecho 阅读设置的每页文章数。
    $size = (int) sg_opt('sgPageSize', '200');
    if ($size > 0 && ($archive->is('index') || $archive->is('archive'))) {
        $archive->parameter->pageSize = $size;
    }
}
