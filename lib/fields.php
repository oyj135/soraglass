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

/**
 * 背景图组：后台一个文本框里一行一个地址。
 *
 * 2026-11-13 第二十二轮新增：AI 日报卡片的背景从「一张静态图」升级成「多张轮播」，
 * 用户不需要填 JSON 数组，按行贴地址最不容易出错。
 * 单行（也就是这个设置项的历史值）解析出来就是一张图，行为与改动前完全一致。
 *
 * @param string $value 原始设置值，换行或逗号分隔
 * @param int    $limit 最多保留几张，避免一屏几百张图把首页拖死
 * @return string[] 已经过 sg_bg_url() 归一化、去重后的地址列表
 */
function sg_bg_list(string $value, int $limit = 12): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    $out = [];
    foreach (preg_split('/[\r\n,]+/', $value) ?: [] as $part) {
        $url = sg_bg_url((string) $part);
        if ($url === '' || in_array($url, $out, true)) {
            continue;
        }
        $out[] = $url;
        if (count($out) >= $limit) {
            break;
        }
    }
    return $out;
}

/** 站内绝对地址：把 `usr/uploads/...` 这类相对路径补成能从任何 URL 打开的形式 */
function sg_site_url(string $path = ''): string
{
    $options = sg_options();
    $root = is_object($options) ? rtrim((string) $options->siteUrl, '/') : '';
    return $root . '/' . ltrim($path, '/');
}

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
 * 最近动态：文章与碎语混排，供关于页「研究动态」的时间线使用。
 *
 * 一次查库搞定，不逐条问分类：先取最近 N 篇已发布 post，再用一次
 * relationships + metas 连表拿到各自的分类缩略名，最后按缩略名归类动作：
 * notes → 记了一条碎语，其余 → 更新了文章。
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
        if ($slug === 'notes') {
            $action = '记录了';
            $kind = '碎语';
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
            /* 碎语不进「最新文章」海报（2026-11-13）：多取碎语条数再跳过，
               否则最新一篇是碎语时，首页宽幅卡会顶着碎语标题。 */
            $archive->widget('Widget_Contents_Post_Recent', 'pageSize=' . (1 + count(sg_note_cids())))->to($recent);
            while ($recent->next()) {
                if (sg_is_note((int) $recent->cid)) {
                    continue;
                }
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

    /* 相册不再是后台 JSON，而是扫描 usr/uploads 自动分组（见 sg_albums()） */
    $albums = sg_albums();
    $album = isset($albums[0]) && is_array($albums[0]) ? $albums[0] : [];
    if ($album) {
        $photos = is_array($album['photos'] ?? null) ? array_values($album['photos']) : [];
        /* 首页宽幅卡只有 4:1 的窄条，用缩略图就够（OSS 站省流量） */
        $cover = trim((string) ($album['coverThumb'] ?? $album['cover'] ?? ''));
        if ($cover === '' && isset($photos[0]['thumb'])) {
            $cover = (string) $photos[0]['thumb'];
        }
        return [
            'title'  => (string) ($album['title'] ?? $fallback['title']),
            /* 自动成册的相册没有手写简介，用张数当副标题；手写 JSON 时代填的简介仍然优先 */
            'desc'   => (string) ($album['description'] ?? '') !== ''
                ? (string) $album['description']
                : (count($photos) > 0 ? count($photos) . ' 张照片' : $fallback['desc']),
            'link'   => sg_page_url('photowall'),
            'cover'  => sg_media($cover, $fallback['cover']),
            'kicker' => 'PHOTO WALL',
        ];
    }

    return $fallback;
}

/**
 * 歌单数据源 —— 只有一套配置：后台「歌单」文本域 `sgPlaylist`。
 *
 * 填什么就是什么，三种写法：
 *   ①留空                    → `dist/data/playlist.json`（主题自带的 CC-BY 音频）
 *   ②以 `[` 开头的 JSON 数组 → 直接当歌单用（自己挑的曲目）
 *   ③以 http(s) 开头的地址   → 当作 Meting 兼容接口整条请求
 *     例：`https://api.injahow.cn/meting/?server=netease&type=playlist&id=2619366284`
 *
 * 之所以收成一层：以前「后台 JSON / Meting 四件套 / 网易云 ID」并列，
 * 谁生效取决于留空顺序，站长改完不知道卡在哪 —— 歌单不更新多半就是这么来的。
 * 现在只有一个输入框，命中结果由 `sg_playlist_source()` 显示在音乐页。
 */
function sg_playlist(): array
{
    $raw = trim((string) sg_opt('sgPlaylist', ''));

    /* 旧版「Meting API 地址 + 平台 + 类型 + ID」四件套的兼容：
       这四个字段已经下线，但老站的 options 里可能还留着值。直接把它们拼成一条
       地址继续用，免得升级主题后歌单突然变回自带音频。站长把完整地址填进
       「歌单」后，这一支自然失效。 */
    if ($raw === '') {
        $legacyApi = trim((string) sg_opt('sgMetingApi', ''));
        $legacyId = trim((string) sg_opt('sgMetingId', ''));
        if ($legacyApi !== '' && $legacyId !== '' && preg_match('#^https?://#i', $legacyApi)) {
            $raw = rtrim($legacyApi, '/') . '/?' . http_build_query([
                'server' => trim((string) sg_opt('sgMetingServer', 'netease')) ?: 'netease',
                'type'   => trim((string) sg_opt('sgMetingType', 'playlist')) ?: 'playlist',
                'id'     => $legacyId,
            ]);
            $GLOBALS['SG_PLAYLIST_LEGACY'] = true;
        }
    }

    /* ③ 远程接口：整条 URL 透传 */
    if ($raw !== '' && preg_match('#^https?://#i', $raw)) {
        $remote = sg_meting_playlist($raw);
        if ($remote) {
            $GLOBALS['SG_PLAYLIST_SOURCE'] = 'Meting · ' . sg_meting_label($raw)
                . (!empty($GLOBALS['SG_PLAYLIST_LEGACY']) ? '（旧版设置，建议把完整地址填进「歌单」）' : '');
            return sg_playlist_cap($remote);
        }
    }

    /* ② 手填 JSON */
    if ($raw !== '' && !preg_match('#^https?://#i', $raw)) {
        $list = json_decode($raw, true);
        $list = is_array($list) ? array_values(array_filter($list, static fn($item) => is_array($item))) : [];
        if ($list) {
            $GLOBALS['SG_PLAYLIST_SOURCE'] = '后台「歌单 JSON」';
            return sg_playlist_media($list);
        }
        $GLOBALS['SG_PLAYLIST_ERROR'] = '「歌单」里填的内容既不是 JSON 也不是 http(s) 地址，已回退到主题自带音频。';
    }

    /* ① 随包音频 */
    $GLOBALS['SG_PLAYLIST_SOURCE'] = '主题自带音频（dist/data/playlist.json）';
    $fallback = sg_json('sgPlaylist', 'playlist', []);
    $fallback = array_values(array_filter($fallback, static fn($item) => is_array($item)));

    return $fallback ? sg_playlist_media($fallback) : [];
}

/**
 * 当前生效的歌单来源，给模板显示用。
 */
function sg_playlist_source(): string
{
    return (string) ($GLOBALS['SG_PLAYLIST_SOURCE'] ?? '主题自带音频（dist/data/playlist.json）');
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
 * 这里按后台「歌单上限」截断（默认 50，填 0 不截断），手工填的歌单 JSON 不受影响
 * —— 那份是站长自己挑过的。
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
 * 首页「碎语」轮播的一张。
 *
 * 数据来自 sg_notes()（「碎语」分类下的文章），没有封面时退到主题自带渐变图，
 * 避免出现一条空白卡片。
 */
function sg_note_slide(array $note): string
{
    $active = !empty($note['active']);
    $title = (string) ($note['title'] ?? '');
    $text = (string) ($note['text'] ?? '');
    $tag = (string) ($note['tag'] ?? '碎语');
    $date = (string) ($note['date'] ?? '');
    $cover = (string) ($note['cover'] ?? '');
    if ($cover === '') {
        $cover = sg_theme_url('dist/img/cover-default.svg');
    }
    $href = (string) ($note['url'] ?? '');
    if ($href === '') {
        $href = sg_page_url('notes');
    }
    // 卡片里只放一句：正文太长会把轮播撑开
    if ($text !== '') {
        $text = mb_substr($text, 0, 64, 'UTF-8') . (mb_strlen($text, 'UTF-8') > 64 ? '...' : '');
    }
    if ($title === '') {
        $title = $tag;
    }

    return '<article class="sg-chatter-slide' . ($active ? ' is-active' : '') . '" data-sg-slide>'
        . '<img src="' . sg_e($cover) . '" alt=""' . ($active ? '' : ' loading="lazy"') . '>'
        . '<span class="sg-veil"></span>'
        . '<a class="sg-chatter-body" href="' . sg_e($href) . '">'
        . '<span class="sg-chatter-meta">'
        . '<span class="sg-chip--tag">' . sg_e($tag) . '</span>'
        . ($date !== '' ? '<time>' . sg_e($date) . '</time>' : '')
        . '</span>'
        . '<h3>' . sg_e($title) . '</h3>'
        . ($text !== '' ? '<p>' . sg_e($text) . '</p>' : '')
        . '</a>'
        . '</article>';
}

/**
 * 碎语：直接读「碎语」分类下的文章（约定 slug = `notes`）。
 *
 * 2026-11-13 第二十轮按用户要求改的：碎语回归「写文章」这一条路 ——
 * 后台写一篇短文章扔进「碎语」分类，前台首页轮播与碎语页都会自动出现，
 * 不再有「后台填 JSON」和「写文章」两套并存的维护方式。
 *
 * 字段映射：时间 → created、标签 → 第一个标签（没有就用分类名）、
 * 标题 → 文章标题、正文 → 自定义字段 description（没填则截正文）、
 * 封面 → 自定义字段 cover、链接 → 文章永久地址。
 */
function sg_notes(int $limit = 60): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = sg_notes_query(60);
    }
    return $limit > 0 ? array_slice($cache, 0, $limit) : $cache;
}

