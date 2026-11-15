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

function sg_gallery_catalog(): array
{
    static $data = null;
    if ($data !== null) { return $data; }
    try { return $data = sg_gallery_plugin() ? (new \TypechoPlugin\SoraglassGallery\Repository())->catalog()->albums() : ['albums' => [], 'total' => 0, 'photoTotal' => 0, 'page' => 1, 'pages' => 1]; }
    catch (\Throwable $e) { $GLOBALS['SG_GALLERY_ERROR'] = '画廊数据暂时不可用，请在后台检查插件。'; return ['albums' => [], 'total' => 0, 'photoTotal' => 0, 'page' => 1, 'pages' => 1]; }
}

function sg_gallery_album(string $id): ?array
{
    if (!sg_gallery_plugin()) { return null; }
    return (new \TypechoPlugin\SoraglassGallery\Repository())->catalog()->albums(1, '', $id)['albums'][0] ?? null;
}

function sg_gallery_photos(string $id): array
{
    return sg_gallery_plugin() ? (new \TypechoPlugin\SoraglassGallery\Repository())->catalog()->photos($id) : ['photos' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
}

