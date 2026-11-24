<?php
/**
 * 字海：全站文字的字级索引（数据层）
 *
 * 以「字」为轴组织内容：每篇正文（文章 + 碎语）清洗后只计汉字，
 * 累出每字的频次 / 位次 / 初见末见 / 逐年密度 / 出处例句 / 同句共现字。
 * 岁候以节气历法分桶内容；字海换成语言轴，数据口径完全独立。
 *
 * 构建成本与缓存：
 *   - 全量扫描一次 contents 表（type=post 且 publish，碎语也在此集合内），
 *     清洗后逐字累加。数百篇量级为亚秒到秒级一次性开销；
 *   - 结果序列化为紧凑 JSON，走 sg_cache_file()（/tmp/soraglass-cache 优先，
 *     回退 dist/data/cache，与歌单 / GitHub / AI 日报同一缓存路径）；
 *   - 失效判定沿用岁候的版本哈希思路：md5(COUNT + MAX(created) + MAX(modified) +
 *     notes_slug)，任何发文 / 改稿都会让下一次请求自动重建，无需手动干预；
 *   - 管理员可在字海页地址后追加 ?rebuild=1 强制重建（page-zi.php 调 sg_zi_rebuild）。
 *
 * 缓存结构（键从短以控制体积；对外形状由本文件底部各读数函数整形）：
 *   version / builtAt        缓存指纹与构建时间
 *   total / distinct         总字数 / 不同字数
 *   newYear / curYear        今年新见字数 / 当前公历年
 *   posts / notes            计入字海的文章篇数 / 碎语条数
 *   rank                     [[字, 次], …] 全量降序（门厅高频二十取前 20，
 *                            仅一见从尾部遍历 f=1）
 *   todayPool                今日一字池（排名 30–130 的字，避开虚字与孤证）
 *   posts                    {cid: [slug, created, 标题, 是否碎语]}（例句反查链接）
 *   chars                    {字: {
 *       f 频次, r 位次,
 *       fi 初见时间戳, li 末见时间戳, fc 初见 cid, lc 末见 cid,
 *       ys {年: 次数},
 *       q  [[cid, 句], …]（每篇首句、最早优先，全站 ≤12 句）,
 *       m  [[共现字, 次数], …]（共现次数降序、剔除虚字；只对前 800 字计算）
 *   }}
 *
 * 统计口径（与 page-zi.php 页脚「口径与边界」文案逐条对应）：
 *   - 只计汉字：基本区 U+4E00–U+9FFF 与扩展 A 区 U+3400–U+4DBF；
 *   - 清洗顺序：先整块丢弃 ``` 围栏 / <pre> / <code> / 行内反引号代码，
 *     再剥 HTML 与实体解码，最后丢弃 URL —— 代码与链接里的字不入海；
 *   - 出处例句按句末标点切句，每篇每字只记第一句，每字全站最多 12 句，
 *     最早优先（构建按 created 升序遍历，先填先得）；
 *   - 共现字取「同句共现」，只对全站频次前 800 的字计算（构建成本从对数级
 *     降到句级），展示时剔除频次前 10 的虚字（的一是……共现谁都是它们）。
 */

if (!defined('SG_ROOT')) {
    define('SG_ROOT', dirname(__DIR__));
}

const SG_ZI_QUOTE_LIMIT = 12;   // 每字全站例句上限（最早优先）
const SG_ZI_QUOTE_LEN = 48;     // 单句截断长度（字符）
const SG_ZI_MATES_LIMIT = 8;    // 每字共现字上限
const SG_ZI_MATES_SKIP = 10;    // 共现挑选时剔除频次前 N 的虚字
const SG_ZI_MATES_RANK_CAP = 800; // 共现只对全站频次前 800 的字计算
const SG_ZI_SENT_UNIQ_CAP = 30; // 句内不同字超过此数的长句不参与共现统计
const SG_ZI_TODAY_FROM = 30;    // 今日一字池：频次排名下界（避开「的了我」）
const SG_ZI_TODAY_TO = 130;     // 今日一字池：频次排名上界（避开孤证）

