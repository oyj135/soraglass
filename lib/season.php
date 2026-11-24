<?php
/**
 * 岁候：二十四节气 × 黄道十二宫（数据层）
 *
 * 一条黄道，两种读法：节气 24 等分（15°）、黄道十二宫 12 等分（30°），
 * 所以宫界必然落在偶数序节气（中气）上 —— gong = floor(i / 2)，不需要任何映射表。
 *
 * 时间口径（与 lib/config.php 的 sg_date_ts 同一基准）：
 *   - 库里的 created 是真实 UTC 时间戳；
 *   - 本文件的 ts / end 也是**真实 UTC 时间戳**，指向「站点时区的当日 00:00」；
 *   - 归日一律把 created 加上站点偏移再看，不用 UTC 日期，也不依赖数据库日期函数。
 *
 * 年口径：一个公历年 = 该年小寒 → 次年小寒，恰好 24 个节气、365/366 天，
 * 环永远完整、区间无空档。1 月 1 日至小寒前的几天属于**上一年**的冬至区间。
 */

if (!defined('SG_ROOT')) {
    define('SG_ROOT', dirname(__DIR__));
}

/* ============================================================
 * 一、静态资产
 * ============================================================ */

/**
 * 24 节气元数据，下标即 i（0 = 春分 … 18 = 冬至, 19 = 小寒 … 23 = 惊蛰）。
 * 顺序按黄经升序，与 data/season-terms.php 的 MMDD 串一一对应。
 */
function sg_season_meta(): array
{
    static $meta = null;
    if ($meta !== null) {
        return $meta;
    }
    return $meta = [
        ['chunfen', '春分'], ['qingming', '清明'], ['guyu', '谷雨'], ['lixia', '立夏'],
        ['xiaoman', '小满'], ['mangzhong', '芒种'], ['xiazhi', '夏至'], ['xiaoshu', '小暑'],
        ['dashu', '大暑'], ['liqiu', '立秋'], ['chushu', '处暑'], ['bailu', '白露'],
        ['qiufen', '秋分'], ['hanlu', '寒露'], ['shuangjiang', '霜降'], ['lidong', '立冬'],
        ['xiaoxue', '小雪'], ['daxue', '大雪'], ['dongzhi', '冬至'], ['xiaohan', '小寒'],
        ['dahan', '大寒'], ['lichun', '立春'], ['yushui', '雨水'], ['jingzhe', '惊蛰'],
    ];
}

/**
 * 七十二候（通行本《月令七十二候集解》，引文一字不改）。
 * 下标 i 与节气同序，每项三候，候序 0/1/2 对应节气内第 1–5 / 6–10 / 11–15 日。
 */
function sg_season_hou(): array
{
    static $hou = null;
    if ($hou !== null) {
        return $hou;
    }
    return $hou = [
        0 => ['玄鸟至', '雷乃发声', '始电'],
        1 => ['桐始华', '田鼠化为鴽', '虹始见'],
        2 => ['萍始生', '鸣鸠拂其羽', '戴胜降于桑'],
        3 => ['蝼蝈鸣', '蚯蚓出', '王瓜生'],
        4 => ['苦菜秀', '靡草死', '麦秋至'],
        5 => ['螳螂生', '鵙始鸣', '反舌无声'],
        6 => ['鹿角解', '蜩始鸣', '半夏生'],
        7 => ['温风至', '蟋蟀居壁', '鹰始击'],
        8 => ['腐草为萤', '土润溽暑', '大雨时行'],
        9 => ['凉风至', '白露降', '寒蝉鸣'],
        10 => ['鹰乃祭鸟', '天地始肃', '禾乃登'],
        11 => ['鸿雁来', '玄鸟归', '群鸟养羞'],
        12 => ['雷始收声', '蛰虫坯户', '水始涸'],
        13 => ['鸿雁来宾', '雀入大水为蛤', '菊有黄华'],
        14 => ['豺乃祭兽', '草木黄落', '蛰虫咸俯'],
        15 => ['水始冰', '地始冻', '雉入大水为蜃'],
        16 => ['虹藏不见', '天气上升地气下降', '闭塞而成冬'],
        17 => ['鹖鴠不鸣', '虎始交', '荔挺出'],
        18 => ['蚯蚓结', '麋角解', '水泉动'],
        19 => ['雁北乡', '鹊始巢', '雉始雊'],
        20 => ['鸡始乳', '征鸟厉疾', '水泽腹坚'],
        21 => ['东风解冻', '蛰虫始振', '鱼陟负冰'],
        22 => ['獭祭鱼', '候雁北', '草木萌动'],
        23 => ['桃始华', '仓庚鸣', '鹰化为鸠'],
    ];
}

