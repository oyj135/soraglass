<?php
/**
 * Vite 构建产物解析。
 * dist/ 必须随主题上传，线上（阿里云 ECS + 宝塔）不运行 npm。
 */
function sg_manifest(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $candidates = [
        SG_ROOT . '/dist/.vite/manifest.json',
        SG_ROOT . '/dist/manifest.json',
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $cache = $data;
                return $cache;
            }
        }
    }
    $cache = [];
    return $cache;
}

/** 输出入口的 <link> 与 <script>，自动处理带 hash 的文件名 */
function sg_asset(string $entry = 'src/scripts/main.ts'): string
{
    $manifest = sg_manifest();
    if (empty($manifest[$entry])) {
        return '<!-- Soraglass: 未找到 ' . sg_e($entry) . '，请本地执行 npm run build 并上传 dist/ -->';
    }
    $item = $manifest[$entry];
    $html = '';
    foreach ($item['css'] ?? [] as $css) {
        $html .= '<link rel="stylesheet" href="' . sg_e(sg_theme_url('dist/' . $css)) . '">' . "\n";
    }
    if (!empty($item['file'])) {
        $html .= '<script type="module" src="' . sg_e(sg_theme_url('dist/' . $item['file'])) . '"></script>';
    }
    return $html;
}

/** 供前端读取的运行时配置，输出为一小段内联 JSON */
function sg_runtime_config(): string
{
    $danmaku = sg_lines('sgDanmaku', []);
    if (!$danmaku) {
        $danmakuFile = SG_ROOT . '/dist/data/danmaku.json';
        if (is_file($danmakuFile)) {
            $decoded = json_decode((string) file_get_contents($danmakuFile), true);
            if (is_array($decoded)) {
                $danmaku = $decoded;
            }
        }
    }
    $config = [
        'themeUrl' => sg_theme_url(''),
        // 单张壁纸（第二十轮轮播下线）：前端已不再消费这个字段，保留只是为了不破坏旧缓存
        'bgImages' => array_values(array_filter([sg_bg_url((string) sg_opt('sgBg', ''))])) ?: [sg_theme_url('dist/img/bg-1.svg')],
        'themeColors' => array_values(array_filter(array_map('trim', explode(',', sg_opt('sgThemeColors', '#a18cd1,#fbc2eb,#a1c4fd,#c2e9fb'))))),
        'danmaku' => $danmaku,
        'effects' => sg_opt('sgEffects', 'on') === 'on',
        'clickEffect' => sg_opt('sgClickEffect', 'on') === 'on',
        'season' => sg_opt('sgSeason', 'none'),
        // 歌单三层回退（后台 JSON → 网易云 ID → dist/data）：和 page-music.php 共用同一个函数，
        // 否则首页卡与音乐页会拿到两份不一样的数据
        'playlist' => sg_playlist(),
        // 前端 <img> 外链失败时的兜底图（见 core.ts initImageFallback）。
        // 必须指向主题内置文件：不能再调 sg_placeholder() —— 那是 picsum 外链，
        // 外链挂掉时兜底图自己也加载不出来，等于没有兜底。
        'placeholder' => sg_theme_url('dist/img/ph-1.svg'),
        'nav' => [],
    ];
    return '<script>window.SG=' . json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';
}
