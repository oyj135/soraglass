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

/** 构建产物是否就绪（manifest 能解析出入口）。供后台自检与前台降级提示共用。 */
function sg_asset_ready(string $entry = 'src/scripts/main.ts'): bool
{
    $manifest = sg_manifest();
    return !empty($manifest[$entry]['file']);
}

/**
 * 构建产物缺失时的可见降级提示（仅管理员可见，P0-3）。
 * manifest 缺失时 sg_asset() 只吐 HTML 注释，页面静默裸奔无从发现；
 * 这里在 <body> 顶部挂一条醒目横幅，让误传源码目录的站长一眼看见。
 */
function sg_asset_missing_notice(): string
{
    if (sg_asset_ready() || !sg_user_is_admin()) {
        return '';
    }
    return '<div class="sg-build-alert" role="alert">'
        . 'Soraglass 构建产物缺失：请本地执行 <code>npm run release</code> 生成 <code>dist/</code>，'
        . '并把 <code>soraglass.zip</code>（内含 dist）上传到主题目录；仅上传源码目录会导致整页无样式。'
        . '</div>';
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
    // P2-10：这组值会由 media.ts 直接拼进 linear-gradient()，必须限定为纯 hex 颜色，
    // 否则管理员填入 `red), url(…)` 即可注入 CSS 声明 / 外发请求。非法值整条丢弃。
    return array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($v) {
        return preg_match('/^#[0-9a-f]{3,8}$/i', $v) === 1;
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
        // 首页与音乐页共用 Meting 歌单，避免两份来源不一致。
        'playlist' => sg_playlist(),
        // 图片救援必须使用内置资源，避免依赖同样可能失败的外链。
        'placeholder' => sg_theme_url('dist/img/ph-1.svg'),
        'nav' => [],
    ];
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return '<script type="application/json" data-sg-config>' . $json . '</script><script>window.SG=JSON.parse(document.querySelector("[data-sg-config]").textContent);</script>';
}
