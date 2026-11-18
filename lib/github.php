<?php
/**
 * GitHub 仓库 → 项目卡片。
 *
 * 数据流：`/users/{user}/repos` → 归一化成和「项目 JSON」一模一样的结构，
 * 模板层不用关心数据是从 GitHub 来的还是后台手填的。
 *
 * 为什么放在主题里自己取，而不是用现成插件：
 * 项目页只需要「仓库名 / 简介 / 语言 / 星标 / 链接」这几项，
 * jsDelivr 一类的第三方 widget 会带一堆我们不要的样式和脚本，
 * 和「一种做法一处用」的视觉约定冲突。
 *
 * 四层回退（与歌单同构）：
 *   ① GitHub API（本文件）→ ② 后台「项目 JSON」→ ③ dist/data/projects.json → ④ 空态。
 * 任何一层拿不到都不该让页面白掉，所以这里只负责「拿不到就返回空数组」，
 * 由 page-projects.php 决定退到哪一层，失败原因留在 sg_github_error()。
 */

/** 读设置（未配置时 user 为空 → 整个 GitHub 层关闭） */
function sg_github_cfg(): array
{
    /* Token 不再有后台设置项（2026-11-13 第二十轮）：
       主题设置会被导出/备份，PAT 放进去等于随手泄露。
       想提额度就在站点 config.inc.php 里 define('SG_GITHUB_TOKEN', 'ghp_xxx')，
       这里只从常量读；老站 options 里残留的 sgGithubToken 仅作兼容兜底。 */
    $token = defined('SG_GITHUB_TOKEN') ? (string) SG_GITHUB_TOKEN : '';
    if ($token === '') {
        $token = (string) sg_opt('sgGithubToken', '');
    }
    return [
        'user'   => trim((string) sg_opt('sgGithubUser', '')),
        'repos'  => trim((string) sg_opt('sgGithubRepos', '')),
        'limit'  => (int) sg_opt('sgGithubLimit', '12'),
        'token'  => trim($token),
    ];
}

/** 上一次取数的失败原因（空串 = 正常） */
function sg_github_error(): string
{
    return (string) ($GLOBALS['SG_GITHUB_ERROR'] ?? '');
}

/** 选中清单里写了、但 GitHub 上已经没有的仓库名 */
function sg_github_missing(): array
{
    return (array) ($GLOBALS['SG_GITHUB_MISSING'] ?? []);
}

function sg_github_fail(string $message): void
{
    $GLOBALS['SG_GITHUB_ERROR'] = $message;
}

/**
 * 缓存文件名。
 *
 * 只跟「账号 + token」有关，和选中清单无关：改勾选不该触发重新取数
 * （未认证时每小时只有 60 次额度，改一次勾选打一次 API 太奢侈）。
 */
function sg_github_cache_file(string $user, string $token): string
{
    $key = hash('sha256', SG_ROOT . '|' . $user . '|' . $token);
    return sg_cache_file('github-' . $key . '.json');
}

/**
 * 取仓库列表（带缓存）。
 *
 * 返回 [] 时看 sg_github_error()：'rate' 限流、'404' 账号不存在、'net' 网络失败。
 *
 * 缓存 30 分钟，**只有显式 `?sg-refresh=1` 才绕过**（原来登录管理员就绕过，
 * 结果是站长每次打开项目页都直连 api.github.com，等 1–10s）。
 * 访客与站长都吃缓存，避免每次访问都打外网。
 */