/** 碎语分类的固定缩略名；后台建分类时用这个名字即可 */
function sg_notes_slug(): string
{
    return 'notes';
}

/**
 * 碎语分类下的全部文章 cid（静态缓存，一次请求只查一次）。
 *
 * 2026-11-13：碎语与普通文章要求「完全隔离」—— 知识档案、首页封面轮播、
 * 侧栏最近文章、归档页都要把碎语剔出去，所以单独提供一份 id 集合，
 * 调用方用 sg_is_note() 过滤即可。不复用 sg_notes()：那份带 LIMIT、
 * 封面与摘要计算，过滤时只需要 id。
 */
function sg_note_cids(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $db = \Typecho\Db::get();
        $cat = $db->fetchRow($db->select('mid')->from('table.metas')
            ->where('type = ?', 'category')->where('slug = ?', sg_notes_slug())->limit(1));
        if ($cat && !empty($cat['mid'])) {
            foreach ($db->fetchAll($db->select('cid')->from('table.relationships')
                ->where('mid = ?', (int) $cat['mid'])) as $row) {
                $cid = (int) ($row['cid'] ?? 0);
                if ($cid > 0) {
                    $cache[] = $cid;
                }
            }
        }
    } catch (\Throwable $e) {
        $cache = [];
    }

    return $cache;
}