/* ============================================================
 * 一、开关与版本
 * ============================================================ */

/** 后台 sgZi 开关（默认开）。关掉时字海页显示关闭态、首页挂件不渲染。 */
function sg_zi_enabled(): bool
{
    return sg_opt('sgZi', 'on') !== 'off';
}

/**
 * 内容指纹：COUNT + MAX(created) + MAX(modified) + 碎语缩略名。
 * 字海与岁候的内容集合是同一张 contents 表的同一个子集，口径直接沿用。
 */
function sg_zi_version(): string
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

/** 管理员强制重建：丢弃进程内缓存，按当前内容重新构建并覆写缓存文件。 */
function sg_zi_rebuild(): void
{
    sg_zi_index(true);
}

/* ============================================================
 * 二、索引入口（读缓存 → 过期重建 → 对外整形）
 * ============================================================ */

/**
 * 对外索引：['rank' => [['c' => 字, 'f' => 次], …], 'chars' => 原始字表, …]。
 * rank 是全量降序榜（门厅从头部取高频二十、从尾部数仅一见）。
 * 返回 null 表示开关关闭或构建失败；调用方走空态。
 */
function sg_zi_index(bool $force = false): ?array
{
    static $cache = null;
    static $shaped = null;
    if (!$force && $shaped !== null) {
        return $shaped;
    }
    if (!sg_zi_enabled()) {
        return $shaped = null;
    }
    $data = sg_zi_cache($force);
    if ($data === null) {
        return $shaped = null;
    }
    $rank = [];
    foreach ((array) ($data['rank'] ?? []) as $pair) {
        $rank[] = ['c' => (string) $pair[0], 'f' => (int) $pair[1]];
    }
    return $shaped = [
        'rank' => $rank,
        'chars' => (array) ($data['chars'] ?? []),
        'posts' => (array) ($data['postsMap'] ?? []),
        'total' => (int) ($data['total'] ?? 0),
        'distinct' => (int) ($data['distinct'] ?? 0),
    ];
}

/** 紧凑缓存层：命中读文件，过期重建。 */
function sg_zi_cache(bool $force = false): ?array
{
    static $cache = null;
    if (!$force && $cache !== null) {
        return $cache;
    }
    $version = sg_zi_version();
    $file = $version !== '' ? sg_cache_file('zi-index.json') : '';

    if (!$force && $file !== '' && is_file($file)) {
        $raw = @file_get_contents($file);
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && ($data['version'] ?? '') === $version) {
                return $cache = $data;
            }
        }
    }

    $data = sg_zi_build($version);
    if ($data === null) {
        return $cache = null;
    }
    if ($file !== '') {
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    return $cache = $data;
}

/* ============================================================
 * 三、文本清洗
 * ============================================================ */

/**
 * 清洗到「只剩值得入海的散文」：整块丢弃代码（围栏 / pre / code / 行内反引号）、
 * 丢弃 URL，再剥 HTML 与实体。非汉字反正不入海，markdown 记号无需单独处理。
 */
function sg_zi_clean(string $text): string
{
    if ($text === '') {
        return '';
    }
    /* 围栏代码块 ``` … ```（跨行，非贪婪） */
    $text = preg_replace('/```[\s\S]*?```/', ' ', $text) ?? $text;
    /* 渲染态或残留的 pre / code 整块 */
    $text = preg_replace('/<pre\b[^>]*>[\s\S]*?<\/pre>/i', ' ', $text) ?? $text;
    $text = preg_replace('/<code\b[^>]*>[\s\S]*?<\/code>/i', ' ', $text) ?? $text;
    /* 行内反引号代码 */
    $text = preg_replace('/`[^`\n]*`/', ' ', $text) ?? $text;
    /* URL（含 markdown 链接的括号地址） */
    $text = preg_replace('~https?://\S+~iu', ' ', $text) ?? $text;
    /* 实体还原（&#x4E00; 这类字符引用也该入海），再剥标签 */
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    return strip_tags($text);
}