function sg_github_repos(): array
{
    $cfg = sg_github_cfg();
    if ($cfg['user'] === '') {
        return [];
    }

    $cacheFile = sg_github_cache_file($cfg['user'], $cfg['token']);
    /* 失败也缓存一小会儿：国内机房连不上 api.github.com 时，
       每个访客都等一次超时（最多 ~10s）会让项目页慢得不像话。 */
    $failFile = $cacheFile !== '' ? $cacheFile . '.fail' : '';
    $bypass = isset($_GET['sg-refresh']) && sg_user_is_admin();
    $ttl = 1800;

    if (!$bypass && $cacheFile !== '' && is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached) {
            return $cached;
        }
    }

    if (!$bypass && $failFile !== '' && is_file($failFile) && (time() - (int) filemtime($failFile)) < 300) {
        sg_github_fail(trim((string) file_get_contents($failFile)));
        return [];
    }

    $url = 'https://api.github.com/users/' . rawurlencode($cfg['user'])
        . '/repos?per_page=100&sort=pushed';
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if ($cfg['token'] !== '') {
        $headers[] = 'Authorization: Bearer ' . $cfg['token'];
    }

    $body = sg_http_get($url, $headers, 5);
    $rows = $body !== '' ? json_decode($body, true) : null;

    if (!is_array($rows) || !isset($rows[0])) {
        // GitHub 的错误也是 JSON：{"message":"API rate limit exceeded…"} / {"message":"Not Found"}
        $message = '';
        if (is_array($rows) && isset($rows['message'])) {
            $message = (string) $rows['message'];
        }
        if (stripos($message, 'rate limit') !== false) {
            sg_github_fail('GitHub 接口限流（未登录每小时 60 次），稍后再试或填一个 Token');
        } elseif (stripos($message, 'not found') !== false) {
            sg_github_fail('找不到 GitHub 用户「' . $cfg['user'] . '」');
        } else {
            sg_github_fail('连不上 GitHub（超时或被墙），已退回手动数据');
        }

        if ($failFile !== '') @file_put_contents($failFile, sg_github_error());

        // 失败时退回上一份缓存：宁可展示旧数据，也好过整页空掉
        if ($cacheFile !== '' && is_file($cacheFile)) {
            $stale = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($stale) && $stale) {
                return $stale;
            }
        }
        return [];
    }

    if ($failFile !== '' && is_file($failFile)) {
        @unlink($failFile);
    }

    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['name'])) {
            continue;
        }
        $clean[] = $row;
    }

    if ($clean) {
        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($json)) {
            if ($cacheFile !== '') @file_put_contents($cacheFile, $json);
        }
    }

    return $clean;
}

/**
 * 解析「仓库清单」文本域。
 *
 * 一行一个，支持三种写法：
 *   soraglass                → 只写仓库名
 *   oyj135/soraglass         → owner/repo（owner 写错也认，以仓库名为准）
 *   soraglass | Typecho 主题  → 竖线后面是简介覆盖（给没有 description 的仓库补一句话）
 *
 * 空行与 # 开头的行忽略，方便站长在清单里写注释。
 *
 * @return array<int,array{name:string,note:string}>
 */
function sg_github_parse_selection(string $raw): array
{
    $list = [];
    foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $note = '';
        if (str_contains($line, '|')) {
            [$line, $note] = array_map('trim', explode('|', $line, 2));
        }
        if (str_contains($line, '/')) {
            $parts = explode('/', $line);
            $line = trim((string) end($parts));
        }
        if ($line === '') {
            continue;
        }
        $list[] = ['name' => $line, 'note' => $note];
    }
    return $list;
}

/** 语言 → 卡片左上角那个字符（沿用现有 1.75rem emoji 位，不引图标库） */
function sg_github_icon(string $language): string
{
    static $map = [
        'php'              => '✦',
        'java'             => '☕',
        'javascript'       => '✎',
        'typescript'       => '✎',
        'vue'              => '◈',
        'html'             => '◇',
        'css'              => '◈',
        'scss'             => '◈',
        'python'           => '⌘',
        'go'               => '◍',
        'rust'             => '⚙',
        'c'                => '⚙',
        'c++'              => '⚙',
        'c#'               => '⚙',
        'shell'            => '⌘',
        'lua'              => '◍',
        'kotlin'           => '◍',
        'swift'            => '⌘',
        'ruby'             => '◈',
        'dart'             => '◈',
        'jupyter notebook' => '⌘',
    ];
    $key = strtolower(trim($language));
    return $map[$key] ?? '✦';
}

/**
 * 语言色点（第三十七轮）。
 *
 * 项目卡用一枚 8px 圆点标语言，取代原来裸 emoji 的图标块 —— 小、准、不抢。
 * 这些是各语言在 GitHub linguist 里的品牌色，属于「数据自带的语义色」，
 * 不进 tokens.css 的调色板：它们不该跟着昼夜主题或签名色变，
 * 否则「PHP 是紫的、TypeScript 是蓝的」这条通用认知就没了。
 * 认不出来的语言一律用签名色，不硬编一个乱猜的颜色。
 */
