<?php
/**
 * One configurable source: a complete Meting-compatible HTTP(S) endpoint.
 * Keep the sgPlaylist option key so existing endpoint settings survive upgrades.
 * 2026-11-25：随包示例 MP3 与 playlist.json 已移除，歌单完全依赖后台填写的
 * Meting 兼容端点；留空仍表示不展示歌单（语义不变）。
 */
function sg_playlist(): array
{
    $raw = trim((string) sg_opt('sgPlaylist', ''));

    unset($GLOBALS['SG_PLAYLIST_ERROR']);
    if ($raw === '') {
        $GLOBALS['SG_PLAYLIST_SOURCE'] = '未配置 Meting 接口';
        return [];
    }
    if (!preg_match('#^https?://#i', $raw) || filter_var($raw, FILTER_VALIDATE_URL) === false) {
        $GLOBALS['SG_PLAYLIST_SOURCE'] = 'Meting 接口地址无效';
        $GLOBALS['SG_PLAYLIST_ERROR'] = '请填写完整的 http(s) Meting 接口地址。';
        return [];
    }
    $GLOBALS['SG_PLAYLIST_SOURCE'] = 'Meting · ' . sg_meting_label($raw);
    return sg_playlist_cap(sg_playlist_media(sg_meting_playlist($raw)));
}

/**
 * 当前生效的歌单来源，给模板显示用。
 */
function sg_playlist_source(): string
{
    return (string) ($GLOBALS['SG_PLAYLIST_SOURCE'] ?? '未配置 Meting 接口');
}

/**
 * 最近一次取远程歌单失败的原因；没有失败时是空串。
 */
function sg_playlist_error(): string
{
    return (string) ($GLOBALS['SG_PLAYLIST_ERROR'] ?? '');
}

/**
 * 音乐页底部的一行诊断：来源 + 条数（+ 失败原因）。
 */
function sg_playlist_diag(int $count): string
{
    $text = sg_playlist_source() . '，共 ' . $count . ' 首';
    $error = sg_playlist_error();
    if ($error !== '') {
        $text .= '；' . $error;
    }
    return $text;
}

/**
 * 把 Meting 地址缩成 `server/type/id`，用于「当前数据源」那一行。
 */
function sg_meting_label(string $url): string
{
    $query = [];
    $parts = parse_url($url);
    if (is_array($parts) && isset($parts['query'])) {
        parse_str((string) $parts['query'], $query);
    }
    $bits = [];
    foreach (['server', 'type', 'id'] as $key) {
        $value = trim((string) ($query[$key] ?? ''));
        if ($value !== '') {
            $bits[] = $value;
        }
    }

    return $bits ? implode('/', $bits) : (string) parse_url($url, PHP_URL_HOST);
}

/**
 * 远程歌单截断。
 *
 * 一个热门网易云歌单动辄几百首：整份塞进 `window.SG.playlist` 与歌单列表，
 * 单页 HTML 会膨胀到 500 KB 以上，而且每一首都带一张懒加载封面。
 * 这里按后台「歌单上限」截断（默认 50，填 0 不截断）。
 *
 * @param array<int,array<string,mixed>> $list
 * @return array<int,array<string,mixed>>
 */
function sg_playlist_cap(array $list): array
{
    $limit = (int) sg_opt('sgPlaylistLimit', '50');
    if ($limit > 0 && count($list) > $limit) {
        return array_slice($list, 0, $limit);
    }
    return $list;
}

/**
 * 歌单里的 url / cover 归一化。
 *
 * 歌单只来自 Meting 接口（见 sg_playlist()），url / pic 均为完整 https 地址，
 * sg_media() 对外链原样返回；主题内相对路径（如默认封面 cover-default.svg）
 * 才补主题目录前缀。随包示例 MP3 与 playlist.json 已于 2026-11-25 移除。
 */
function sg_playlist_media(array $list): array
{
    foreach ($list as $i => $track) {
        if (!is_array($track)) {
            continue;
        }
        foreach (['url', 'cover'] as $key) {
            $value = trim((string) ($track[$key] ?? ''));
            if ($value !== '') {
                $list[$i][$key] = sg_media($value);
            }
        }
    }
    return $list;
}