/** 这篇内容是不是碎语（挂在碎语分类下）。列表模板据此把碎语剔出通用列表 */
function sg_is_note(int $cid): bool
{
    return $cid > 0 && in_array($cid, sg_note_cids(), true);
}

/** 碎语页副标题里要显示的条数（与首页轮播共用同一份数据） */
function sg_notes_count(): int
{
    return count(sg_notes(0));
}

/**
 * 真正取数的地方。查不到分类 / 查库失败一律返回空数组，绝不让页面 500。
 */
function sg_notes_query(int $limit): array
{
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        $options = sg_options();

        $cat = $db->fetchRow($db->select('mid', 'name')->from('table.metas')
            ->where('type = ?', 'category')->where('slug = ?', sg_notes_slug())->limit(1));
        if (!$cat || empty($cat['mid'])) {
            return [];
        }
        $mid = (int) $cat['mid'];
        $catName = (string) ($cat['name'] ?? '碎语');

        $rels = $db->fetchAll($db->select('cid')->from('table.relationships')->where('mid = ?', $mid));
        $cids = [];
        foreach ($rels as $rel) {
            $cid = (int) ($rel['cid'] ?? 0);
            if ($cid > 0) {
                $cids[] = $cid;
            }
        }
        if (!$cids) {
            return [];
        }
        $in = implode(',', $cids);

        $rows = $db->fetchAll("SELECT cid, title, slug, created, text FROM {$p}contents"
            . " WHERE cid IN ({$in}) AND type = 'post' AND status = 'publish'"
            . " ORDER BY created DESC LIMIT " . max(1, $limit));
        if (!$rows) {
            return [];
        }

        $fields = [];
        foreach ($db->fetchAll("SELECT cid, name, str_value FROM {$p}fields WHERE cid IN ({$in})") as $f) {
            $cid = (int) ($f['cid'] ?? 0);
            if ($cid > 0 && (string) ($f['str_value'] ?? '') !== '') {
                $fields[$cid][(string) $f['name']] = (string) $f['str_value'];
            }
        }

        $tags = [];
        foreach ($db->fetchAll("SELECT r.cid, m.name FROM {$p}relationships r"
            . " INNER JOIN {$p}metas m ON m.mid = r.mid"
            . " WHERE r.cid IN ({$in}) AND m.type = 'tag'") as $t) {
            $cid = (int) ($t['cid'] ?? 0);
            if ($cid > 0 && !isset($tags[$cid])) {
                $tags[$cid] = (string) ($t['name'] ?? '');
            }
        }

        $tz = 0;
        try {
            $tz = (int) \Widget\Options::alloc()->timezone;
        } catch (\Throwable $e) {
            $tz = 0;
        }

        $out = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            $text = (string) ($row['text'] ?? '');

            /* Markdown 文章：库里存的是 `<!--markdown-->` + 源码（前缀判断与
               Widget\Base\Contents::___isMarkdown() 一致）。碎语卡不走 $this->content()，
               这里必须自己转一遍 HTML —— 否则 `![](url)` 既抽不出 <img>，
               又会被 strip_tags 原样留成一段地址文字显示在卡片上。
               用纯 HTML 写的碎语没有这个前缀，走原路径，行为不变。 */
            if (strncmp($text, '<!--markdown-->', 15) === 0) {
                $text = \Utils\Markdown::convert(substr($text, 15));
            }

            $own = $fields[$cid] ?? [];

            $desc = trim((string) ($own['description'] ?? ''));
            if ($desc === '') {
                $desc = sg_notes_excerpt($text);
            }
            $cover = trim((string) ($own['cover'] ?? ''));

            /* 图集：正文里所有 <img>，最多 9 张（九宫格上限）。
               碎语是「一句 + 几张图」的记录，图片本身就是内容的一部分。 */
            $images = [];
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $text, $mm)) {
                foreach ($mm[1] as $src) {
                    $src = trim((string) $src);
                    if ($src === '' || in_array($src, $images, true)) {
                        continue;
                    }
                    $images[] = $src;
                    if (count($images) >= 9) {
                        break;
                    }
                }
            }
            if ($cover === '' && $images) {
                $cover = $images[0];
            }

            /* Router::url 只给路径（/archives/40/），要自己跟站点入口拼：
               开了伪静态时 index 是站点根，关掉时带 /index.php，混用站点才不会 404。 */
            $path = (string) \Typecho\Router::url('post', [
                'cid'     => $cid,
                'slug'    => (string) ($row['slug'] ?? ''),
                'created' => (int) ($row['created'] ?? 0),
                'type'    => 'post',
            ]);
            if ($path === '' || $path === '#') {
                $path = '/archives/' . $cid . '/';
            }
            $url = (string) \Typecho\Common::url($path, $options->index);

            $ts = (int) ($row['created'] ?? 0) + $tz;
            $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

            $out[] = [
                'cid'      => $cid,
                'ts'       => $ts,
                'date'     => date('Y.m.d', $ts),
                'time'     => date('H:i', $ts),
                'group'    => date('Y-m', $ts),
                'groupLabel' => date('Y 年 n 月', $ts),
                'tag'      => ($tags[$cid] ?? '') !== '' ? $tags[$cid] : $catName,
                'title'    => (string) ($row['title'] ?? ''),
                'text'     => $desc,
                'body'     => $plain,
                'images'   => array_map(static fn(string $src): string => sg_media($src), $images),
                'mood'     => trim((string) ($own['mood'] ?? '')),
                'location' => trim((string) ($own['location'] ?? '')),
                'cover'    => sg_media($cover),
                'url'      => $url,
            ];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 相册：**扫描 `usr/uploads/YYYY/MM/` 自动成册**，不再由后台 JSON 维护。
 *
 * 2026-11-13 第二十轮按用户要求改的：照片本来就该由站长在「附件」里传，
 * 再手抄一遍 JSON 纯属重复劳动，还容易写错路径。
 * 规则：
 *  - 后台传图默认落在 `usr/uploads/2026/11/xxx.jpg`，按「年/月」两段分组；
 *  - 不是这种结构的图（比如直接扔在 `usr/uploads/` 根下）归到「未归类」；
 *  - 相册按时间倒序，同一相册内按文件名升序（上传顺序基本等于拍摄顺序）；
 *  - 没有图注来源，`caption` 一律给空串，前台不会渲染空图注气泡。
 *
 * 返回值与旧 JSON 结构保持一致（id/title/description/date/cover/photos），
 * 所以 page-photowall.php、sg_home_poster()、gallery.ts 都不用改契约。
 */