function sg_lang_color(string $language): string
{
    static $map = [
        'php'               => '#4f5d95',
        'typescript'        => '#3178c6',
        'javascript'        => '#f1e05a',
        'vue'               => '#41b883',
        'html'              => '#e34c26',
        'css'               => '#563d7c',
        'scss'              => '#c6538c',
        'python'            => '#3572a5',
        'go'                => '#00add8',
        'rust'              => '#dea584',
        'java'              => '#b07219',
        'kotlin'            => '#a97bff',
        'swift'             => '#f05138',
        'dart'              => '#00b4ab',
        'c'                 => '#555555',
        'c++'               => '#f34b7d',
        'c#'                => '#178600',
        'shell'             => '#89e051',
        'ruby'              => '#701516',
        'lua'               => '#000080',
        'jupyter notebook'  => '#da5b0b',
    ];
    $key = strtolower(trim($language));
    return $map[$key] ?? 'var(--sg-accent-ink)';
}

/** 「更新于」用的日期：UTC → 站点时区 */
function sg_github_date(string $iso): string
{
    if ($iso === '') {
        return '';
    }
    try {
        $date = new \DateTime($iso);
        $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $date->format('Y.m.d');
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 归一化成项目卡片数组。
 *
 * 输出字段与「项目 JSON」完全对齐（name / description / icon / tags / githubUrl），
 * 另外多给 meta（星标、更新时间）、badge（Fork / 已归档）与 keywords（搜索索引）。
 *
 * @param int|null $limit 覆盖设置里的条数（null = 用 sgGithubLimit）
 */
function sg_github_projects(?int $limit = null): array
{
    $GLOBALS['SG_GITHUB_ERROR'] = '';
    $GLOBALS['SG_GITHUB_MISSING'] = [];

    $cfg = sg_github_cfg();
    if ($cfg['user'] === '') {
        return [];
    }

    $repos = sg_github_repos();
    if (!$repos) {
        return [];
    }

    $byName = [];
    foreach ($repos as $repo) {
        $byName[strtolower((string) $repo['name'])] = $repo;
    }

    $cap = $limit ?? $cfg['limit'];
    if ($cap < 0) {
        $cap = 0;
    }

    $selection = sg_github_parse_selection($cfg['repos']);
    $picked = [];

    if ($selection) {
        $missing = [];
        foreach ($selection as $item) {
            $key = strtolower($item['name']);
            if (!isset($byName[$key])) {
                $missing[] = $item['name'];
                continue;
            }
            $picked[] = ['repo' => $byName[$key], 'note' => $item['note']];
        }
        $GLOBALS['SG_GITHUB_MISSING'] = $missing;
        if ($cap > 0) {
            $picked = array_slice($picked, 0, $cap);
        }
    } else {
        // 自动模式：排除 fork 与已归档，按最近推送倒序
        $auto = [];
        foreach ($repos as $repo) {
            if (!empty($repo['fork']) || !empty($repo['archived'])) {
                continue;
            }
            $auto[] = $repo;
        }
        usort($auto, static function ($a, $b) {
            return strcmp((string) ($b['pushed_at'] ?? ''), (string) ($a['pushed_at'] ?? ''));
        });
        if ($cap > 0) {
            $auto = array_slice($auto, 0, $cap);
        }
        foreach ($auto as $repo) {
            $picked[] = ['repo' => $repo, 'note' => ''];
        }
    }

    $projects = [];
    foreach ($picked as $entry) {
        $repo = $entry['repo'];
        $language = trim((string) ($repo['language'] ?? ''));

        $description = trim((string) ($entry['note'] ?? ''));
        if ($description === '') {
            $description = trim((string) ($repo['description'] ?? ''));
        }
        /* 占位文案不进关键词索引：否则搜「看看」会把所有没简介的仓库都筛出来 */
        $hasDescription = $description !== '';
        if (!$hasDescription) {
            $description = '暂无简介，点开仓库看看。';
        }

        $tags = [];
        foreach ((array) ($repo['topics'] ?? []) as $topic) {
            $topic = trim((string) $topic);
            if ($topic !== '' && !in_array($topic, $tags, true)) {
                $tags[] = $topic;
            }
        }
        if ($language !== '' && !in_array($language, $tags, true)) {
            $tags[] = $language;
        }
        $tags = array_slice($tags, 0, 3);

        $badge = '';
        if (!empty($repo['archived'])) {
            $badge = '已归档';
        } elseif (!empty($repo['fork'])) {
            $badge = 'Fork';
        }

        $stars = (int) ($repo['stargazers_count'] ?? 0);
        $updated = sg_github_date((string) ($repo['pushed_at'] ?? ''));

        $name = (string) $repo['name'];
        $keywords = implode(' ', array_unique(array_filter([
            $name,
            $hasDescription ? $description : '',
            implode(' ', $tags),
            $language,
            $badge,
        ])));

        $projects[] = [
            'name'        => $name,
            'description' => $description,
            'icon'        => sg_github_icon($language),
            'tags'        => $tags,
            'githubUrl'   => (string) ($repo['html_url'] ?? ('https://github.com/' . $cfg['user'] . '/' . $name)),
            'meta'        => array_values(array_filter([
                $stars > 0 ? '★ ' . $stars : '',
                $updated !== '' ? '更新于 ' . $updated : '',
            ])),
            'badge'       => $badge,
            'keywords'    => $keywords,
            /* 第三十七轮：卡片改成结构化渲染（语言点 + 星数 + 更新日期各占一格），
               meta 那串「★ 3 · 更新于 …」不再需要拆字符串。手填 JSON 没有这些字段时，
               模板会退回 meta 解析，所以这里多给的只是「有则更好」。 */
            'language'    => $language,
            'stars'       => $stars,
            'updated'     => $updated,
            'forks'       => (int) ($repo['forks_count'] ?? 0),
            'archived'    => !empty($repo['archived']),
            'fork'        => !empty($repo['fork']),
        ];
    }

    return $projects;
}

/**
 * 页面顶部那行小字用：数据源标注
 *
 * @param int|null $count 实际展示的条数；传了就用它，避免「清单写了 4 个、只显示 3 个」的困扰
 */
function sg_github_source_line(?int $count = null): string
{
    $cfg = sg_github_cfg();
    if ($cfg['user'] === '' || sg_github_error() !== '') {
        /* 取数失败时页面展示的是兜底数据，别再声称「项目来自 GitHub」 */
        return '';
    }
    $picked = count(sg_github_parse_selection($cfg['repos']));
    if ($picked > 0 && $count !== null) {
        $line = '项目来自 GitHub · @' . $cfg['user'] . '（清单挑选 ' . $count . ' / ' . $picked . ' 个）';
        return $line;
    }
    $line = '项目来自 GitHub · @' . $cfg['user'];
    $line .= $picked > 0 ? '（手动挑选 ' . $picked . ' 个）' : '（按最近更新）';
    return $line;
}

/**
 * 项目页的唯一取数入口：四层回退。
 *
 *   ① 后台「项目 JSON」—— 手填的永远最优先，站长说了算
 *   ② GitHub（`sgGithubUser` 非空时）—— 站长的仓库列表
 *   ③ dist/data/projects.json —— 主题自带示例
 *   ④ 空数组 —— 模板层给空态
 *
 * 拿不到就往下掉，任何一层失败都不该让页面白掉；
 * 失败原因留在 sg_github_error() / sg_github_missing()，给管理员看。
 *
 * @return array<int,array<string,mixed>>
 */
function sg_projects(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $manual = trim((string) sg_opt('sgProjects', ''));
    if ($manual !== '') {
        $decoded = json_decode($manual, true);
        if (is_array($decoded) && $decoded) {
            return $cache = array_values(array_filter($decoded, 'is_array'));
        }
    }

    if (sg_github_cfg()['user'] !== '') {
        $repos = sg_github_projects();
        if ($repos) {
            return $cache = $repos;
        }
    }

    $fallback = sg_json('sgProjects', 'projects', []);
    return $cache = is_array($fallback) ? array_values(array_filter($fallback, 'is_array')) : [];
}

/** 项目页数据源说明（管理员才看得到失败原因） */
function sg_projects_diag(): string
{
    $manual = trim((string) sg_opt('sgProjects', ''));
    if ($manual !== '') {
        $decoded = json_decode($manual, true);
        if (is_array($decoded) && $decoded) {
            return '数据源：后台「项目 JSON」，共 ' . count($decoded) . ' 个';
        }
    }

    $cfg = sg_github_cfg();
    if ($cfg['user'] !== '') {
        $projects = sg_projects();
        if (sg_github_error() !== '') {
            return '数据源：GitHub 取数失败，已回退主题示例；' . sg_github_error();
        }
        $line = '数据源：GitHub · @' . $cfg['user'] . '，共 ' . count($projects) . ' 个仓库';
        $missing = sg_github_missing();
        if ($missing) {
            $line .= '；清单里这些仓库已不存在：' . implode('、', $missing);
        }
        return $line;
    }

    return '数据源：主题示例 dist/data/projects.json';
}

