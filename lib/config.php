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
 * 约定：一律从 https://picsum.photos 入口引用 —— 它会 302 到
 * fastly.picsum.photos 的带签名地址；手写 fastly 域名（缺 ?hmac=）一定 404。
 *
 * 2026-11-12 第七轮：按用户要求恢复为 picsum.photos（第六轮曾临时改成主题内置
 * dist/img/ph-1..6.svg）。万一访问不到该站，前端 initImageFallback() 会把失败的
 * <img> 换成内置占位图（window.SG.placeholder 是本地文件，见 lib/assets.php），
 * 不会留黑块。
 */
function sg_placeholder(int $id = 1015, int $width = 1200, int $height = 600): string
{
    return 'https://picsum.photos/id/' . $id . '/' . $width . '/' . $height;
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

/**
 * 首页个人卡的三项统计：文章 / 杂谈 / 照片。
 * 后台留空则自动查库；查库失败回落 0，绝不让页面 500。
 */
function sg_stats(): array
{
    $fallback = [
        'posts'  => sg_opt('sgStatPosts', ''),
        'notes'  => sg_opt('sgStatNotes', ''),
        'photos' => sg_opt('sgStatPhotos', ''),
    ];
    if ($fallback['posts'] !== '' && $fallback['notes'] !== '' && $fallback['photos'] !== '') {
        return $fallback;
    }

    $count = static function (string $type) : int {
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchObject(
                $db->select(['COUNT(*)' => 'num'])->from('table.contents')
                    ->where('type = ?', $type)->where('status = ?', 'publish')
            );
            return (int) ($row->num ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    };

    // 杂谈按分类统计（分类缩略名可在后台改，未命中时回落 0）
    $countCategory = static function (string $slug) : int {
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchObject(
                $db->select(['COUNT(*)' => 'num'])->from('table.contents')
                    ->join('table.relationships', 'table.contents.cid = table.relationships.cid')
                    ->join('table.metas', 'table.metas.mid = table.relationships.mid')
                    ->where('table.metas.type = ?', 'category')
                    ->where('table.metas.slug = ?', $slug)
                    ->where('table.contents.type = ?', 'post')
                    ->where('table.contents.status = ?', 'publish')
            );
            return (int) ($row->num ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    };

    return [
        'posts'  => $fallback['posts'] !== '' ? $fallback['posts'] : (string) $count('post'),
        'notes'  => $fallback['notes'] !== '' ? $fallback['notes'] : (string) $countCategory('chatter'),
        'photos' => $fallback['photos'] !== '' ? $fallback['photos'] : (string) $count('attachment'),
    ];
}

/**
 * 站点已运行时长（页脚「系统已稳定运行」那一行）。
 *
 * 数据源：后台「建站日期」`sgUptimeSince`；留空时取**最早一篇已发布文章**的时间。
 * 展示规则（2026-11-12 第八轮按用户要求定稿）：
 *   - 不满一年 → 「211 天 20 小时」
 *   - 满一年   → 「1 年 12 天 12 小时」（年 / 天 / 小时三段都写，缺省写 0）
 * 纯服务端计算，前端不再有每秒跳动的定时器；刷新页面即更新。
 *
 * @return array{text:string, days:int, hours:int, since:int}
 */
function sg_uptime(): array
{
    $since = trim(sg_opt('sgUptimeSince', ''));
    $start = null;
    if ($since !== '') {
        $ts = strtotime($since);
        if ($ts !== false && $ts > 0) {
            $start = $ts;
        }
    }

    if ($start === null) {
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchObject(
                $db->select(['MIN(created)' => 'first'])->from('table.contents')
                    ->where('type = ?', 'post')->where('status = ?', 'publish')
            );
            if (!empty($row->first)) {
                $start = (int) $row->first;
            }
        } catch (\Throwable $e) {
            $start = null;
        }
    }

    // 没数据、或后台填了个未来日期：不让页脚出现负数
    if ($start === null || $start > time()) {
        return ['text' => '不足 1 小时', 'days' => 0, 'hours' => 0, 'since' => 0];
    }

    // 两个时间点必须用同一个时区做日历差，否则「年 / 天」会差 8 小时
    $tz = new \DateTimeZone(date_default_timezone_get());
    $from = (new \DateTimeImmutable('@' . $start))->setTimezone($tz);
    $diff = $from->diff(new \DateTimeImmutable('now', $tz));
    $days = (int) $diff->days;   // 总天数，不受月份进位影响
    $hours = (int) $diff->h;

    if ($diff->y >= 1) {
        // $diff->d 只是「月内第几天」，不能直接用：2 年零 10 个月会被算成 11 天。
        // 先按整年推进到锚点，再取锚点到现在的总天数，才是「几年零几天」里的那个天数。
        $years = (int) $diff->y;
        $anchor = $from->modify('+' . $years . ' years');
        $restDays = (int) $anchor->diff(new \DateTimeImmutable('now', $tz))->days;
        $text = $years . ' 年 ' . $restDays . ' 天 ' . $hours . ' 小时';
    } else {
        $text = $days . ' 天 ' . $hours . ' 小时';
    }

    return ['text' => $text, 'days' => $days, 'hours' => $hours, 'since' => $start];
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
 * 按缩略名取独立页永久链接；页面不存在时回退到站点首页。
 * 用来避免模板里硬编码 xxx.html 造成的死链（站点未必建了同名页面）。
 */
function sg_page_url(string $slug): string
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

    if (isset($cache[$slug])) {
        return $cache[$slug];
    }

    $options = sg_options();
    $home = is_object($options) ? (string) $options->siteUrl : '';

    return rtrim($home, '/') . '/';
}

/** 独立页副标题：优先自定义字段 subtitle */
function sg_page_sub($archive, string $default = ''): string
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