/** 24 条节气白话语，只写物候与天气，不超过 24 字。 */
function sg_season_gloss(): array
{
    static $gloss = null;
    if ($gloss !== null) {
        return $gloss;
    }
    return $gloss = [
        0 => '昼夜对半，风里开始有土腥气。',
        1 => '雨多了，桐花开，天也洗干净了。',
        2 => '最后一场春雨，秧苗扎下根。',
        3 => '日头开始咬人，蛙声从夜里起来。',
        4 => '麦粒灌浆未满，雨水却先满了。',
        5 => '一边收一边种，最忙的几天。',
        6 => '白天最长，之后一天短过一天。',
        7 => '热得发闷，蟋蟀躲进墙缝。',
        8 => '一年最热，雷雨说来就来。',
        9 => '还热，但风里有了一丝凉。',
        10 => '暑气退场，早晚开始有凉意。',
        11 => '草叶结露，天亮时一片白。',
        12 => '昼夜再对半，天高得厉害。',
        13 => '露水凉透了，菊花开了。',
        14 => '夜里结霜，最后一茬叶子落尽。',
        15 => '水面初结薄冰，冬天从这天算起。',
        16 => '不再见虹，天光短，万物收进土里。',
        17 => '雪落得住了，夜最长前的一段。',
        18 => '白天最短，数九从这天开始。',
        19 => '冷到骨头里，但白天在变长。',
        20 => '一年最冷，河面冻实。',
        21 => '土松了，风转暖，一切重新起头。',
        22 => '雪化成雨，泥里冒出新芽。',
        23 => '一声雷，虫子都醒了。',
    ];
}

/**
 * 十二宫。起于中气 i = 2g，只写季节气质：
 * 不写性格、不写运势、不写「宜」。
 */
function sg_season_gongs(): array
{
    static $gongs = null;
    if ($gongs !== null) {
        return $gongs;
    }
    return $gongs = [
        ['aries', '白羊', '昼夜均而寒暑平，草木抽芽。'],
        ['taurus', '金牛', '雨水渐密，麦粒初满。'],
        ['gemini', '双子', '昼长夜短，鸟兽换毛，藤蔓爬满了架。'],
        ['cancer', '巨蟹', '日到最长，暑气初盛，蝉第一次叫。'],
        ['leo', '狮子', '一年最热，蝉声压过一切，午后常有雷。'],
        ['virgo', '处女', '暑退凉生，谷穗低头，开始收拾。'],
        ['libra', '天秤', '昼夜再均，天高气清，雁开始南飞。'],
        ['scorpio', '天蝎', '霜落之后，昼短，万物收敛，叶落尽。'],
        ['sagittarius', '射手', '夜一天长过一天，火与酒，走得远一点。'],
        ['capricorn', '摩羯', '一年最短的白天，做长久的打算。'],
        ['aquarius', '水瓶', '寒到极处开始松动，冰下有水声。'],
        ['pisces', '双鱼', '雪化泥松，风里已有湿气。'],
    ];
}

/* ============================================================
 * 二、时间与归属
 * ============================================================ */

/** 站点时区偏移（秒）。Typecho 的 timezone 是固定偏移，无夏令时。 */
function sg_season_tz(): int
{
    static $tz = null;
    if ($tz !== null) {
        return $tz;
    }
    try {
        $tz = (int) \Widget\Options::alloc()->timezone;
    } catch (\Throwable $e) {
        $tz = (int) date('Z');
    }
    return $tz;
}

/** 节气日期表（生成物，见 scripts/gen-season.mjs）。 */
function sg_season_table(): array
{
    static $table = null;
    if ($table !== null) {
        return $table;
    }
    $file = SG_ROOT . '/data/season-terms.php';
    $table = is_file($file) ? (array) require $file : [];
    return $table;
}