function sg_albums(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    /* 第二十三轮：照片存在 OSS 的站，直接在「相册图片」里一行一个贴链接，
       这里按链接里的年月自动成册。留空才退回扫 `usr/uploads`。 */
    $links = trim((string) sg_opt('sgOssPhotos', ''));
    if ($links !== '') {
        $remote = sg_oss_photos($links);
        if ($remote) {
            return $cache = $remote;
        }
    }

    $root = defined('__TYPECHO_ROOT_DIR__') ? __TYPECHO_ROOT_DIR__ : dirname(__DIR__, 2);
    $base = rtrim(str_replace('\\', '/', $root), '/') . '/usr/uploads';
    if (!is_dir($base)) {
        return $cache = [];
    }

    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'svg'];
    $groups = [];

    try {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $allowed, true)) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            $rel = ltrim(substr($path, strlen($base)), '/');
            if ($rel === '') {
                continue;
            }
            $parts = explode('/', $rel);
            $key = 'misc';
            $title = '未归类';
            $date = '';
            if (count($parts) >= 3
                && preg_match('/^\d{4}$/', $parts[0]) === 1
                && preg_match('/^\d{2}$/', $parts[1]) === 1) {
                $key = $parts[0] . '-' . $parts[1];
                $title = $parts[0] . ' 年 ' . (int) $parts[1] . ' 月';
                $date = $parts[0] . '.' . $parts[1];
            }
            if (!isset($groups[$key])) {
                $groups[$key] = ['title' => $title, 'date' => $date, 'photos' => []];
            }
            $groups[$key]['photos'][] = [
                'url'     => sg_site_url('usr/uploads/' . $rel),
                'thumb'   => sg_site_url('usr/uploads/' . $rel),
                'caption' => '',
                'name'    => $rel,
            ];
        }
    } catch (\Throwable $e) {
        return $cache = [];
    }

    $albums = [];
    foreach ($groups as $key => $group) {
        $photos = $group['photos'];
        sort($photos);
        if (!$photos) {
            continue;
        }
        $count = count($photos);
        $albums[] = [
            'id'          => (string) $key,
            'title'       => (string) $group['title'],
            'date'        => (string) $group['date'],
            /* 张数模板里已经单独渲染（「2026.11 · 2 张照片」），
               这里再写一遍会重复成「2 张照片 / 2 张照片」 */
            'description' => '',
            'cover'       => (string) $photos[0]['url'],
            'coverThumb'  => (string) ($photos[0]['thumb'] ?? $photos[0]['url']),
            'photos'      => $photos,
        ];
    }

    usort($albums, static function (array $a, array $b): int {
        if ($a['id'] === 'misc') {
            return 1;
        }
        if ($b['id'] === 'misc') {
            return -1;
        }
        return strcmp($b['id'], $a['id']);
    });

    return $cache = $albums;
}

