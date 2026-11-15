<?php
/** Theme bridge; storage and administration belong to the independent plugin. */
function sg_gallery_plugin(): bool
{
    if (!class_exists('Typecho\\Plugin') || !isset(\Typecho\Plugin::export()['activated']['SoraglassGallery'])) { return false; }
    $file = \Widget\Options::alloc()->pluginDir('SoraglassGallery') . '/Plugin.php';
    if (!is_file($file)) { return false; }
    require_once $file;
    return true;
}

function sg_albums(): array
{
    static $albums = null;
    if ($albums !== null) { return $albums; }
    try { return $albums = sg_gallery_plugin() ? \TypechoPlugin\SoraglassGallery\Plugin::albums() : []; }
    catch (\Throwable $e) { $GLOBALS['SG_GALLERY_ERROR'] = '画廊数据暂时不可用，请在后台检查插件。'; return $albums = []; }
}