/** 站点时区某日 00:00 的真实 UTC 时间戳。 */
function sg_season_day_ts(int $year, int $month, int $day): int
{
    return gmmktime(0, 0, 0, $month, $day, $year) - sg_season_tz();
}

/**
 * 某时刻在站点的本地日期（Y-m-d）。
 * 必须走 sg_date_ts()：服务器本身已在站点时区时，直接加偏移会再多出 8 小时。
 */
function sg_season_local_date(int $ts): string
{
    return date('Y-m-d', sg_date_ts($ts));
}

/** 本地日序号（1970-01-01 起），用于算天数差，避免 DST 与偏移叠加。 */
function sg_season_local_day(int $ts): int
{
    return (int) (strtotime(sg_season_local_date($ts) . ' UTC') / 86400);
}

/**
 * 某公历年的 24 个节气实例，键即 i。
 * 每年恰好一组；i=19..23 的日期在 1–3 月，按日期排序才会排在最前。
 */
function sg_season_terms(int $year): array
{
    static $cache = [];
    if (isset($cache[$year])) {
        return $cache[$year];
    }
    $table = sg_season_table();
    if (!isset($table[$year])) {
        return $cache[$year] = [];
    }
    $row = (string) $table[$year];
    $meta = sg_season_meta();
    $hou = sg_season_hou();
    $gloss = sg_season_gloss();
    $terms = [];
    for ($i = 0; $i < 24; $i++) {
        $month = (int) substr($row, $i * 4, 2);
        $day = (int) substr($row, $i * 4 + 2, 2);
        $terms[$i] = [
            'i' => $i,
            'slug' => $meta[$i][0],
            'name' => $meta[$i][1],
            'lon' => $i * 15,
            'month' => $month,
            'day' => $day,
            'date' => sprintf('%04d-%02d-%02d', $year, $month, $day),
            'ts' => sg_season_day_ts($year, $month, $day),
            'zhongqi' => $i % 2 === 0,
            'gong' => intdiv($i, 2),
            'hou' => $hou[$i],
            'gloss' => $gloss[$i],
            'year' => $year,
        ];
    }

    /* end = 按日期排序后的下一节气起点；冬至的 end 是次年小寒 */
    $order = array_keys($terms);
    usort($order, static fn(int $a, int $b): int => $terms[$a]['ts'] <=> $terms[$b]['ts']);
    $nextRow = isset($table[$year + 1]) ? (string) $table[$year + 1] : '';
    $nextFirst = $nextRow !== ''
        ? sg_season_day_ts($year + 1, (int) substr($nextRow, 19 * 4, 2), (int) substr($nextRow, 19 * 4 + 2, 2))
        : 0;
    foreach ($order as $k => $i) {
        if (isset($order[$k + 1])) {
            $terms[$i]['end'] = $terms[$order[$k + 1]]['ts'];
        } else {
            $terms[$i]['end'] = $nextFirst > $terms[$i]['ts'] ? $nextFirst : $terms[$i]['ts'] + 15 * 86400;
        }
    }

    return $cache[$year] = $terms;
}

/** 24 个节气按实际日期排序后的 i 列表（1–3 月在前的 19..23）。 */
function sg_season_order(int $year): array
{
    $terms = sg_season_terms($year);
    $order = array_keys($terms);
    usort($order, static fn(int $a, int $b): int => $terms[$a]['ts'] <=> $terms[$b]['ts']);
    return $order;
}

/** 给定时刻在本年的位置；1/1–小寒前回溯到上一年的冬至。 */
function sg_season_at(int $ts, int $year): array
{
    $terms = sg_season_terms($year);
    if (!$terms) {
        return [];
    }
    if ($ts < $terms[19]['ts']) {
        $prev = sg_season_terms($year - 1);
        return isset($prev[18]) ? sg_season_position($ts, $prev[18], $prev, $year - 1) : [];
    }
    foreach (sg_season_order($year) as $i) {
        if ($ts >= $terms[$i]['ts'] && $ts < $terms[$i]['end']) {
            return sg_season_position($ts, $terms[$i], $terms, $year);
        }
    }
    return sg_season_position($ts, $terms[18], $terms, $year);
}