/** OSS 相册清单的失败原因（后台诊断行用） */
function sg_oss_error(): string
{
    return (string) ($GLOBALS['SG_OSS_ERROR'] ?? '');
}

/** 记录 OSS 相册的失败原因 */
function sg_oss_fail(string $message): void
{
    if (sg_oss_error() === '') {
        $GLOBALS['SG_OSS_ERROR'] = $message;
    }
}

/**
 * 图片处理后缀：OSS 自带 `x-oss-process`，列表里按宽度压一版并转 webp，省流量也快。
 * 留空则不处理（原图直出）；已经是完整 `x-oss-process` 的链接不会重复拼。
 */
function sg_oss_suffix(): string
{
    $suffix = trim((string) sg_opt('sgOssSuffix', 'x-oss-process=image/resize,w_1200/format,webp/quality,q_82'));
    if ($suffix === '') {
        return '';
    }
    if ($suffix[0] !== '?') {
        $suffix = '?' . ltrim($suffix, '&');
    }
    return $suffix;
}

/** 给一条 OSS 链接套上缩略图参数；站内相对路径与非 OSS 链接原样返回 */
function sg_oss_thumb(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
        return $url;
    }
    $suffix = sg_oss_suffix();
    if ($suffix === '' || stripos($url, 'x-oss-process') !== false) {
        return $url;
    }
    return $url . (strpos($url, '?') === false ? $suffix : '&' . ltrim($suffix, '?'));
}

