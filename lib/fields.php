<?php
/**
 * 文章自定义字段读取。
 * 源项目 frontmatter 的 cover / description 在此映射为 Typecho 自定义字段。
 */
function sg_field($archive, string $key, string $default = ''): string
{
    try {
        $fields = $archive->fields ?? null;
        $value = $fields ? ($fields->{$key} ?? null) : null;
        return (is_string($value) && $value !== '') ? $value : $default;
    } catch (\Throwable $e) {
        return $default;
    }
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
 * 背景图组：日间 / 夜间两路，各自一行一个地址（后台 sgBgLight / sgBgDark）。
 *
 * 2026-11-17 第十二轮：移除旧的通用壁纸设置 sgBg 与单页覆盖字段 ——
 * 日间 / 夜间两路已覆盖全部场景，不再需要第三条配置；
 * 两路都留空时回退到主题内置渐变壁纸 dist/img/bg-1.svg。
 *
 * @return array{light:string[],dark:string[]} 已经过 sg_bg_url() 归一化、去重后的地址列表
 */
function sg_wallpapers(): array
{
    $fallback = sg_theme_url('dist/img/bg-1.svg');
    return ['light' => sg_bg_list((string) sg_opt('sgBgLight', '')) ?: [$fallback],
        'dark' => sg_bg_list((string) sg_opt('sgBgDark', '')) ?: [$fallback]];
}

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
    $center = (bool) ($head['center'] ?? false);
    /* 2026-11-12 第九轮：允许调用方直接渲染成隐藏状态。
       光影画廊的二级路由（?album=<id>）要在服务端就藏掉页头，否则刷新详情页
       会先闪一下「光影画廊」大标题再被 gallery.ts 收起。 */
    $hidden = (bool) ($head['hidden'] ?? false);
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

    /* 相册由独立画廊插件提供，顺序优先主推相册。 */
    $albums = sg_albums();
    $album = isset($albums[0]) && is_array($albums[0]) ? $albums[0] : [];
    if ($album) {
        $photos = is_array($album['photos'] ?? null) ? array_values($album['photos']) : [];
        /* 首页宽幅卡只有 4:1 的窄条，用缩略图就够（OSS 站省流量） */
        $cover = trim((string) ($album['coverThumb'] ?? $album['cover'] ?? ''));
        if ($cover === '') {
            foreach ($photos as $photo) { if (!empty($photo['preview'])) { $cover = (string) ($photo['thumb'] ?? $photo['url'] ?? ''); break; } }
        }
        return [
            'title'  => (string) ($album['title'] ?? $fallback['title']),
            /* 相册简介优先，未填写时显示照片数量。 */
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
 * 文章末尾「相关推荐」数据源（2026-11-17 新增，对齐 Fomalhaut related_post）。
 *
 * 相关性：与当前文章共享分类或标签（与源站 helper 同口径，共享数即权重），
 * 权重降序 → 时间降序，剔除自己与碎语（cid 集合同 sg_home_articles）。
 * 直查模式与 sg_home_articles 同理由 —— Widget 上下文的坑已经踩过。
 * 展示日期用 updated（源站 date_type: updated），没有更新记录时回落 created。
 *
 * @return array<int,array{url:string,cover:string,date:string,title:string}>
 */
function sg_related_posts($archive, int $limit = 6): array
{
    try {
        $cid = (int) $archive->cid;
        if ($cid <= 0) {
            return [];
        }
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();

        /* 当前文章的分类/标签 mid 列表 */
        $mids = [];
        foreach ($db->fetchAll("SELECT r.mid FROM {$p}relationships r INNER JOIN {$p}metas m ON m.mid = r.mid WHERE r.cid = {$cid} AND m.type IN ('category', 'tag')") as $row) {
            $mid = (int) ($row['mid'] ?? 0);
            if ($mid > 0) {
                $mids[$mid] = true;
            }
        }
        if (!$mids) {
            return [];
        }
        $midList = implode(',', array_keys($mids));

        /* 共享 meta 的文章按共享数计权重；碎语隔离直接写进子查询 */
        $rows = $db->fetchAll(
            "SELECT r.cid, COUNT(*) AS w FROM {$p}relationships r"
            . " WHERE r.mid IN ({$midList}) AND r.cid <> {$cid}"
            . " AND r.cid NOT IN ("
            . "SELECT r2.cid FROM {$p}relationships r2 INNER JOIN {$p}metas m2 ON m2.mid = r2.mid"
            . " WHERE (m2.type = 'category' AND (m2.slug = '" . sg_notes_slug() . "' OR m2.name = '碎语'))"
            . " OR (m2.type = 'tag' AND (m2.slug = '" . sg_notes_slug() . "' OR m2.name = '碎语'))"
            . ") GROUP BY r.cid"
        );
        if (!$rows) {
            return [];
        }

        /* 权重降序 → 时间降序，截取 limit，再取文章行 */
        $scored = [];
        foreach ($rows as $row) {
            $scored[(int) ($row['cid'] ?? 0)] = (int) ($row['w'] ?? 0);
        }
        $in = implode(',', array_keys($scored));
        $posts = $db->fetchAll(
            "SELECT cid, title, slug, created, modified, text FROM {$p}contents"
            . " WHERE cid IN ({$in}) AND type = 'post' AND status = 'publish'"
            . " AND (password IS NULL OR password = '')"
        );
        if (!$posts) {
            return [];
        }

        $fields = [];
        foreach ($db->fetchAll("SELECT cid, name, str_value FROM {$p}fields WHERE cid IN ({$in})") as $f) {
            $fcid = (int) ($f['cid'] ?? 0);
            if ($fcid > 0 && (string) ($f['str_value'] ?? '') !== '') {
                $fields[$fcid][(string) $f['name']] = (string) $f['str_value'];
            }
        }

        $tz = 0;
        try {
            $tz = (int) \Widget\Options::alloc()->timezone;
        } catch (\Throwable $e) {
            $tz = 0;
        }
        $options = sg_options();
        $fallbackCover = (string) sg_opt('sgDefaultCover', sg_theme_url('dist/img/cover-default.svg'));

        $out = [];
        foreach ($posts as $row) {
            $rcid = (int) ($row['cid'] ?? 0);
            if ($rcid <= 0 || !isset($scored[$rcid])) {
                continue;
            }
            $text = (string) ($row['text'] ?? '');
            if (strncmp($text, '<!--markdown-->', 15) === 0) {
                $text = \Utils\Markdown::convert(substr($text, 15));
            }
            $cover = trim((string) ($fields[$rcid]['cover'] ?? ''));
            if ($cover === '') {
                if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $text, $mm) && !empty($mm[1][0])) {
                    $cover = trim((string) $mm[1][0]);
                }
            }
            if ($cover === '') {
                $cover = $fallbackCover;
            }
            $path = (string) \Typecho\Router::url('post', [
                'cid'     => $rcid,
                'slug'    => (string) ($row['slug'] ?? ''),
                'created' => (int) ($row['created'] ?? 0),
                'type'    => 'post',
            ]);
            if ($path === '' || $path === '#') {
                $path = '/archives/' . $rcid . '/';
            }
            $modified = (int) ($row['modified'] ?? 0);
            $out[] = [
                'url'     => (string) \Typecho\Common::url($path, $options->index),
                'cover'   => sg_media($cover),
                'date'    => date('Y-m-d', ($modified > 0 ? $modified : (int) ($row['created'] ?? 0)) + $tz),
                'title'   => trim((string) ($row['title'] ?? '')),
                'weight'  => $scored[$rcid],
                'created' => (int) ($row['created'] ?? 0),
            ];
        }
        usort($out, static function (array $a, array $b): int {
            return $b['weight'] <=> $a['weight'] ?: $b['created'] <=> $a['created'];
        });
        return array_slice($out, 0, max(1, $limit));
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 首页「最新文章」数据源（2026-11-17 第三轮重写，绕开 Widget_Archive）。
 *
 * 线上排查结论：index 查询会把置顶文章前置且在窗口内重复出现，同一篇渲染两张卡；
 * 行内过滤依赖的 magic 属性在列表上下文也不可靠。这里与 sg_notes_query() 同模式
 * 直接查库，碎语隔离直接写进 SQL 子查询 —— 分类 slug=notes/名称=碎语、标签同名
 * 同 slug，四条路径全部剔除，不依赖 PHP 侧的 cid 集合。
 *
 * @return array<int,array{url:string,cover:string,quote:string,title:string,dateText:string,cate:string,tags:string,sticky:int}>
 */
function sg_home_articles(int $limit = 2): array
{
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        /* 置顶标记（2026-11-17）：Typecho 原生 contents 表没有 sticky 列，
           沿用本主题自定义字段约定 —— 文章后台填 sticky=1 普通置顶、sticky=2 强置顶，
           与 description/cover 同一机制。这里先拉全站置顶 cid=>等级。 */
        $stickyMap = [];
        foreach ($db->fetchAll("SELECT f.cid, f.int_value, f.str_value FROM {$p}fields f WHERE f.name = 'sticky'") as $f) {
            $level = (int) ($f['int_value'] ?? 0) > 0 ? (int) $f['int_value'] : (int) ($f['str_value'] ?? 0);
            if ($level > 0) {
                $stickyMap[(int) ($f['cid'] ?? 0)] = $level;
            }
        }
        /* 按时间取一个足够大的候选池（50 篇），置顶优先排序后截取 ——
           置顶文章未必是最新的 50 篇里，但对个人博客这个池子足够；
           避免在 SQL 里写 contents.sticky（该列不存在，一查就 500） */
        $rows = $db->fetchAll(
            "SELECT cid, title, slug, created, text FROM {$p}contents"
            . " WHERE type = 'post' AND status = 'publish'"
            . " AND (password IS NULL OR password = '')"
            . " AND cid NOT IN ("
            . "SELECT r.cid FROM {$p}relationships r INNER JOIN {$p}metas m ON m.mid = r.mid"
            . " WHERE (m.type = 'category' AND (m.slug = '" . sg_notes_slug() . "' OR m.name = '碎语'))"
            . " OR (m.type = 'tag' AND (m.slug = '" . sg_notes_slug() . "' OR m.name = '碎语'))"
            . ")"
            . " ORDER BY created DESC LIMIT 50"
        );
        if (!$rows) {
            return [];
        }

        $in = implode(',', array_map(static fn($row): int => (int) ($row['cid'] ?? 0), $rows));
        $fields = [];
        foreach ($db->fetchAll("SELECT cid, name, str_value FROM {$p}fields WHERE cid IN ({$in})") as $f) {
            $cid = (int) ($f['cid'] ?? 0);
            if ($cid > 0 && (string) ($f['str_value'] ?? '') !== '') {
                $fields[$cid][(string) $f['name']] = (string) $f['str_value'];
            }
        }
        $cats = [];
        foreach ($db->fetchAll("SELECT r.cid, m.name, m.slug FROM {$p}relationships r INNER JOIN {$p}metas m ON m.mid = r.mid WHERE r.cid IN ({$in}) AND m.type = 'category'") as $c) {
            $cid = (int) ($c['cid'] ?? 0);
            $name = trim((string) ($c['name'] ?? ''));
            if ($cid > 0 && $name !== '') {
                $cats[$cid][] = $name;
                $cats[$cid . ':slug'][] = (string) ($c['slug'] ?? '');
            }
        }
        $tags = [];
        foreach ($db->fetchAll("SELECT r.cid, m.name, m.slug FROM {$p}relationships r INNER JOIN {$p}metas m ON m.mid = r.mid WHERE r.cid IN ({$in}) AND m.type = 'tag'") as $t) {
            $cid = (int) ($t['cid'] ?? 0);
            $name = trim((string) ($t['name'] ?? ''));
            if ($cid > 0 && $name !== '') {
                $tags[$cid][] = $name;
            $tagSlugs[$cid][] = (string) ($t['slug'] ?? '');
            }
        }

        $tz = 0;
        try {
            $tz = (int) \Widget\Options::alloc()->timezone;
        } catch (\Throwable $e) {
            $tz = 0;
        }
        $options = sg_options();
        $fallbackCover = (string) sg_opt('sgDefaultCover', sg_theme_url('dist/img/cover-default.svg'));

        /* meta 链接构造（第十八轮对齐源站：分类/标签全部可点）。Router::url 路由表
           由归档组件初始化；路由缺失或参数不齐时返回 '#'/{占位}，此时降级为纯文本。 */
        $metaLink = static function (string $type, array $names, array $slugs) use ($options): array {
            $rows = [];
            foreach ($names as $i => $name) {
                $row = ['name' => (string) $name, 'url' => ''];
                $slug = (string) ($slugs[$i] ?? '');
                if ($slug !== '') {
                    try {
                        $u = \Typecho\Router::url($type, [
                            'slug' => $slug, 'dir' => '', 'category' => $slug, 'directory' => '',
                        ], (string) $options->index);
                        if ($u !== '#' && !str_contains($u, '{')) {
                            $row['url'] = $u;
                        }
                    } catch (\Throwable $e) {
                        $row['url'] = '';
                    }
                }
                $rows[] = $row;
            }
            return $rows;
        };

        $out = [];
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid <= 0) {
                continue;
            }
            $text = (string) ($row['text'] ?? '');
            /* Markdown 存文先转 HTML，供下方封面兑底抽 <img> 用（summary 已改为 description-only） */
            if (strncmp($text, '<!--markdown-->', 15) === 0) {
                $text = \Utils\Markdown::convert(substr($text, 15));
            }
            $own = $fields[$cid] ?? [];
            /* 引号摘要（2026-11-17）：description 自定义字段优先 —— 填了就是自定义文案；
               没填则退回正文自动摘要（前 48 字），保证卡片文字始终可见。 */
            $quote = trim((string) ($own['description'] ?? ''));
            if ($quote === '') {
                $quote = sg_notes_excerpt($text);
            }
            if (function_exists('mb_strlen') && mb_strlen($quote, 'UTF-8') > 48) {
                $quote = mb_substr($quote, 0, 48, 'UTF-8') . '…';
            }

            /* Router::url 只给路径，自己跟站点入口拼（同 sg_notes_query） */
            $path = (string) \Typecho\Router::url('post', [
                'cid'     => $cid,
                'slug'    => (string) ($row['slug'] ?? ''),
                'created' => (int) ($row['created'] ?? 0),
                'type'    => 'post',
            ]);
            if ($path === '' || $path === '#') {
                $path = '/archives/' . $cid . '/';
            }

            $stamp = (int) ($row['created'] ?? 0);
            $ago = sg_time_ago($stamp);
            $cover = trim((string) ($own['cover'] ?? ''));
            if ($cover === '') {
                $cover = $fallbackCover;
            }

            $out[] = [
                'url'      => (string) \Typecho\Common::url($path, $options->index),
                'cover'    => sg_media($cover),
                'quote'    => $quote,
                'title'    => trim((string) ($row['title'] ?? '')),
                'dateText' => $ago !== '' ? '发表于 ' . $ago : '发表于 ' . date('Y.m.d', $stamp + $tz),
                'cates'    => $metaLink('category', $cats[$cid] ?? [], $cats[$cid . ':slug'] ?? []),
                'tagRows'  => $metaLink('tag', $tags[$cid] ?? [], $tagSlugs[$cid] ?? []),
                'sticky'   => $stickyMap[$cid] ?? 0,
            ];
        }
        /* 置顶优先、同级按时间倒序（对齐源站「置顶卡片显示在精选位」） */
        usort($out, static function (array $a, array $b): int {
            $sa = (int) ($a['sticky'] ?? 0);
            $sb = (int) ($b['sticky'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            return 0;
        });
        return array_slice($out, 0, max(1, $limit));
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 首页分类入口芯片（对齐 Fomalhaut 首页分类导航卡）：按文章数取前 N 个分类。
 * 路由缺失时 url 留空，模板降级为纯文本芯片；任何异常回空数组，首页不 500。
 *
 * @return array<int,array{name:string,count:int,url:string}>
 */
function sg_home_categories(int $limit = 6): array
{
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('name', 'slug', 'count')->from('table.metas')
                ->where('type = ?', 'category')->order('count', \Typecho\Db::SORT_DESC)
                ->limit($limit)
        );
        $options = sg_options();
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $slug = (string) ($row['slug'] ?? '');
            if ($name === '' || $slug === '') {
                continue;
            }
            $url = '';
            try {
                $path = (string) \Typecho\Router::url('category', [
                    'slug' => $slug, 'dir' => '', 'category' => $slug, 'directory' => '',
                ]);
                if ($path !== '' && $path !== '#' && !str_contains($path, '{')) {
                    $url = (string) \Typecho\Common::url($path, $options->index);
                }
            } catch (\Throwable $e) {
                $url = '';
            }
            $out[] = ['name' => $name, 'count' => (int) ($row['count'] ?? 0), 'url' => $url];
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 「最新文章」卡片的统一 HTML（凸三角封面 + 引号摘要 + 居中标题/元信息）。
 * card-post.php（归档/分类/标签等 Widget 上下文）与首页直查数据共用同一份标记，
 * 两侧只负责组装字段，改卡片形态只改这里。
 * tags 传预构建的 HTML（归档页是链接，首页是纯文本），调用方自行转义。
 */
function sg_feature_card_html(array $card): string
{
    $url = (string) ($card['url'] ?? '#');
    $cover = (string) ($card['cover'] ?? '');
    $quote = trim((string) ($card['quote'] ?? ''));
    $title = trim((string) ($card['title'] ?? ''));
    if ($title === '') {
        $title = '未命名文章';
    }
    $dateText = trim((string) ($card['dateText'] ?? ''));
    $cate = trim((string) ($card['cate'] ?? ''));
    $tags = trim((string) ($card['tags'] ?? ''));
    $sticky = !empty($card['sticky']);

    $html  = '<article class="sg-feature-card" data-sg-filter-item>';
    /* 置顶角标（fomalhaut post-sticky-badge 同款：红底白字胶囊 + 流光扫过） */
    if ($sticky) {
        $html .= '<div class="sg-sticky-badge">' . sg_icon('pin') . '精选<span class="sg-sticky-shine" aria-hidden="true"></span></div>';
    }
    $html .= '<a class="sg-feature-media" href="' . sg_e($url) . '" tabindex="-1" aria-hidden="true">';
    $html .= '<img src="' . sg_e($cover) . '" alt="" loading="lazy">';
    if ($quote !== '') {
        $html .= '<span class="sg-feature-quote">「' . sg_e($quote) . '」</span>';
    }
    /* 封面 V 缺口尖上的小三角（fomalhaut recent-post-arrow 同款 20×10 + 弹跳） */
    $html .= '<span class="sg-feature-arrow" aria-hidden="true"></span>';
    $html .= '</a>';
    $html .= '<div class="sg-feature-body">';
    $html .= '<h3 class="sg-feature-title"><a href="' . sg_e($url) . '">' . sg_e($title) . '</a></h3>';
    /* meta 行对齐源站 post-ui.pug .article-meta-wrap：日期 | 分类链接(' > ' 分隔) | 标签链接(' • ' 分隔)。
       第十八轮修复「标签不全」：旧版合并成一段纯文本且分类只取第一个，现全部渲染为可点击链接。 */
    $cates = is_array($card['cates'] ?? null) ? $card['cates'] : [];
    $tagRows = is_array($card['tagRows'] ?? null) ? $card['tagRows'] : [];
    $html .= '<div class="sg-feature-meta">';
    $html .= '<span class="is-date">' . sg_icon_color('clock') . sg_e($dateText) . '</span>';
    if ($cates) {
        $links = [];
        foreach ($cates as $sgIdx => $sgCate) {
            $links[] = '<a class="sg-meta-link" href="' . sg_e((string) $sgCate['url']) . '">' . sg_e((string) $sgCate['name']) . '</a>';
            if ($sgIdx < count($cates) - 1) {
                $links[] = '<i class="sg-meta-sep" aria-hidden="true">&gt;</i>';
            }
        }
        $html .= '<span class="is-cate">' . sg_icon_color('folder') . implode('', $links) . '</span>';
    }
    if ($tagRows) {
        $links = [];
        foreach ($tagRows as $sgIdx => $sgTag) {
            $links[] = '<a class="sg-meta-link" href="' . sg_e((string) $sgTag['url']) . '">' . sg_e((string) $sgTag['name']) . '</a>';
            if ($sgIdx < count($tagRows) - 1) {
                $links[] = '<span class="sg-meta-sep" aria-hidden="true"> • </span>';
            }
        }
        $html .= '<span class="is-tags">' . sg_icon_color('tag') . implode('', $links) . '</span>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</article>';
    return $html;
}

/**
 * 首页封面轮播的数据源：最近若干篇「普通文章」（跳过碎语）。
 *
 * 为什么不再复用 index.php 的主循环：主循环受「每页条数上限」限制，
 * 一旦这个值小于碎语条数（线上是 6 篇碎语），最新一页会全是碎语，
 * 循环里被逐条跳过后封面就成了空卡片 —— 2026-11-16 线上「左侧文章不显示」就是这个原因。
 * 这里用 Recent 组件单独取数，条数按「需要几张 + 碎语条数」多取，与列表分页互不影响。
 *
 * @return array<int,array{title:string,url:string,cover:string,date:string,category:string,desc:string}>
 */
function sg_home_slides(int $limit = 5): array
{
    $limit = max(1, $limit);
    $slides = [];

    try {
        $recent = \Widget\Contents\Post\Recent::allocWithAlias(
            'sg_home_slides',
            ['pageSize' => $limit + count(sg_note_cids())]
        );
        while ($recent->next()) {
            if (sg_is_note((int) $recent->cid)) {
                continue;
            }
            $title = trim((string) $recent->title);
            $slides[] = [
                'title'    => $title !== '' ? $title : '未命名文章',
                'url'      => (string) $recent->permalink,
                'cover'    => sg_cover($recent),
                'date'     => date('Y.m.d', (int) $recent->created),
                'category' => sg_first_category($recent),
                'desc'     => sg_excerpt($recent, 72),
            ];
            if (count($slides) >= $limit) {
                break;
            }
        }
    } catch (\Throwable $e) {
        return [];
    }

    return $slides;
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
 * 分类按 slug=notes 或名称「碎语」认：真站建分类时缩略名未必填 notes，
 * 只认 slug 会把碎语漏进首页/归档列表（2026-11-16 第三轮修复）。
 *
 * 2026-11-17 修复：不能再写 where('slug = ? OR name = ?', 'notes', '碎语') ——
 * Typecho 的 where() 第三参是 SQL 连接符而不是第二个绑定值，两个占位符只绑
 * 第一个值，多值写法构造出错误 SQL，被下面的 try/catch 吞成空集合，
 * 这正是首页最新文章过滤失效、碎语漏出来的根因。改为两条单条件查询在 PHP 侧合并。
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
        /* 两条单条件查询，PHP 侧取并集：slug=notes 与名称=碎语 认同一个分类 */
        $mids = [];
        foreach (
            [
                $db->fetchAll($db->select('mid')->from('table.metas')->where('type = ?', 'category')->where('slug = ?', sg_notes_slug())),
                $db->fetchAll($db->select('mid')->from('table.metas')->where('type = ?', 'category')->where('name = ?', '碎语')),
            ] as $rows
        ) {
            foreach ($rows as $row) {
                $mid = (int) ($row['mid'] ?? 0);
                if ($mid > 0) {
                    $mids[$mid] = true;
                }
            }
        }
        foreach (array_keys($mids) as $mid) {
            foreach ($db->fetchAll($db->select('cid')->from('table.relationships')->where('mid = ?', $mid)) as $row) {
                $cid = (int) ($row['cid'] ?? 0);
                if ($cid > 0) {
                    $cache[] = $cid;
                }
            }
        }
        $cache = array_values(array_unique($cache));
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

/**
 * 打了「碎语」标签的全部文章 cid（静态缓存，失败回空数组）。
 * 有的站碎语不走分类而是打标签，首页最新文章列表要把两种写法都剔出去；
 * 标签按 name='碎语' 或 slug='notes' 认，与分类的约定缩略名保持一致。
 *
 * 2026-11-17：与 sg_note_cids() 同根因 —— where() 不能带两个绑定值，
 * 改为两条单条件查询在 PHP 侧合并。
 */
function sg_chatter_tag_cids(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = [];
    try {
        $db = \Typecho\Db::get();
        $mids = [];
        foreach (
            [
                $db->fetchAll($db->select('mid')->from('table.metas')->where('type = ?', 'tag')->where('name = ?', '碎语')),
                $db->fetchAll($db->select('mid')->from('table.metas')->where('type = ?', 'tag')->where('slug = ?', 'notes')),
            ] as $rows
        ) {
            foreach ($rows as $row) {
                $mid = (int) ($row['mid'] ?? 0);
                if ($mid > 0) {
                    $mids[$mid] = true;
                }
            }
        }
        foreach (array_keys($mids) as $mid) {
            foreach ($db->fetchAll($db->select('cid')->from('table.relationships')->where('mid = ?', $mid)) as $row) {
                $cid = (int) ($row['cid'] ?? 0);
                if ($cid > 0) {
                    $cache[] = $cid;
                }
            }
        }
        $cache = array_values(array_unique($cache));
    } catch (\Throwable $e) {
        $cache = [];
    }

    return $cache;
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
 * 卡片引号摘要（2026-11-17 新增）：description 自定义字段优先（站长可控），
 * 没填再从正文提取 —— Recent 组件不查 text，sg_excerpt() 恒空，知识档案卡的
 * 引号摘要要用它兑底（对齐 Fomalhaut index_post_content 的正文摘录）。
 * 统一精简到 48 字（两行以内）。
 */
function sg_card_desc($archive): string
{
    /* 2026-11-17：description 自定义字段优先（自定义文案）；没填则回正文自动摘要 ——
       源站的文字本质就是「有 description 用 description」，
       这里多一层兑底让卡片文字始终可见。 */
    $custom = sg_field($archive, 'description', '');
    $quote = trim(strip_tags($custom));
    if ($quote === '') {
        try {
            $db = \Typecho\Db::get();
            $p = $db->getPrefix();
            $row = $db->fetchRow('SELECT text FROM ' . $p . 'contents WHERE cid = ' . (int) $archive->cid);
            $text = (string) ($row['text'] ?? '');
            if (strncmp($text, '<!--markdown-->', 15) === 0) {
                $text = \Utils\Markdown::convert(substr($text, 15));
            }
            $quote = sg_notes_excerpt($text);
        } catch (\Throwable $e) {
            $quote = '';
        }
    }
    if (function_exists('mb_strlen') && mb_strlen($quote, 'UTF-8') > 48) {
        $quote = mb_substr($quote, 0, 48, 'UTF-8') . '…';
    }
    return $quote;
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
 * 文章字数与预计阅读时长。
 *
 * 中文按 400 字/分钟、英文/数字按 220 词/分钟估算，最少 1 分钟。
 * 只统计可见文字：先剥 HTML，再把连续空白压成一个空格。
 * 失败时返回 0 字 / 1 分钟，模板按 chars > 0 决定是否显示。
 *
 * @return array{chars:int, minutes:int, text:string}
 */
function sg_reading_stats(string $html): array
{
    $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($html)));
    $chars = function_exists('mb_strlen') ? (int) mb_strlen($text, 'UTF-8') : strlen($text);
    $cjk = preg_match_all('/[\x{4e00}-\x{9fff}]/u', $text);
    $latin = preg_match_all('/[A-Za-z0-9]+/', $text);
    $minutes = (int) ceil(((int) $cjk) / 400 + ((int) $latin) / 220);
    if ($minutes < 1) {
        $minutes = 1;
    }

    if ($chars >= 10000) {
        $textCount = rtrim(rtrim(number_format($chars / 10000, 1), '0'), '.') . ' 万字';
    } else {
        $textCount = number_format($chars) . ' 字';
    }

    return ['chars' => $chars, 'minutes' => $minutes, 'text' => $textCount];
}

/**
 * 每个分类的最新一篇文章（P1 分类总览页「最新 1 条」）。
 *
 * 一次连表查询按发布时间倒序取前 600 行，PHP 侧每个 mid 只留第一条；
 * 返回 [mid => ['title','url','date']]，失败回空数组。
 */
function sg_category_latest_map(): array
{
    try {
        $db = \Typecho\Db::get();
        $options = \Typecho\Widget::widget('Widget_Options');
        $rows = $db->fetchAll(
            $db->select([
                'table.relationships.mid' => 'mid',
                'table.contents.cid' => 'cid',
                'table.contents.title' => 'title',
                'table.contents.slug' => 'slug',
                'table.contents.created' => 'created',
            ])->from('table.relationships')
                ->join('table.contents', 'table.contents.cid = table.relationships.cid')
                ->where('table.contents.type = ?', 'post')
                ->where('table.contents.status = ?', 'publish')
                ->order('table.contents.created', \Typecho\Db::SORT_DESC)
                ->limit(600)
        );

        $map = [];
        foreach ($rows as $row) {
            $mid = (int) ($row['mid'] ?? 0);
            $cid = (int) ($row['cid'] ?? 0);
            if ($mid <= 0 || $cid <= 0 || isset($map[$mid])) {
                continue;
            }
            $created = (int) ($row['created'] ?? 0);
            $path = \Typecho\Router::url('post', [
                'cid' => $cid,
                'slug' => (string) ($row['slug'] ?? ''),
                'created' => $created,
                'type' => 'post',
            ]);
            $map[$mid] = [
                'title' => (string) ($row['title'] ?? ''),
                'url' => \Typecho\Common::url($path, $options->index),
                'date' => $created > 0 ? date('Y-m-d', $created) : '',
            ];
        }
        return $map;
    } catch (\Throwable $e) {
        return [];
    }
}

/**
 * 上下篇：按发布时间取相邻的普通文章（跳过页面、草稿、加密与未来文章）。
 *
 * 查询条件与 Typecho 自带的 thePrev()/theNext() 保持一致，额外返回封面，
 * 供文章末尾的图文导航使用。取不到时返回 null，不抛错。
 *
 * @return array{title:string,url:string,cover:string}|null
 */
function sg_adjacent_post($archive, string $direction = 'prev'): ?array
{
    try {
        $isPrev = $direction === 'prev';
        $query = $archive->select()
            ->where('table.contents.created ' . ($isPrev ? '<' : '>') . ' ?', $archive->created)
            ->where('table.contents.status = ?', 'publish')
            ->where('table.contents.type = ?', 'post')
            ->where("table.contents.password IS NULL OR table.contents.password = ''");

        if (!$isPrev) {
            $query->where('table.contents.created < ?', time());
        }

        /* 碎语隔离（2026-11-17）：上一篇/下一篇只在普通文章之间流转，
           碎语分类与「碎语」标签命中的 cid 一律剔除（与首页列表同一份集合）。 */
        $sgSkipCids = array_values(array_filter(array_map('intval', array_merge(sg_note_cids(), sg_chatter_tag_cids()))));
        if ($sgSkipCids) {
            $query->where('table.contents.cid NOT IN (' . implode(',', $sgSkipCids) . ')');
        }

        $query->order('table.contents.created', $isPrev ? \Typecho\Db::SORT_DESC : \Typecho\Db::SORT_ASC)->limit(1);

        $content = \Widget\Contents\From::allocWithAlias(
            'sg_' . $direction . ':' . $archive->cid,
            ['query' => $query]
        );
        if (!$content->have()) {
            return null;
        }

        return [
            'title' => (string) $content->title,
            'url'   => (string) $content->permalink,
            'cover' => sg_cover($content),
        ];
    } catch (\Throwable $e) {
        return null;
    }
}
