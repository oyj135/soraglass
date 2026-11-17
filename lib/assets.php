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
        $html .= '<link rel="stylesheet" data-sg-style href="' . sg_e(sg_theme_url('dist/' . $css)) . '">' . "\n";
    }
    if (!empty($item['file'])) {
        $html .= '<script type="module" src="' . sg_e(sg_theme_url('dist/' . $item['file'])) . '"></script>';
    }
    return $html;
}

/* 首屏字体 preload（第三十三轮）已移除（2026-11-17）：全局主字体换霞鹜文楷（lxgw-wenkai-webfont 按需分片），思源宋体退为回落层，preload 思源分片反而多下 188 KB 不在首屏用的字体；文楷分片无法预知，交给 font-display swap。 */

/**
 * 呼吸光晕的配色（壁纸之上那两个大光斑）。
 *
 * 2026-11-13 第三十四轮：签名色由 indigo 改青瓷，这组光晕必须跟着换，
 * 否则页面主色已经是青的、顶上两个光斑还是紫粉的，比不换更难看。
 *
 * 但 Typecho 的设置一旦保存过就落在 options 表里，改代码里的默认值**不会**覆盖它。
 * 所以这里额外判断一次：如果存下来的正是 indigo 时代那串默认值，就按「未设置」处理。
 * 只精确匹配那一串，用户自己配过的任何颜色都不会被动到。
 */
function sg_theme_colors(): array
{
    $default = '#5f9299,#8ec5c9,#b8d8dc,#dcebed';
    $legacy = '#a18cd1,#fbc2eb,#a1c4fd,#c2e9fb';   // indigo 时代的默认值
    $raw = trim((string) sg_opt('sgThemeColors', $default));
    if (strtolower($raw) === $legacy) {
        $raw = $default;
    }
    return array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($v) {
        return $v !== '';
    }));
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
        // 兼容旧配置；当前壁纸组由 header.php 的 data-bg-mode 输出。
        'bgImages' => sg_wallpapers()['light'], // 兼容旧字段；壁纸组实际由 header.php 的 data-bg-mode 输出,
        'themeColors' => sg_theme_colors(),
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
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return '<script type="application/json" data-sg-config>' . $json . '</script><script>window.SG=JSON.parse(document.querySelector("[data-sg-config]").textContent);</script>';
}