/**
 * 把「一行一个 OSS 链接」的相册配置解析成相册数组。
 *
 * 每行格式：`https://….jpg` 或 `https://….jpg | 一句说明`（# 开头为注释）。
 * 分组规则：从链接路径里找年份与月份（`2026/11`、`2026-11`、`2026_11`），
 * 找到就归到「2026 年 11 月」，找不到归到「未归类」。相册按时间倒序，未归类永远最后。
 *
 * 产出结构与扫附件那条路完全一致（page-photowall.php / sg_home_poster() 都不用改）：
 *   id / title / date / description / cover / coverThumb / photos[{url,thumb,caption,name}]
 */
function sg_oss_photos(string $links): array
{
    $lines = preg_split('/\r\n|\r|\n/', $links) ?: [];
    $groups = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        // 「链接 | 说明」
        $caption = '';
        $pos = strpos($line, '|');
        if ($pos !== false) {
            $caption = trim(substr($line, $pos + 1));
            $line = trim(substr($line, 0, $pos));
        }
        if (preg_match('#^(https?://|/)#i', $line) !== 1) {
            continue;
        }

        $url = sg_media($line);
        $key = 'misc';
        $title = '未归类';
        $date = '';
        if (preg_match('#(20\d{2})[-_/](\d{1,2})(?:[-_/]|$)#', $line, $m) === 1) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if ($month >= 1 && $month <= 12) {
                $key = sprintf('%04d-%02d', $year, $month);
                $title = $year . ' 年 ' . $month . ' 月';
                $date = sprintf('%04d.%02d', $year, $month);
            }
        }

        if (!isset($groups[$key])) {
            $groups[$key] = [
                'id'          => $key,
                'title'       => $title,
                'date'        => $date,
                'description' => '',
                'photos'      => [],
            ];
        }
        $groups[$key]['photos'][] = [
            'url'     => $url,
            'thumb'   => sg_media(sg_oss_thumb($url)),
            'caption' => $caption,
            'name'    => (string) basename((string) parse_url($line, PHP_URL_PATH)),
        ];
    }

    if (!$groups) {
        return [];
    }

    // 时间倒序，未归类永远最后
    $keys = array_keys($groups);
    usort($keys, static function (string $a, string $b): int {
        if ($a === 'misc') {
            return 1;
        }
        if ($b === 'misc') {
            return -1;
        }
        return strcmp($b, $a);
    });

    $albums = [];
    foreach ($keys as $key) {
        $album = $groups[$key];
        $first = $album['photos'][0];
        $album['cover'] = $first['url'];
        $album['coverThumb'] = $first['thumb'];
        /* 张数由模板用 count(photos) 统一渲染，这里留空，避免出现两行「N 张照片」 */
        $album['description'] = '';
        $albums[] = $album;
    }
    return $albums;
}

