<?php
/**
 * 文章自定义字段读取。
 * 源项目 frontmatter 的 cover / description 在此映射为 Typecho 自定义字段。
 */
function sg_field($archive, string $key, string $default = ''): string
{
    $value = $archive->fields->{$key};
    return (is_string($value) && $value !== '') ? $value : $default;
}

function sg_cover($archive): string
{
    return sg_field($archive, 'cover', sg_opt('sgDefaultCover', sg_theme_url('dist/img/cover-default.svg')));
}

function sg_description($archive): string
{
    $custom = sg_field($archive, 'description', '');
    if ($custom !== '') {
        return $custom;
    }
    return sg_e($archive->excerpt(120, '...'));
}

/** 安全地渲染主题配置中的 JSON 字段值 */
function sg_value($row, string $key, string $default = ''): string
{
    if (!is_array($row) || !isset($row[$key]) || !is_scalar($row[$key]) || (string) $row[$key] === '') {
        return $default;
    }
    return sg_e((string) $row[$key]);
}

/**
 * 静态数据（JSON）里的媒体地址归一化。
 *
 * 解析规则与 sg_bg_url() 完全一致：
 *   外链 / 站内绝对路径 / data: → 原样返回；
 *   `usr/uploads/2026/11/x.jpg` 这种「站点根相对路径」→ 补 siteUrl；
 *   其余（如 `dist/img/x.svg`）→ 补主题目录前缀。
 *
 * 第六轮把这三档抽出来共用：之前这里只认「主题内相对路径」，后台附件地址
 * （usr/uploads/...，宝塔后台传图默认就长这样）会被拼成
 * `/usr/themes/soraglass/usr/uploads/...`，封面 / 相册图必然 404。
 */
function sg_media(string $value, string $fallback = ''): string
{
    $url = sg_bg_url($value);
    return $url !== '' ? $url : $fallback;
}

/**
 * 背景图地址解析：比 sg_media() 多认一种「站点根相对路径」。
 *
 * 宝塔后台传图默认落在 `usr/uploads/YYYY/MM/xxx.jpg`，用户复制这段填进设置里
 * 时不会带前导斜杠；若直接丢给 sg_media()，会被当成主题内文件拼成
 * `/usr/themes/soraglass/usr/uploads/...`，必然 404。
 */
function sg_bg_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (preg_match('#^(?:https?:)?//#i', $value) === 1
        || str_starts_with($value, 'data:')
        || str_starts_with($value, '/')) {
        return $value;
    }
    if (str_starts_with($value, 'usr/') || str_starts_with($value, 'www/')) {
        $options = sg_options();
        $root = is_object($options) ? rtrim((string) $options->siteUrl, '/') : '';
        return $root . '/' . $value;
    }
    return sg_theme_url($value);
}

