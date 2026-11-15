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

/**
 * 首屏字体预加载（第三十三轮）。
 *
 * 实测（docs/IMPLEMENTATION.md §36）：首页 293 个不同汉字触发 19 个分片 / 1314 KB，
 * 其中 094 / 095 / 096 三片就覆盖每页 66%–77% 的汉字，合计 188 KB。
 * 不 preload 的话，字体请求要等 207 KB 的 CSS 下载 + 解析完才发起 —— 首屏文字
 * 先是后备衬线渲染，再整页换装，视觉上是一次明显的闪动。
 *
 * ⚠️ `crossorigin` 必须写，哪怕同源：字体请求恒为 CORS 匿名模式，少了这个属性
 *    浏览器会当成「另一个资源」，preload 的响应无法被 @font-face 复用，等于把
 *    同一批分片下载两遍。
 * ⚠️ 文件名带 Vite hash，不能写死；这里用 glob 现查。dist 未上传或被清理时静默
 *    返回空串，绝不让字体拖垮页面。
 * ⚠️ 这三个编号是 `scripts/font-scan.mjs` 扫出来的。**重跑 `npm run fonts` 之后
 *    分片顺序可能变，必须重新扫一遍并更新下面的数组**，否则会 preload 错文件
 *    （不报错，只是白下 188 KB）。
 */
function sg_font_preload(): string
{
    static $html = null;
    if ($html !== null) {
        return $html;
    }
    $html = '';
    $dir = SG_ROOT . '/dist/assets';
    if (!is_dir($dir)) {
        return $html;
    }
    // 顺序即倾向：096 覆盖的字符最多，先让它排队
    foreach (['096', '095', '094'] as $id) {
        $hits = glob($dir . '/subset-' . $id . '-*.woff2');
        if (!$hits) {
            continue;
        }
        $html .= '<link rel="preload" href="' . sg_e(sg_theme_url('dist/assets/' . basename($hits[0])))
            . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
    return $html;
}

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
        'bgImages' => array_values(array_filter([sg_bg_url((string) sg_opt('sgBg', ''))])) ?: [sg_theme_url('dist/img/bg-1.svg')],
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