/** 只计汉字的匹配（基本区 + 扩展 A）；文本非法 UTF-8 时返回空数组。 */
function sg_zi_cjk(string $s): array
{
    $m = [];
    if (!preg_match_all('/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u', $s, $m)) {
        return [];
    }
    return $m[0];
}

/* ============================================================
 * 四、构建
 * ============================================================ */

function sg_zi_build(string $version): ?array
{
    try {
        $db = \Typecho\Db::get();
        $p = $db->getPrefix();
        $slug = addslashes(sg_notes_slug());
        $sql = "SELECT c.cid, c.title, c.slug, c.created, c.text,"
            . " (SELECT COUNT(*) FROM {$p}relationships r LEFT JOIN {$p}metas m ON m.mid = r.mid"
            . "  WHERE r.cid = c.cid AND ((m.type = 'category' AND (m.slug = '{$slug}' OR m.name = '碎语'))"
            . "  OR (m.type = 'tag' AND (m.slug = '{$slug}' OR m.name = '碎语')))) AS is_notes"
            . " FROM {$p}contents c"
            . " WHERE c.type = 'post' AND c.status = 'publish' AND c.created > 0 AND c.created <= " . (int) time()
            . " ORDER BY c.created ASC";
        $rows = $db->fetchAll($db->query($sql));
    } catch (\Throwable $e) {
        return null;
    }

    $freq = [];
    $years = [];
    $firstTs = [];
    $lastTs = [];
    $firstCid = [];
    $lastCid = [];
    $quotes = [];
    $pairs = [];
    $postCount = 0;
    $noteCount = 0;

    /* 逐篇清洗累加；created 升序遍历保证例句「最早优先」 */
    foreach ($rows as $row) {
        $cid = (int) ($row['cid'] ?? 0);
        $created = (int) ($row['created'] ?? 0);
        $isNote = (int) ($row['is_notes'] ?? 0) > 0;
        $text = sg_zi_clean((string) ($row['text'] ?? ''));
        if ($cid <= 0 || $created <= 0 || $text === '') {
            continue;
        }
        $chars = sg_zi_cjk($text);
        if (!$chars) {
            continue;
        }
        if ($isNote) {
            $noteCount++;
        } else {
            $postCount++;
        }
        $y = (int) substr(date('Y-m', sg_date_ts($created)), 0, 4);

        foreach ($chars as $ch) {
            $freq[$ch] = ($freq[$ch] ?? 0) + 1;
            $years[$ch][$y] = ($years[$ch][$y] ?? 0) + 1;
            if (!isset($firstTs[$ch]) || $created < $firstTs[$ch]) {
                $firstTs[$ch] = $created;
                $firstCid[$ch] = $cid;
            }
            if (!isset($lastTs[$ch]) || $created > $lastTs[$ch]) {
                $lastTs[$ch] = $created;
                $lastCid[$ch] = $cid;
            }
        }

        /* 句级：例句归属 + 同句共现 */
        $sents = preg_split('/[。！？；!?;\n\r]+/u', $text) ?: [];
        $seenInPost = [];
        foreach ($sents as $s) {
            $sChars = array_values(array_unique(sg_zi_cjk($s)));
            if (!$sChars) {
                continue;
            }

            /* 共现：两两成对，长句（不同字多）不参与，控制构建成本 */
            $n = count($sChars);
            if ($n >= 2 && $n <= SG_ZI_SENT_UNIQ_CAP) {
                for ($i = 0; $i < $n; $i++) {
                    for ($j = $i + 1; $j < $n; $j++) {
                        $a = $sChars[$i];
                        $b = $sChars[$j];
                        if ($a > $b) {
                            $t = $a;
                            $a = $b;
                            $b = $t;
                        }
                        $pairs[$a . $b] = ($pairs[$a . $b] ?? 0) + 1;
                    }
                }
            }

            /* 例句：每篇每字只记第一句，全站 ≤12 句，最早优先 */
            foreach ($sChars as $ch) {
                if (isset($seenInPost[$ch])) {
                    continue;
                }
                $seenInPost[$ch] = true;
                if (count($quotes[$ch] ?? []) >= SG_ZI_QUOTE_LIMIT) {
                    continue;
                }
                $q = trim($s);
                if (mb_strlen($q, 'UTF-8') > SG_ZI_QUOTE_LEN) {
                    $q = mb_substr($q, 0, SG_ZI_QUOTE_LEN, 'UTF-8') . '…';
                }
                $quotes[$ch][] = [$cid, $q];
            }
        }
    }

    if (!$freq) {
        return null;
    }

    /* 位次与全量降序榜 */
    arsort($freq);
    $rank = [];
    $r = 0;
    foreach ($freq as $ch => $f) {
        $rank[$ch] = ++$r;
    }
    $rankPairs = [];
    foreach ($freq as $ch => $f) {
        $rankPairs[] = [$ch, $f];
    }

    /* 虚字集合：共现挑选时剔除（的一是……共现谁都是它们） */
    $virtual = [];
    $i = 0;
    foreach ($freq as $ch => $f) {
        if (++$i > SG_ZI_MATES_SKIP) {
            break;
        }
        $virtual[$ch] = true;
    }

    /* 共现对表 → 每字共现列表（只对频次前 800 的字计算） */
    $mateCnt = [];
    foreach ($pairs as $pair => $cnt) {
        $a = mb_substr($pair, 0, 1, 'UTF-8');
        $b = mb_substr($pair, 1, 1, 'UTF-8');
        if (!isset($virtual[$a]) && $rank[$a] <= SG_ZI_MATES_RANK_CAP) {
            $mateCnt[$a][$b] = ($mateCnt[$a][$b] ?? 0) + $cnt;
        }
        if (!isset($virtual[$b]) && $rank[$b] <= SG_ZI_MATES_RANK_CAP) {
            $mateCnt[$b][$a] = ($mateCnt[$b][$a] ?? 0) + $cnt;
        }
    }
    unset($pairs);

    /* 内容反查表：cid → [slug, created, 标题, 是否碎语] */
    $posts = [];
    foreach ($rows as $row) {
        $cid = (int) ($row['cid'] ?? 0);
        if ($cid <= 0) {
            continue;
        }
        $posts[$cid] = [
            (string) ($row['slug'] ?? ''),
            (int) ($row['created'] ?? 0),
            (string) ($row['title'] ?? ''),
            (int) ($row['is_notes'] ?? 0) > 0,
        ];
    }

    /* 今日一字池：排名 30–130 */
    $todayPool = [];
    $i = 0;
    foreach ($freq as $ch => $f) {
        $i++;
        if ($i >= SG_ZI_TODAY_FROM && $i <= SG_ZI_TODAY_TO) {
            $todayPool[] = $ch;
        }
    }

    /* 今年新见字数（初见年份即今年） */
    $curYear = (int) substr(date('Y-m', sg_date_ts(time())), 0, 4);
    $newYear = 0;
    foreach ($firstTs as $ts) {
        if ((int) substr(date('Y-m', sg_date_ts($ts)), 0, 4) === $curYear) {
            $newYear++;
        }
    }

    /* 字表 */
    $chars = [];
    foreach ($freq as $ch => $f) {
        $mates = [];
        if (isset($mateCnt[$ch])) {
            arsort($mateCnt[$ch]);
            $k = 0;
            foreach ($mateCnt[$ch] as $other => $cnt) {
                if (++$k > SG_ZI_MATES_LIMIT) {
                    break;
                }
                $mates[] = [$other, $cnt];
            }
        }
        $chars[$ch] = [
            'f' => $f,
            'r' => $rank[$ch],
            'fi' => $firstTs[$ch],
            'li' => $lastTs[$ch],
            'fc' => $firstCid[$ch],
            'lc' => $lastCid[$ch],
            'ys' => $years[$ch],
            'q' => $quotes[$ch] ?? [],
            'm' => $mates,
        ];
    }

    return [
        'version' => $version,
        'builtAt' => time(),
        'total' => array_sum($freq),
        'distinct' => count($freq),
        'newYear' => $newYear,
        'curYear' => $curYear,
        'posts' => $postCount,
        'notes' => $noteCount,
        'rank' => $rankPairs,
        'todayPool' => $todayPool,
        'postsMap' => $posts,
        'chars' => $chars,
    ];
}