/** 文章分类列表（名称 + 链接），取不到时返回空数组 */
function sg_categories($archive): array
{
    try {
        $cats = $archive->categories;
        if (!is_array($cats)) {
            return [];
        }
        $out = [];
        foreach ($cats as $cat) {
            if (is_array($cat) && isset($cat['name'])) {
                $out[] = ['name' => (string) $cat['name'], 'permalink' => (string) ($cat['permalink'] ?? '')];
            } elseif (is_object($cat) && isset($cat->name)) {
                $out[] = ['name' => (string) $cat->name, 'permalink' => (string) ($cat->permalink ?? '')];
            }
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 取文章的第一个分类名，用于封面徽章（取不到时返回默认值） */
function sg_first_category($archive, string $default = '文章'): string
{
    $cats = sg_categories($archive);
    return $cats ? $cats[0]['name'] : $default;
}

/** 以字符串形式取摘要（excerpt 是 echo 型方法，这里用缓冲转为字符串） */
function sg_excerpt($archive, int $length = 60, string $suffix = '...'): string
{
    try {
        ob_start();
        $archive->excerpt($length, $suffix);
        return trim((string) ob_get_clean());
    } catch (\Throwable $e) {
        return '';
    }
}

/**
 * 独立页统一页头（大标题 + 副标题 + 可选搜索框，可选返回胶囊）。
 *
 * 为什么是函数而不是 partial：Typecho 的 need() 实现是方法内的 require
 * （var/Widget/Archive.php: need() { require $this->themeDir . $fileName; }），
 * 调用方作用域的变量不会传进去，partial 里读 $sgHead 永远是空数组 ——
 * 线上所有独立页标题消失就是这个原因。
 *
 * 2026-11-12 第四轮：$back 默认改成 false。独立页本身就是导航一级入口
 * （侧栏 / 顶部导航都能直达），页头再挂一个「返回上一级」既多余又抢视觉。
 * 文章页与列表页仍在 page.php / post.php 里各自渲染 `.sg-back`。
 */
function sg_page_head(array $head = []): void
{
    $title = (string) ($head['title'] ?? '');
    $sub = (string) ($head['sub'] ?? '');
    $back = (bool) ($head['back'] ?? false);
    $search = (bool) ($head['search'] ?? false);
    $center = (bool) ($head['center'] ?? false);
    /* 2026-11-12 第九轮：允许调用方直接渲染成隐藏状态。
       光影画廊的二级路由（?album=<id>）要在服务端就藏掉页头，否则刷新详情页
       会先闪一下「光影画廊」大标题再被 gallery.ts 收起。 */
    $hidden = (bool) ($head['hidden'] ?? false);
    $placeholder = (string) ($head['placeholder'] ?? '搜索标题、描述或标签…');
    $options = sg_options();
    $home = is_object($options) ? rtrim((string) $options->siteUrl, '/') . '/' : '/';
    ?>
<div class="sg-page-head"<?php echo $hidden ? ' hidden' : ''; ?>>
  <?php if ($back) : ?>
  <a class="sg-back" href="<?php echo sg_e($home); ?>"><?php echo sg_icon('arrow-left'); ?>返回上一级</a>
  <?php endif; ?>
  <div class="sg-page-head-row<?php echo $center ? ' is-centered' : ''; ?>">
    <div>
      <h1 class="sg-page-title"><?php echo sg_e($title); ?></h1>
      <?php if ($sub !== '') : ?><p class="sg-page-sub"><?php echo sg_e($sub); ?></p><?php endif; ?>
    </div>
    <?php if ($search) : ?>
    <form class="sg-search sg-search--sm" role="search" method="get" action="<?php echo sg_e($home); ?>">
      <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
      <input class="sg-search-input" type="search" name="s" placeholder="<?php echo sg_e($placeholder); ?>" aria-label="站内搜索">
    </form>
    <?php endif; ?>
  </div>
</div>
    <?php
}

/** 把标签渲染成一组胶囊（保留原生标签链接） */function sg_tag_chips($archive): string
{
    try {
        ob_start();
        $archive->tags('@@SG@@', false, '');
        $raw = trim((string) ob_get_clean());
    } catch (\Throwable $e) {
        return '';
    }
    if ($raw === '') {
        return '';
    }
    $html = '';
    foreach (array_filter(array_map('trim', explode('@@SG@@', $raw))) as $item) {
        $item = str_replace('<a ', '<a class="sg-tag-link" ', (string) $item);
        $html .= '<span class="sg-meta-chip is-pink">#' . $item . '</span>';
    }
    return $html;
}

/**
 * 批量取文章标签（cid => [['name'=>..,'slug'=>..], ...]），一次查询搞定整页。
 *
 * 为什么不遍历 `$archive->tags`：列表 widget 拿到的是
 * Widget\Contents\Post\Recent 的行对象，tags() 方法是 Widget\Archive 的，
 * 在这些行上不存在；逐条查询又会变成 N+1。直接连表是最稳的写法。
 *
 * 同时取 slug：前端按标签筛选时用 slug 比对，避免中文标签在
 * 「标签云输出的是转义后名字、数据库里是原文」这种情况下匹配不上。
 */
function sg_post_tags_map(array $cids): array
{
    $cids = array_values(array_unique(array_filter(array_map('intval', $cids))));
    if (!$cids) {
        return [];
    }

    $map = [];
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select([
                'table.relationships.cid' => 'cid',
                'table.metas.name'        => 'name',
                'table.metas.slug'        => 'slug',
            ])
                ->from('table.relationships')
                ->join('table.metas', 'table.metas.mid = table.relationships.mid')
                ->where('table.metas.type = ?', 'tag')
                ->where('table.relationships.cid IN ?', $cids)
        );
        foreach ($rows as $row) {
            $cid  = (int) ($row['cid'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            if ($cid > 0 && $name !== '') {
                $map[$cid][] = ['name' => $name, 'slug' => $slug !== '' ? $slug : $name];
            }
        }
    } catch (\Throwable $e) {
        return [];
    }

    return $map;
}

/**
 * 近一年发文热力图：返回 ['YYYY-MM-DD' => 篇数]。
 * 只取 created 字段自己分桶，不依赖数据库的日期函数（MySQL / SQLite 都能跑）。
 */
function sg_post_heatmap(): array
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select(['table.contents.created' => 'created'])
                ->from('table.contents')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->where('table.contents.created > ?', time() - 366 * 86400)
        );
    } catch (\Throwable $e) {
        return [];
    }

    $map = [];
    foreach ($rows as $row) {
        $ts = (int) ($row['created'] ?? 0);
        if ($ts <= 0) {
            continue;
        }
        $key = date('Y-m-d', $ts);
        $map[$key] = ($map[$key] ?? 0) + 1;
    }

    return $map;
}