/**
 * Meting 兼容 API → 歌单数组。
 *
 * Meting 是 APlayer 生态里事实上的后端契约（Typecho 的 `Meting` 插件、MetingJS、
 * 多数主题都吃同一份 JSON），所以这里只实现「读它的 JSON」，不引入 APlayer / 第三方 JS。
 * 字段同时兼容两套命名：
 *   `name / artist / url / pic / lrc`（APlayer 标准）
 *   `title / author / url / pic`（i-meto 老接口）
 *
 * `lrc` 有两副面孔：有的是歌词正文（含 [mm:ss]），有的是纯文本地址（`type=lrc`）。
 * 后者原样交给前端按需 fetch —— 该接口带 `Access-Control-Allow-Origin: *`。
 *
 * 同地址缓存 1 小时；管理员主动 sg-refresh=1 时绕缓存。
 * 更换接口地址使用独立缓存，网络失败可回退该接口的旧缓存。
 */
function sg_meting_playlist(string $endpoint): array
{
    $endpoint = trim($endpoint);
    if ($endpoint === '' || !preg_match('#^https?://#i', $endpoint)) {
        return [];
    }

    /* 2026-11-14 第三十七轮：这两个变量决定「翻一页要等多久」。
       原来写的是 `$bypass = sg_user_is_admin();` + 600 秒 TTL —— 站长自己浏览时
       永远是登录态，于是**每一次翻页都绕过缓存直连 Meting**，而 sg_http_get 的
       超时是 6 秒；接口正常时也要多花 0.3–1s，接口抖动时整页卡住几秒。
       这就是「页面间跳转很慢」最直接的原因：PHP 在吐第一个字节之前先等了一次外网。
       现在缓存优先、谁登录都一样，要强制刷新用 `?sg-refresh=1`（仅管理员有效），
       TTL 10 分钟 → 1 小时。 */
    $bypass = isset($_GET['sg-refresh']) && sg_user_is_admin();
    $ttl = 3600;
    $cacheFile = sg_cache_file('meting-' . hash('sha256', SG_ROOT . '|' . $endpoint) . '.json');

    if (!$bypass && $cacheFile !== '' && is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < $ttl) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached) {
            return $cached;
        }
    }

    $body = sg_http_get($endpoint, ['Accept: application/json']);
    $rows = $body !== '' ? json_decode($body, true) : null;
    if (!is_array($rows)) {
        /* 拿不到就退回上一份缓存，总比音乐页空着强；失败原因照样报到页面上 */
        $stale = is_file($cacheFile) ? json_decode((string) file_get_contents($cacheFile), true) : null;
        if (is_array($stale) && $stale) {
            $GLOBALS['SG_PLAYLIST_ERROR'] = '接口这次没返回可解析的 JSON，先用的是上次缓存。';
            return $stale;
        }
        $GLOBALS['SG_PLAYLIST_ERROR'] = '接口没有返回可解析的 JSON（检查地址是否可达、是否需要换一个公共实例）。';
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $title = trim((string) ($row['name'] ?? $row['title'] ?? ''));
        $url = trim((string) ($row['url'] ?? ''));
        if ($title === '' || $url === '') {
            continue;   // 没有播放地址的条目直接丢掉，点了没反应比不显示更糟
        }
        $cover = trim((string) ($row['pic'] ?? $row['cover'] ?? ''));
        $out[] = [
            'title'  => $title,
            'artist' => trim((string) ($row['artist'] ?? $row['author'] ?? '')),
            'album'  => trim((string) ($row['album'] ?? '')),
            'cover'  => $cover !== '' ? $cover : sg_theme_url('dist/img/cover-default.svg'),
            'url'    => $url,
            'lrc'    => trim((string) ($row['lrc'] ?? '')),
            'lyric'  => '',
        ];
    }

    if (!$out) {
        $GLOBALS['SG_PLAYLIST_ERROR'] = '接口返回的条目里没有可播放地址（公共实例可能限制了该歌单）。';
        return [];
    }

    if ($cacheFile !== '') {
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return $out;
}