/** 去掉短代码/标签后截一段纯文本，用于「没填摘要」的碎语卡片 */
function sg_notes_excerpt(string $html): string
{
    $text = preg_replace('/<!--.*?-->/s', ' ', $html) ?? $html;
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > 90) {
        return mb_substr($text, 0, 90, 'UTF-8') . '…';
    }
    return $text;
}

/**
 * 歌单里的 url / cover 归一化。
 *
 * 自带音源放在 `public/audio/`，打包后在 `dist/audio/`，JSON 里只写相对路径；
 * 不补主题前缀的话浏览器会去站点根找 `/dist/audio/...`，必然 404。
 * 外链（网易云封面、用户自己的图床）原样返回，见 sg_media() 的解析规则。
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
 * 一个极简 GET：curl 优先，失败退 file_get_contents。
 *
 * 「失败」真的会发生：有些 PHP 构建没配 CA 包，curl 直接报
 * `errno 60 unable to get local issuer certificate`，而 file_get_contents
 * 走的是另一套 CA 配置、常常还能通 —— 所以 curl 拿不到内容时必须再试一次，
 * 否则站长会看到「接口没数据」，实际只是证书链没配。
 *
 * 只允许 http/https（含重定向），避免配置里出现 file:// 之类的协议被 curl 跟着跑。
 *
 * @param array<int,string> $headers 形如 `Accept: application/json`
 */