/**
 * 最近动态：文章 / 杂谈 / 说说混排，供关于页「研究动态」的时间线使用。
 *
 * 一次查库搞定，不逐条问分类：先取最近 N 篇已发布 post，再用一次
 * relationships + metas 连表拿到各自的分类缩略名，最后按缩略名归类动作：
 * chatter → 更新了杂谈，moments → 发布了说说，其余 → 更新了文章。
 */
function sg_activity_feed(int $limit = 12): array
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select([
                'table.contents.cid'     => 'cid',
                'table.contents.title'   => 'title',
                'table.contents.created' => 'created',
                'table.contents.slug'    => 'slug',
            ])
                ->from('table.contents')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->limit(max(1, $limit))
        );
    } catch (\Throwable $e) {
        return [];
    }

    if (!$rows) {
        return [];
    }

    $cids = array_map(static fn($row) => (int) ($row['cid'] ?? 0), $rows);

    // cid => 分类缩略名（一篇文章可能挂多个分类，取第一个命中我们关心的）
    $slugs = [];
    try {
        $db = \Typecho\Db::get();
        $cats = $db->fetchAll(
            $db->select([
                'table.relationships.cid' => 'cid',
                'table.metas.slug'        => 'slug',
            ])
                ->from('table.relationships')
                ->join('table.metas', 'table.metas.mid = table.relationships.mid')
                ->where('table.metas.type = ?', 'category')
                ->where('table.relationships.cid IN ?', $cids)
        );
        foreach ($cats as $cat) {
            $cid = (int) ($cat['cid'] ?? 0);
            $slug = (string) ($cat['slug'] ?? '');
            if ($cid > 0 && $slug !== '' && !isset($slugs[$cid])) {
                $slugs[$cid] = $slug;
            }
        }
    } catch (\Throwable $e) {
        $slugs = [];
    }

    $options = sg_options();
    $home = is_object($options) ? rtrim((string) $options->siteUrl, '/') : '';

    $feed = [];
    foreach ($rows as $row) {
        $cid = (int) ($row['cid'] ?? 0);
        $ts = (int) ($row['created'] ?? 0);
        if ($cid <= 0 || $ts <= 0) {
            continue;
        }
        $slug = $slugs[$cid] ?? '';
        if ($slug === 'chatter') {
            $action = '更新了';
            $kind = '杂谈';
        } elseif ($slug === 'moments') {
            $action = '发布了';
            $kind = '说说';
        } else {
            $action = '更新了';
            $kind = '文章';
        }
        $feed[] = [
            'cid'    => $cid,
            'title'  => (string) ($row['title'] ?? ''),
            'date'   => date('Y-m-d H:i', $ts),
            'time'   => $ts,
            'action' => $action,
            'kind'   => $kind,
            'url'    => $home . '/index.php/archives/' . $cid . '/',
        ];
    }

    return $feed;
}

/**
 * 首页宽幅卡内容（8 列上那张大海报）。
 *
 * 三种来源，后台「首页宽幅卡」下拉切换：
 *   album  光影画廊（默认，对齐源站 app/page.tsx 的 latestAlbum 海报）
 *   post   最新一篇带封面的文章
 *   custom 后台「宽幅卡 JSON」手填（title / desc / cover / url / kicker）
 * 任何一层取不到都往下回退，最后兜底占位图，保证这张卡永远不空。
 */
