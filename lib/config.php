<?php
/**
 * Soraglass 配置读取层
 * Typecho 1.3 主题设置以 JSON 存于 options 表的 theme:<目录名> 行，
 * 模板中通过 $this->options->字段名 读取；本层统一做空值回退。
 */
if (!defined('SG_ROOT')) {
    define('SG_ROOT', dirname(__DIR__));
}

function sg_context($archive): void
{
    $GLOBALS['SG_CONTEXT'] = $archive;
}

function sg_options()
{
    // 首选：模板/themeInit 注入的归档上下文
    $context = $GLOBALS['SG_CONTEXT'] ?? null;
    if (is_object($context)) {
        try {
            $options = $context->options;
            if (is_object($options)) {
                return $options;
            }
        } catch (\Throwable $e) {
            // 落到下面的全局 Widget 回退
        }
    }

    // 回退：Typecho 1.3 的全局 Options Widget（namespace Widget\Options，继承 Typecho\Widget）
    // 不能只依赖 $GLOBALS['SG_CONTEXT']：某些上下文里 ->options 取不到，会导致所有 sg_opt() 静默用默认值
    try {
        if (class_exists('\\Widget\\Options')) {
            $options = \Widget\Options::alloc();
            if (is_object($options)) {
                return $options;
            }
        }
    } catch (\Throwable $e) {
        // 调用方会用默认值与 dist/data/*.json 兜底
    }

    return null;
}

function sg_opt(string $key, string $default = ''): string
{
    $options = sg_options();
    if (!$options) {
        return $default;
    }
    $value = $options->{$key};
    return (is_string($value) && $value !== '') ? $value : $default;
}

/**
 * 占位图地址。
 *
 * 2026-11-13 第十六轮：**改成主题内置图**，不再引用 https://picsum.photos。
 * 第七轮曾按用户要求换回 picsum 外链，但实测国内网络访问 picsum.photos
 * 直接超时（IPv4 连不上、IPv6 直接失败），于是所有封面都回退成本地渐变图，
 * 首页「樱色纪行」海报与「AI 日报」横幅的背景图整体失效。
 *
 * 现在返回 `dist/img/ph-1..6.svg`（$id 取模选图），由 sg_theme_url() 拼成
 * 绝对地址，任何网络环境下都能显示，也避免了一次跨域请求。$width/$height
 * 只为兼容旧调用点保留，不再参与拼地址。
 */
function sg_placeholder(int $id = 1015, int $width = 1200, int $height = 600): string
{
    $index = (($id % 6) + 6) % 6 + 1;
    return sg_theme_url('dist/img/ph-' . $index . '.svg');
}

/** 按行读取多行配置（壁纸、弹幕等），自动过滤空行 */
function sg_lines(string $key, array $default = []): array
{
    $raw = sg_opt($key, '');
    if ($raw === '') {
        return $default;
    }
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    return count($lines) ? $lines : $default;
}

/** 后台 JSON 文本域优先，回退到 dist/data/<name>.json */
function sg_json(string $key, string $name, array $default = []): array
{
    $raw = sg_opt($key, '');
    if ($raw !== '') {
        $data = json_decode($raw, true);
        if (is_array($data)) {
            return $data;
        }
    }
    $file = SG_ROOT . '/dist/data/' . $name . '.json';
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return $default;
}

/**
 * 外网数据的缓存文件路径（歌单 / GitHub 仓库 / AI 日报共用）。
 *
 * 优先 `/tmp/soraglass-cache/`；宝塔的 open_basedir 一般放行 /tmp，
 * 但少数环境把它锁死在站点目录里 —— 那时退回 `dist/data/cache/`，
 * 否则 `@file_put_contents` 静默失败，缓存永远建不起来，
 * 每次访问都直连外网、每次都要等到超时，页面慢得莫名其妙。
 *
 * 返回空串表示两处都不可写：调用方按「不缓存」处理，功能不受影响。
 */
function sg_cache_file(string $name): string
{
    static $dir = null;

    if ($dir === null) {
        $dir = '';
        $candidates = [
            rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'soraglass-cache',
            SG_ROOT . '/dist/data/cache',
        ];
        foreach ($candidates as $candidate) {
            if (!is_dir($candidate)) {
                @mkdir($candidate, 0755, true);
            }
            if (is_dir($candidate) && is_writable($candidate)) {
                $dir = $candidate;
                break;
            }
        }
    }

    return $dir !== '' ? $dir . DIRECTORY_SEPARATOR . $name : '';
}

/**
 * 主题 URL。
 *
 * ⚠️ 不要用 `$options->themeUrl` 属性当唯一来源：Typecho 1.3 里它走 ___themeUrl() 懒加载，
 *    在部分上下文（含宝塔 + PHP 8.2 的模板 include 场景）会取到空值；
 *    旧实现随后退化成站点根 `/`，把 /usr/themes/soraglass/dist/... 拼成 /dist/... 导致全站无样式。
 *
 * 正确用法：`themeUrl($path, $theme)` 传第二参数时「返回」字符串；
 *          只传 $path 时方法会 echo 并返回 void，不能用于字符串拼接。
 * 最终兜底用主题根目录名拼相对路径，完全不依赖 Options，保证任何情况都能指向 dist/。
 */
