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
 * 首页个人卡的三项统计：文章 / 碎语 / 照片，**全部自动统计**。
 *
 * 2026-11-13 第二十轮按用户要求去掉手填：以前三个数字允许后台覆盖，
 * 结果站点内容变了数字不动，还被误当成 bug。现在一律实时数：
 *  - 文章：contents 里 type=post 且已发布；
 *  - 碎语：「碎语」分类下的文章条数（sg_notes_count()）；
 *  - 照片：usr/uploads 下能被相册认出来的图片总数（sg_albums() 累加）。
 * 任何一步失败都回落 0，绝不让首页 500。
 */
function sg_stats(): array
{
    $count = static function (string $type): int {
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

    $photos = 0;
    foreach (sg_albums() as $album) {
        $photos += is_array($album['photos'] ?? null) ? count($album['photos']) : 0;
    }

    return [
        'posts'  => (string) $count('post'),
        'notes'  => (string) sg_notes_count(),
        'photos' => (string) $photos,
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

/**
 * 当前访问者是不是登录用户。
 *
 * 只用于「管理员改完配置立刻看到效果」这一件事：远程歌单缓存、后台 JSON 之类
 * 在登录状态下绕过，前台访客仍然走缓存。取不到用户信息时一律当访客处理。
 */
function sg_user_is_admin(): bool
{
    static $isAdmin = null;

    if ($isAdmin === null) {
        $isAdmin = false;
        try {
            if (class_exists('\\Widget\\User')) {
                $user = \Widget\User::alloc();
                $isAdmin = is_object($user) && method_exists($user, 'hasLogin') && $user->hasLogin();
            }
        } catch (\Throwable $e) {
            $isAdmin = false;
        }
    }

    return $isAdmin;
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