function sg_home_poster($archive = null): array
{
    $mode = sg_opt('sgPosterMode', 'album');
    $fallback = [
        'title'  => '光影画廊',
        'desc'   => '定格时间，封存每一次心跳',
        'link'   => sg_page_url('photowall'),
        'cover'  => sg_placeholder(1015, 1200, 800),
        'kicker' => 'PHOTO WALL',
    ];

    if ($mode === 'custom') {
        $raw = sg_opt('sgPoster', '');
        $data = $raw !== '' ? json_decode($raw, true) : null;
        if (is_array($data) && $data) {
            // 允许写成对象，也允许写成只有一项的数组
            if (isset($data[0]) && is_array($data[0])) {
                $data = $data[0];
            }
            return [
                'title'  => (string) ($data['title'] ?? $fallback['title']),
                'desc'   => (string) ($data['desc'] ?? $data['description'] ?? $fallback['desc']),
                'link'   => sg_media((string) ($data['url'] ?? $data['link'] ?? $fallback['link']), $fallback['link']),
                'cover'  => sg_media((string) ($data['cover'] ?? ''), $fallback['cover']),
                'kicker' => (string) ($data['kicker'] ?? 'CUSTOM'),
            ];
        }
    }

    if ($mode === 'post' && $archive) {
        try {
            $archive->widget('Widget_Contents_Post_Recent', 'pageSize=1')->to($recent);
            while ($recent->next()) {
                return [
                    'title'  => (string) $recent->title,
                    'desc'   => sg_excerpt($recent, 44),
                    'link'   => (string) $recent->permalink,
                    'cover'  => sg_cover($recent),
                    'kicker' => sg_first_category($recent, '最新文章'),
                ];
            }
        } catch (\Throwable $e) {
            // 落到相册
        }
    }

    $albums = sg_json('sgAlbums', 'albums', []);
    $album = (is_array($albums) && isset($albums[0]) && is_array($albums[0])) ? $albums[0] : [];
    if ($album) {
        $photos = is_array($album['photos'] ?? null) ? array_values($album['photos']) : [];
        $cover = trim((string) ($album['cover'] ?? ''));
        if ($cover === '' && isset($photos[0]['url'])) {
            $cover = (string) $photos[0]['url'];
        }
        return [
            'title'  => (string) ($album['title'] ?? $fallback['title']),
            'desc'   => (string) ($album['description'] ?? $fallback['desc']),
            'link'   => sg_page_url('photowall'),
            'cover'  => sg_media($cover, $fallback['cover']),
            'kicker' => 'PHOTO WALL',
        ];
    }

    return $fallback;
}

/**
 * 歌单：后台 JSON → 后台网易云歌曲 ID → dist/data/playlist.json。
 * 三层回退保证「只要配了任意一层，播放器就有声音」。
 */
function sg_playlist(): array
{
    $list = sg_json('sgPlaylist', 'playlist', []);
    $list = array_values(array_filter($list, static fn($item) => is_array($item)));
    if ($list) {
        return $list;
    }

    $ids = sg_opt('sgPlaylistIds', '');
    if (trim($ids) !== '') {
        $remote = sg_netease_playlist($ids);
        if ($remote) {
            return $remote;
        }
    }

    return [];
}

/**
 * 网易云歌曲 ID → 歌单数组（对齐源站 app/api/music/route.ts 的做法）。
 *
 * 源站跑在 Node 上，用 route handler 代理请求绕开浏览器跨域；Typecho 没有
 * 路由层，所以把同样的两步（song/detail + song/lyric）搬到 PHP 侧，
 * 结果按「ID 列表 + 6 小时」缓存到系统临时目录，避免每次打开页面都打外网。
 *
 * 注意：`outer/url` 是 302 跳转，能否播放取决于网易云是否放行（常常 403）。
 * 这里只负责把数据补齐，真正的稳定播放仍建议填可直链的音频地址。
 *
 * @param string $ids 逗号或换行分隔的歌曲 ID
 */
function sg_netease_playlist(string $ids): array
{
    $list = preg_split('/[\s,]+/', trim($ids));
    $list = array_values(array_filter(array_map('strval', $list), static fn($id) => preg_match('/^\d{3,}$/', $id) === 1));
    if (!$list) {
        return [];
    }

    $cacheKey = 'soraglass-netease-' . md5(implode(',', $list)) . '.json';
    $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . $cacheKey;
    if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 21600) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && $cached) {
            return $cached;
        }
    }

    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
    $get = static function (string $url) use ($ua): string {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => $ua,
                CURLOPT_REFERER        => 'https://music.163.com/',
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            return is_string($body) ? $body : '';
        }
        $ctx = stream_context_create([
            'http' => ['timeout' => 6, 'header' => "User-Agent: {$ua}\r\nReferer: https://music.163.com/\r\n"],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : '';
    };

    $out = [];
    foreach ($list as $id) {
        $detail = json_decode($get('https://music.163.com/api/song/detail/?id=' . $id . '&ids=[' . $id . ']'), true);
        $song = is_array($detail) ? ($detail['songs'][0] ?? null) : null;
        if (!is_array($song)) {
            continue;
        }

        $lyric = json_decode($get('https://music.163.com/api/song/lyric?id=' . $id . '&lv=-1&kv=-1&tv=-1'), true);
        $lrc = is_array($lyric) ? (string) ($lyric['lrc']['lyric'] ?? '') : '';

        $out[] = [
            'id'     => (string) $id,
            'title'  => (string) ($song['name'] ?? '未命名'),
            'artist' => (string) ($song['artists'][0]['name'] ?? '未知歌手'),
            'cover'  => (string) ($song['album']['picUrl'] ?? ''),
            'url'    => 'https://music.163.com/song/media/outer/url?id=' . $id . '.mp3',
            'lrc'    => $lrc,
        ];
    }

    if ($out) {
        @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    return $out;
}