/* ============================================================
 * 五、读数与整形（page-zi.php / side-zi.php 的出口）
 * ============================================================ */

/** 字海页地址：无参门厅，['c' => 字] 进字卡，['gate' => 1] 进秘境信页。
 * 中文单字 urlencode 后短而稳；秘境地址必须服务端拼好（兼容 ?p= 型无伪静态地址）。 */
function sg_zi_url(array $params = []): string
{
    $base = sg_page_url('zi');
    $glue = str_contains($base, '?') ? '&' : '?';
    $out = $base;
    $ch = (string) ($params['c'] ?? '');
    if ($ch !== '') {
        $out .= $glue . 'c=' . urlencode($ch);
        $glue = '&';
    }
    if (!empty($params['gate'])) {
        $out .= $glue . 'gate=1';
    }
    return $out;
}

/** 文章永久链接：与 sg_season_post_url 同一拼法（Router 失败退回 /archives/{cid}/）。
 * $cid 是 contents 主键（postsMap 的键，不是元素）；$post = [slug, created, …]。 */
function sg_zi_post_url(int $cid, array $post): string
{
    try {
        $path = (string) \Typecho\Router::url('post', [
            'cid' => $cid,
            'slug' => (string) $post[0],
            'created' => (int) $post[1],
            'type' => 'post',
        ]);
    } catch (\Throwable $e) {
        $path = '';
    }
    if ($path === '' || $path === '#') {
        $path = '/archives/' . $cid . '/';
    }
    return (string) \Typecho\Common::url($path, \Widget\Options::alloc()->index);
}