/** 位置换算：候序按 5 天切，第 15、16 天并入第三候。 */
function sg_season_position(int $ts, array $term, array $terms, int $year): array
{
    $dayInTerm = (int) floor(($ts - $term['ts']) / 86400) + 1;
    $dayInTerm = max(1, min(16, $dayInTerm));
    $gongStart = $terms[$term['i'] - $term['i'] % 2] ?? $term;
    $dayInGong = (int) floor(($ts - $gongStart['ts']) / 86400) + 1;
    return [
        'term' => (int) $term['i'],
        'gong' => (int) $term['gong'],
        'dayInTerm' => $dayInTerm,
        'houIndex' => min(2, intdiv($dayInTerm - 1, 5)),
        'dayInGong' => max(1, $dayInGong),
        'year' => $year,
    ];
}

/** 今日双读：节气 + 宫 + 距下一节气天数。页面、挂件共用同一出处。 */
function sg_season_now(?int $now = null): array
{
    $now = $now ?? time();
    $localYear = (int) substr(sg_season_local_date($now), 0, 4);
    $terms = sg_season_terms($localYear);
    if (!$terms) {
        return [];
    }
    $pos = sg_season_at($now, $localYear);
    if (!$pos) {
        return [];
    }
    $term = $terms[$pos['term']];
    $today = sg_season_local_day($now);
    $endDay = sg_season_local_day($term['end']);
    /* 下一节气按日期序取；冬至的下一条是次年小寒（i=18 排在一年最后） */
    $order = sg_season_order($localYear);
    $at = array_search($pos['term'], $order, true);
    $nextIndex = $at === false ? null : ($order[$at + 1] ?? null);
    $next = $nextIndex === null
        ? (sg_season_terms($localYear + 1)[19] ?? $term)
        : $terms[$nextIndex];

    return [
        'term' => $pos['term'],
        'termName' => $term['name'],
        'termSlug' => $term['slug'],
        'gong' => $pos['gong'],
        'gongName' => sg_season_gongs()[$pos['gong']][1],
        'gongSlug' => sg_season_gongs()[$pos['gong']][0],
        'houIndex' => $pos['houIndex'],
        'houName' => $term['hou'][$pos['houIndex']],
        'gloss' => $term['gloss'],
        'dayInTerm' => $pos['dayInTerm'],
        'nextName' => $next['name'],
        'nextDays' => max(0, $endDay - $today),
        'year' => $localYear,
    ];
}

/* ============================================================
 * 三、内容分桶与区间
 * ============================================================ */

/**
 * 一年一次查询，PHP 切三桶（文章 / 宫 / 碎语）。
 * 碎语也是 contents 行，必须单列，否则会在展陈区出现两次。
 */