function sg_http_get(string $url, array $headers = [], int $timeout = 6): string
{
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
    $headers = array_merge(['User-Agent: ' . $ua], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (defined('CURLOPT_PROTOCOLS')) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        /* 只有 cainfo 落在 open_basedir 允许的目录里才去看它。
           宝塔这类环境 curl.cainfo 指向 /etc/pki/tls/certs/ca-bundle.crt，
           而 open_basedir 只有站点目录 + /tmp，直接 is_file() 会在页面顶部喷
           「open_basedir restriction in effect」警告（首页/音乐页都会），
           所以先比对目录前缀，必要时再用 @ 兜一层。 */
        $cainfo = (string) ini_get('curl.cainfo');
        if ($cainfo !== '') {
            $allowed = trim((string) ini_get('open_basedir'));
            $ok = $allowed === '';
            if (!$ok) {
                foreach (explode(PATH_SEPARATOR, $allowed) as $dir) {
                    $dir = rtrim(trim($dir), '/\\');
                    if ($dir !== '' && strpos($cainfo, $dir . DIRECTORY_SEPARATOR) === 0) {
                        $ok = true;
                        break;
                    }
                }
            }
            if ($ok && @is_file($cainfo)) {
                $opts[CURLOPT_CAINFO] = $cainfo;
            }
        }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $failed = $body === false || $body === '';
        curl_close($ch);
        if (!$failed) {
            return (string) $body;
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout'         => $timeout,
            'header'          => implode("\r\n", $headers) . "\r\n",
            'follow_location' => 1,
            'max_redirects'   => 3,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

/**
 * POST JSON：AI 接口用。与 sg_http_get() 同样的「curl 失败退 fgc」策略，
 * 同样只放行 http/https，同样按 open_basedir 决定要不要看 curl.cainfo。
 *
 * @param array<string,mixed> $payload
 * @param array<int,string>   $headers
 */
function sg_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 20): string
{
    if (preg_match('#^https?://#i', $url) !== 1) {
        return '';
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) {
        return '';
    }

    $ua = 'soraglass/1.0 (+https://github.com/oyj135/soraglass)';
    $headers = array_merge([
        'User-Agent: ' . $ua,
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($body),
    ], $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 2,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (defined('CURLOPT_PROTOCOLS')) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        $cainfo = (string) ini_get('curl.cainfo');
        if ($cainfo !== '') {
            $allowed = trim((string) ini_get('open_basedir'));
            $ok = $allowed === '';
            if (!$ok) {
                foreach (explode(PATH_SEPARATOR, $allowed) as $dir) {
                    $dir = rtrim(trim($dir), '/\\');
                    if ($dir !== '' && strpos($cainfo, $dir . DIRECTORY_SEPARATOR) === 0) {
                        $ok = true;
                        break;
                    }
                }
            }
            if ($ok && @is_file($cainfo)) {
                $opts[CURLOPT_CAINFO] = $cainfo;
            }
        }
        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (is_string($result) && $result !== '' && $status >= 200 && $status < 300) {
            return $result;
        }
        if (is_string($result) && $result !== '' && $status >= 400) {
            /* 4xx/5xx 的响应体常常就是错误说明，交给上层解析比丢一句「没响应」有用 */
            return $result;
        }
    }

    $ctx = stream_context_create([
        'http' => [
            'method'          => 'POST',
            'timeout'         => $timeout,
            'header'          => implode("\r\n", $headers) . "\r\n",
            'content'         => $body,
            'ignore_errors'   => true,
            'follow_location' => 1,
            'max_redirects'   => 2,
        ],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    return is_string($result) ? $result : '';
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
 * 缓存 10 分钟。**登录状态下的管理员不加缓存**：站长在后台换歌单、改配置后刷新页面
 * 就该看到新内容 —— 之前固定缓存 3 小时，改完以为「没生效」，是「歌单不更新」的元凶。
 * 前台访客仍然吃缓存，避免每次访问都打外网。
 */
function sg_meting_playlist(string $endpoint): array
{
    $endpoint = trim($endpoint);
    if ($endpoint === '' || !preg_match('#^https?://#i', $endpoint)) {
        return [];
    }

    $bypass = sg_user_is_admin();
    $cacheFile = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
        . 'soraglass-meting-' . md5($endpoint) . '.json';

    if (!$bypass && is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 600) {
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

    @file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $out;
}