/** 全站读数行：门厅读数三格 + 页脚口径的篇数/条数。 */
function sg_zi_stats(): array
{
    $data = sg_zi_cache();
    if ($data === null) {
        return ['total' => 0, 'distinct' => 0, 'newThisYear' => 0, 'posts' => 0, 'notes' => 0];
    }
    return [
        'total' => (int) ($data['total'] ?? 0),
        'distinct' => (int) ($data['distinct'] ?? 0),
        'newThisYear' => (int) ($data['newYear'] ?? 0),
        'posts' => (int) ($data['posts'] ?? 0),
        'notes' => (int) ($data['notes'] ?? 0),
    ];
}

/** 某字的位次（1 起）；不在字海里返回 0。 */
function sg_zi_rank_of(string $ch): int
{
    $data = sg_zi_cache();
    return (int) (($data['chars'][$ch]['r'] ?? 0));
}

/**
 * 例句 → 展示行（text / url / ts / title）。posts 反查不到（篇已删）时整条跳过。
 * 字海页与今日一字共用本出口。
 */
function sg_zi_quote_row(array $index, array $q): array
{
    $cid = (int) $q[0];
    $post = $index['posts'][$cid] ?? null;
    if (!$post) {
        return [];
    }
    return [
        'text' => (string) $q[1],
        'url' => sg_zi_post_url($cid, $post),
        'ts' => (int) $post[1],
        'title' => (string) $post[2],
        'note' => (bool) $post[3],
    ];
}

/**
 * 今日一字：按本地日期确定性选取（无随机数，服务端直出，午夜换字）。
 * 池为频次排名 30–130 的字，避开「的了我」也避开孤证。
 */