function sg_season_buckets(int $year): array
{
    static $cache = [];
    if (isset($cache[$year])) {
        return $cache[$year];
    }
    $empty = ['terms' => [], 'gongs' => [], 'notes' => [], 'from' => 0, 'to' => 0, 'skipped' => 0];
    $terms = sg_season_terms($year);
    if (!$terms) {
        return $cache[$year] = $empty;
    }
    $from = $terms[19]['ts'];
    $to = $terms[18]['end'];

    /* 年数据缓存（评审稿 §7.6）：版本号取「文章条数 + 最新发布 + 最新修改」，
       发文与改文都会改变它，旧缓存自然失效，不需要在发布钩子里清缓存。
       只缓存年数据，不缓存整页 HTML —— 后者会产生 36 × 年数的变体。 */
    $version = sg_season_version();
    $file = $version !== '' ? sg_cache_file('season-' . $year . '.json') : '';
    if ($file !== '' && is_file($file)) {
        $cached = json_decode((string) @file_get_contents($file), true);
        if (is_array($cached) && ($cached['v'] ?? '') === $version && is_array($cached['buckets'] ?? null)) {
            return $cache[$year] = $cached['buckets'] + $empty;
        }
    }

    $buckets = [
        'terms' => array_fill(0, 24, []),
        'gongs' => array_fill(0, 12, []),
        'notes' => array_fill(0, 24, []),
        'from' => $from,
        'to' => $to,
        'skipped' => 0,
    ];
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        /* slug 是主题内常量，仍需转义；时间与 cid 一律强转整型 */
        $slugSql = "'" . addslashes(sg_notes_slug()) . "'";
        $fromSql = (int) $from;
        $toSql = (int) $to;
        $nowSql = (int) time();
        $sql = "SELECT c.cid, c.slug, c.created, c.title, f.str_value AS cover,"
            . " (SELECT COUNT(*) FROM {$p}relationships r INNER JOIN {$p}metas m ON m.mid = r.mid"
            . "  WHERE r.cid = c.cid AND ((m.type = 'category' AND (m.slug = {$slugSql} OR m.name = '碎语'))"
            . "  OR (m.type = 'tag' AND (m.slug = {$slugSql} OR m.name = '碎语')))) AS is_notes"
            . " FROM {$p}contents c LEFT JOIN {$p}fields f ON f.cid = c.cid AND f.name = 'cover'"
            . " WHERE c.type = 'post' AND c.status = 'publish' AND c.created > 0"
            . " AND c.created >= {$fromSql} AND c.created < {$toSql} AND c.created <= {$nowSql}"
            . " ORDER BY c.created DESC";
        $rows = $db->fetchAll($db->query($sql));
    } catch (\Throwable $e) {
        return $cache[$year] = $empty;
    }

    $index = \Widget\Options::alloc()->index;
    foreach ($rows as $row) {
        $ts = (int) ($row['created'] ?? 0);
        $pos = sg_season_at($ts, $year);
        if (!$pos) {
            $buckets['skipped']++;
            continue;
        }
        $item = [
            'cid' => (int) $row['cid'],
            'created' => $ts,
            'title' => (string) ($row['title'] ?? ''),
            'cover' => (string) ($row['cover'] ?? ''),
            'url' => sg_season_post_url($row, $ts, $index),
            'term' => $pos['term'],
            'houIndex' => $pos['houIndex'],
            'gong' => $pos['gong'],
        ];
        if ((int) ($row['is_notes'] ?? 0) > 0) {
            $buckets['notes'][$pos['term']][] = $item;
        } else {
            $buckets['terms'][$pos['term']][] = $item;
            $buckets['gongs'][$pos['gong']][] = $item;
        }
    }

    if ($file !== '') {
        @file_put_contents($file, json_encode(['v' => $version, 'buckets' => $buckets], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    return $cache[$year] = $buckets;
}

/** 年数据的版本号：条数 + 最新发布 + 最新修改，任一变化即失效。 */
function sg_season_version(): string
{
    static $version = null;
    if ($version !== null) {
        return $version;
    }
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        $row = $db->fetchRow($db->query(
            "SELECT COUNT(*) AS c, MAX(created) AS m, MAX(modified) AS d FROM {$p}contents"
            . " WHERE type = 'post' AND status = 'publish'"
        ));
        return $version = md5(implode('|', [
            (int) ($row['c'] ?? 0), (int) ($row['m'] ?? 0), (int) ($row['d'] ?? 0), sg_notes_slug(),
        ]));
    } catch (\Throwable $e) {
        return $version = '';
    }
}

/** 文章永久链接：Router::url 只给路径，要自己跟站点入口拼（关伪静态时带 /index.php）。 */
function sg_season_post_url(array $row, int $ts, string $index): string
{
    try {
        $path = (string) \Typecho\Router::url('post', [
            'cid' => (int) ($row['cid'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'created' => $ts,
            'type' => 'post',
        ]);
    } catch (\Throwable $e) {
        $path = '';
    }
    if ($path === '' || $path === '#') {
        $path = '/archives/' . (int) ($row['cid'] ?? 0) . '/';
    }
    return (string) \Typecho\Common::url($path, $index);
}

/** 哪些年份有内容（年份链接行只列非空年份 + 当年）。 */
function sg_season_years(): array
{
    static $years = null;
    if ($years !== null) {
        return $years;
    }
    $table = sg_season_table();
    if (!$table) {
        return $years = [];
    }
    $now = (int) substr(sg_season_local_date(time()), 0, 4);
    $min = min(array_keys($table));
    $max = min($now + 1, max(array_keys($table)));
    $out = [];
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        $rows = $db->fetchAll($db->query(
            "SELECT c.created FROM {$p}contents c"
            . " WHERE c.type = 'post' AND c.status = 'publish' AND c.created > 0 AND c.created <= " . (int) time()
            . " ORDER BY c.created ASC"
        ));
        foreach ($rows as $row) {
            $y = (int) substr(sg_season_local_date((int) $row['created']), 0, 4);
            if ($y >= $min && $y <= $max) {
                $out[$y] = true;
            }
        }
    } catch (\Throwable $e) {
        return $years = [];
    }
    $out[$now] = true;
    $list = array_keys($out);
    sort($list);
    return $years = $list;
}

/**
 * 日期区间 → 内容。?t=（15 天）、?g=（30 天）、?d=（5 天）都走这里。
 */
function sg_season_range(int $from, int $to, int $year): array
{
    $buckets = sg_season_buckets($year);
    $posts = [];
    $notes = [];
    if ($buckets['terms']) {
        foreach ($buckets['terms'] as $i => $items) {
            foreach ($items as $item) {
                if ($item['created'] >= $from && $item['created'] < $to) {
                    $posts[$item['cid']] = $item;
                }
            }
        }
        foreach ($buckets['notes'] as $i => $items) {
            foreach ($items as $item) {
                if ($item['created'] >= $from && $item['created'] < $to) {
                    $notes[$item['cid']] = $item;
                }
            }
        }
    }
    $posts = array_values($posts);
    $notes = array_values($notes);
    usort($posts, static fn(array $a, array $b): int => $b['created'] <=> $a['created']);

    return [
        'posts' => $posts,
        'notes' => $notes,
        'photos' => sg_season_photos($from, $to, 2),
        'empty' => $posts === [] && $notes === [],
        'nearPrev' => sg_season_near($year, $from, -1),
        'nearNext' => sg_season_near($year, $from, 1),
    ];
}

/** 最近的、有内容的相邻节气（空态里的「上一段有三篇 →」）。 */
function sg_season_near(int $year, int $from, int $dir): array
{
    $terms = sg_season_terms($year);
    if (!$terms) {
        return [];
    }
    /* ?d= 的起点是候起算日，不一定等于节气起点，按「最后一个不晚于 from 的节气」定位 */
    $order = sg_season_order($year);
    $index = 0;
    foreach ($order as $k => $i) {
        if ($terms[$i]['ts'] <= $from) {
            $index = $k;
        }
    }
    $buckets = sg_season_buckets($year);
    for ($k = $index + $dir; $k >= 0 && $k < count($order); $k += $dir) {
        $i = $order[$k];
        $count = count($buckets['terms'][$i] ?? []);
        if ($count > 0) {
            return ['term' => $i, 'name' => $terms[$i]['name'], 'slug' => $terms[$i]['slug'], 'count' => $count];
        }
    }
    return [];
}

/**
 * 相册照片：captured 与区间求交，captured 为空的退到 created。
 * 插件缺席或表不存在时返回 []，绝不让页面因相册出错。
 */
function sg_season_photos(int $from, int $to, int $limit = 2): array
{
    if (!sg_gallery_plugin()) {
        return [];
    }
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        $localFrom = "'" . addslashes(sg_season_local_date($from)) . "'";
        $localTo = "'" . addslashes(sg_season_local_date($to)) . "'";
        $sql = "SELECT id, album, url, thumb, caption, captured, width, height, preview, object_key, storage"
            . " FROM {$p}sg_gallery_photos"
            . " WHERE state = 'uploaded' AND published = 1 AND preview = 1"
            . " AND ((captured IS NOT NULL AND captured <> '' AND captured >= {$localFrom} AND captured < {$localTo})"
            . " OR ((captured IS NULL OR captured = '') AND created >= " . (int) $from . " AND created < " . (int) $to . "))"
            . " ORDER BY captured DESC, id DESC LIMIT " . max(1, (int) $limit);
        $rows = $db->fetchAll($db->query($sql));
        if (!$rows) {
            return [];
        }
        if (class_exists('TypechoPlugin\\SoraglassGallery\\Settings')) {
            $delivery = \TypechoPlugin\SoraglassGallery\Settings::read();
            foreach ($rows as $k => $row) {
                $rows[$k] = \TypechoPlugin\SoraglassGallery\Settings::photoUrls($row, $delivery);
            }
        }
        return array_map(static fn(array $row): array => [
            'id' => (string) $row['id'],
            'album' => (string) $row['album'],
            'url' => (string) ($row['url'] ?? ''),
            'thumb' => (string) ($row['thumb'] ?? $row['url'] ?? ''),
            'caption' => (string) ($row['caption'] ?? ''),
            'date' => (string) ($row['captured'] ?? ''),
        ], $rows);
    } catch (\Throwable $e) {
        return [];
    }
}

/* ============================================================
 * 四、输出
 * ============================================================ */

/**
 * 唯一的链接出口：t / g / d 互斥，y 可共存。
 * 参数一律 ASCII slug，中文不进程 URL（分享后会被百分号编码截断）。
 */
function sg_season_url(array $params = [], ?string $base = null): string
{
    $base = $base ?? sg_page_url('season');
    $query = [];
    if (isset($params['t']) && $params['t'] !== '') {
        $query['t'] = (string) $params['t'];
    } elseif (isset($params['g']) && $params['g'] !== '') {
        $query['g'] = (string) $params['g'];
    } elseif (isset($params['d']) && $params['d'] !== '') {
        $query['d'] = (string) $params['d'];
    }
    if (isset($params['y']) && (string) $params['y'] !== '') {
        $query['y'] = (string) $params['y'];
    }
    if ($query === []) {
        return $base;
    }
    $sep = strpos($base, '?') === false ? '?' : '&';
    return $base . $sep . http_build_query($query);
}

/** 挂件一行：「立冬 · 天蝎宫 · 距小雪 12 天」。 */
function sg_season_line(?int $now = null): string
{
    $now = sg_season_now($now);
    if (!$now) {
        return '';
    }
    return $now['termName'] . ' · ' . $now['gongName'] . '宫 · 距' . $now['nextName'] . ' ' . $now['nextDays'] . ' 天';
}

/** 按 slug 找节气下标，找不到返回 -1。 */
function sg_season_index(string $slug): int
{
    foreach (sg_season_meta() as $i => $row) {
        if ($row[0] === $slug) {
            return $i;
        }
    }
    return -1;
}

/** 按 slug 找宫下标，找不到返回 -1。 */
function sg_season_gong_index(string $slug): int
{
    foreach (sg_season_gongs() as $g => $row) {
        if ($row[0] === $slug) {
            return $g;
        }
    }
    return -1;
}

/**
 * 侧栏日期卡的一行节气文案（服务端直出，与岁候页同一张表）。
 * 节气当天写「今天就是小雪」，其余写「3天后是大雪」。
 */
function sg_season_sidebar(?int $now = null): array
{
    $info = sg_season_now($now);
    if (!$info) {
        return [];
    }
    return $info['dayInTerm'] === 1
        ? ['prefix' => '今天就是', 'name' => $info['termName']]
        : ['prefix' => max(0, (int) $info['nextDays']) . '天后是', 'name' => $info['nextName']];
}

/** 某个 Y-m-d 属于哪一个年份页（1 月 1 日至小寒前归上一年）。 */
function sg_season_year_of(string $date): int
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return 0;
    }
    $year = (int) $m[1];
    $terms = sg_season_terms($year);
    if (!$terms) {
        return 0;
    }
    return sg_season_day_ts($year, (int) $m[2], (int) $m[3]) < $terms[19]['ts'] ? $year - 1 : $year;
}

/**
 * ?d=YYYY-MM-DD 所在的那一候（5 天）的区间。
 * 候起算日 = 节气起点 + 候序 × 5 天，第三候在 15/16 天的节气里自然多吃一天。
 */
function sg_season_hou_range(int $year, string $date): array
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return [];
    }
    $terms = sg_season_terms($year);
    if (!$terms) {
        return [];
    }
    /* 取当地正午再定位，避免 00:00 卡在日界 */
    $ts = sg_season_day_ts((int) $m[1], (int) $m[2], (int) $m[3]) + 43200;
    $pos = sg_season_at($ts, $year);
    if (!$pos) {
        return [];
    }
    $term = $terms[$pos['term']];
    $from = $term['ts'] + $pos['houIndex'] * 5 * 86400;
    $to = min($term['end'], $from + 5 * 86400);
    return [
        'from' => $from,
        'to' => $to,
        'term' => $pos['term'],
        'houIndex' => $pos['houIndex'],
        'date' => sg_season_local_date($from),
    ];
}