function sg_theme_url(string $path = ''): string
{
    $theme = basename(SG_ROOT);              // Typecho 按目录名识别主题，恒可靠
    $dir = defined('__TYPECHO_THEME_DIR__')
        ? trim((string) __TYPECHO_THEME_DIR__, '/')
        : 'usr/themes';
    $fallback = '/' . $dir . '/' . $theme . '/' . ltrim($path, '/');

    $options = sg_options();
    if (!$options) {
        return $fallback;
    }

    // 只有 URL 的 path 里确实带主题目录名才采用，避免拿到 `/` 之类的空壳值后
    // 又把资源拼到站点根（这正是本次全站无样式的原因）
    $valid = static function ($url) use ($theme): bool {
        if (!is_string($url) || $url === '' || $url === '/') {
            return false;
        }
        $urlPath = parse_url($url, PHP_URL_PATH);
        return is_string($urlPath) && str_contains($urlPath, '/' . $theme . '/');
    };

    try {
        $url = $options->themeUrl($path, $theme);
        if ($valid($url)) {
            return $url;
        }
    } catch (\Throwable $e) {
        // 落到次级方案
    }

    try {
        $base = $options->themeUrl;
        if ($valid($base)) {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }
    } catch (\Throwable $e) {
        // 落到最终兜底
    }

    return $fallback;
}

function sg_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}


/** 当前页面标识，供导航高亮使用 */
function sg_current_slug($archive): string
{
    try {
        if ($archive->is('index')) {
            return 'index';
        }
        if ($archive->is('page')) {
            return (string) $archive->slug;
        }
        if ($archive->is('post')) {
            return 'post';
        }
        if ($archive->is('archive')) {
            return 'archive';
        }
    } catch (\Throwable $e) {
        return '';
    }
    return '';
}

/**
 * 全站独立页的「缩略名 → 永久链接」映射。
 *
 * 一次查询、进程内复用：导航要判断「碎语页建没建」，模板要用永久链接，
 * 都不该各查一次。
 */
function sg_page_map(): array
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            if (class_exists('\\Widget\\Contents\\Page\\Rows')) {
                $pages = \Widget\Contents\Page\Rows::alloc();
                while ($pages->next()) {
                    $cache[(string) $pages->slug] = (string) $pages->permalink;
                }
            }
        } catch (\Throwable $e) {
            $cache = [];
        }
    }

    return $cache;
}

/** 站点里是否建了某个缩略名的独立页 */
function sg_page_exists(string $slug): bool
{
    return isset(sg_page_map()[$slug]);
}

/**
 * 按缩略名取独立页永久链接；页面不存在时回退到站点首页。
 * 用来避免模板里硬编码 xxx.html 造成的死链（站点未必建了同名页面）。
 */
function sg_page_url(string $slug): string
{
    $map = sg_page_map();
    if (isset($map[$slug])) {
        return $map[$slug];
    }

    $options = sg_options();
    $home = is_object($options) ? (string) $options->siteUrl : '';

    return rtrim($home, '/') . '/';
}

/** Typecho 返回模式检查管理员角色，失败时不重定向访客。 */
function sg_user_is_admin(): bool
{
    static $isAdmin = null;

    if ($isAdmin === null) {
        $isAdmin = false;
        try {
            if (class_exists('\\Widget\\User')) {
                $user = \Widget\User::alloc();
                $isAdmin = $user->pass('administrator', true);
            }
        } catch (\Throwable $e) {
            $isAdmin = false;
        }
    }

    return $isAdmin;
}

/**
 * 相对时间文案（供参考图文章卡使用，如「发表于 28 天前」）。
 * 只做正整数 + 中文单位，未来/无效时间返回空串由调用方回退到绝对日期。
 * 纯展示函数，不参与任何过滤/取数。
 */
function sg_time_ago(int $timestamp): string
{
    if ($timestamp <= 0) {
        return '';
    }
    $diff = time() - $timestamp;
    if ($diff < 0) {
        return '';
    }
    if ($diff < 60) {
        return '刚刚';
    }
    $units = [
        [31536000, '年'],
        [2592000, '个月'],
        [86400, '天'],
        [3600, '小时'],
        [60, '分钟'],
    ];
    foreach ($units as [$seconds, $label]) {
        if ($diff >= $seconds) {
            return intdiv($diff, $seconds) . ' ' . $label . '前';
        }
    }
    return '';
}

/** 独立页副标题：优先自定义字段 subtitle */function sg_page_sub($archive, string $default = ''): string
{
    try {
        $value = $archive->fields->subtitle;
        if (is_string($value) && $value !== '') {
            return $value;
        }
    } catch (\Throwable $e) {
        // 无字段时用默认值
    }
    return $default;
}