function sg_zi_today(): array
{
    $data = sg_zi_cache();
    if ($data === null) {
        return [];
    }
    $pool = (array) ($data['todayPool'] ?? []);
    if (!$pool) {
        return [];
    }
    $seed = (int) date('Ymd', sg_date_ts(time()));
    $ch = $pool[$seed % count($pool)];
    $row = $data['chars'][$ch] ?? null;
    if (!$row) {
        return [];
    }

    $shaped = [
        'rank' => [],
        'chars' => [],
        'posts' => (array) ($data['postsMap'] ?? []),
    ];
    $quotes = [];
    foreach ((array) ($row['q'] ?? []) as $q) {
        $r = sg_zi_quote_row($shaped, $q);
        if ($r) {
            $quotes[] = $r;
        }
    }
    $fiCid = (int) ($row['fc'] ?? 0);
    $fiPost = $fiCid ? ($shaped['posts'][$fiCid] ?? null) : null;

    return [
        'char' => $ch,
        'freq' => (int) ($row['f'] ?? 0),
        'r' => (int) ($row['r'] ?? 0),
        'first' => [
            'ts' => $fiPost ? (int) $fiPost[1] : (int) ($row['fi'] ?? 0),
            'title' => $fiPost ? (string) $fiPost[2] : '',
            'url' => $fiPost ? sg_zi_post_url($fiCid, $fiPost) : '',
        ],
        'quotes' => $quotes,
    ];
}

/**
 * 字卡全量读数。形状与 page-zi.php 逐键对应：
 *   freq / years / first {ts, title, url} / last (ts) / quotes [{text,url,ts,title}] / mates {字: 次}
 */
function sg_zi_char(string $ch): ?array
{
    $data = sg_zi_cache();
    if ($data === null) {
        return null;
    }
    $row = $data['chars'][$ch] ?? null;
    if (!$row) {
        return null;
    }
    $shaped = [
        'rank' => [],
        'chars' => [],
        'posts' => (array) ($data['postsMap'] ?? []),
    ];

    /* 初见：fc 反查篇目；反查不到（篇已删）退到时间戳本位 */
    $fiCid = (int) ($row['fc'] ?? 0);
    $fiPost = $fiCid ? ($shaped['posts'][$fiCid] ?? null) : null;
    $first = [
        'ts' => $fiPost ? (int) $fiPost[1] : (int) ($row['fi'] ?? 0),
        'title' => $fiPost ? (string) $fiPost[2] : '',
        'url' => $fiPost ? sg_zi_post_url($fiCid, $fiPost) : '',
    ];

    $quotes = [];
    foreach ((array) ($row['q'] ?? []) as $q) {
        $r = sg_zi_quote_row($shaped, $q);
        if ($r) {
            $quotes[] = $r;
        }
    }

    $mates = [];
    foreach ((array) ($row['m'] ?? []) as $pair) {
        $mates[(string) $pair[0]] = (int) $pair[1];
    }

    return [
        'freq' => (int) ($row['f'] ?? 0),
        'years' => (array) ($row['ys'] ?? []),
        'first' => $first,
        'last' => (int) ($row['li'] ?? 0),
        'quotes' => $quotes,
        'mates' => $mates,
    ];
}


/* ============================================================
 * 六、秘境彩蛋门（M5）：?gate=1 深夜来信
 * ============================================================ */

/**
 * 每日轮换的一封「深夜来信」：从「仅一见」里按本地日期确定性挑一个字，
 * 带出它的孤证句与原文。与今日一字同一确定性哲学（无随机数，午夜换字）。
 * 门是否打开由访客本地记账决定（zi-gate.ts），这里只备信。
 */
function sg_zi_gate_letter(): ?array
{
    $data = sg_zi_cache();
    if ($data === null) {
        return null;
    }
    $rare = [];
    foreach ((array) ($data['rank'] ?? []) as $pair) {
        if ((int) $pair[1] === 1) {
            $rare[] = (string) $pair[0];
        }
    }
    if (!$rare) {
        return null;
    }
    $seed = (int) date('Ymd', sg_date_ts(time()));
    $ch = $rare[$seed % count($rare)];
    $card = sg_zi_char($ch);
    if (!$card || !$card['quotes']) {
        return null;
    }
    $q = $card['quotes'][0];
    $title = (string) $q['title'];
    return [
        'ch' => $ch,
        'year' => date('Y', sg_date_ts((int) ($card['first']['ts'] ?? 0))),
        'title' => $title !== '' ? $title : '碎语',
        'quote' => (string) $q['text'],
        'url' => (string) $q['url'],
        'charUrl' => sg_zi_url(['c' => $ch]),
    ];
}