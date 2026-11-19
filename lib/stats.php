<?php
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
/**
 * 跨请求缓存一个标量/数组结果（P2-9）：复用 sg_cache_file 的落盘目录，TTL 1 小时。
 * 只在「全表扫描」这类昂贵计算上使用；管理员带 ?sg-refresh=1 可强制重算。
 * $producer 抛错或缓存不可写时静默回退到实时计算，功能不受影响。
 */
function sg_stats_disk_cache(string $key, callable $producer)
{
    static $mem = [];
    if (array_key_exists($key, $mem)) {
        return $mem[$key];
    }
    $file = sg_cache_file('stats-' . $key . '.json');
    $bypass = isset($_GET['sg-refresh']) && sg_user_is_admin();
    $ttl = 3600;
    if (!$bypass && $file !== '' && is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (is_array($cached) && array_key_exists('v', $cached)) {
            $mem[$key] = $cached['v'];
            return $mem[$key];
        }
    }
    $value = $producer();
    if ($file !== '') {
        @file_put_contents($file, json_encode(['v' => $value], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    $mem[$key] = $value;
    return $value;
}

function sg_stats(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
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

    $meta = static function (string $type): int {
        try {
            $db = \Typecho\Db::get();
            $row = $db->fetchObject(
                $db->select(['COUNT(*)' => 'num'])->from('table.metas')->where('type = ?', $type)
            );
            return (int) ($row->num ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    };

    $photos = sg_gallery_catalog()['photoTotal'];

    return $cache = [
        'posts'  => (string) $count('post'),
        'notes'  => (string) sg_notes_count(),
        'photos' => (string) $photos,
        'tags'       => (string) $meta('tag'),
        'categories' => (string) $meta('category'),
    ];
}

/**
 * 小站资讯卡数据（对齐 Fomalhaut card_webinfo 新版）：
 * KPI 总字数 / 篇均字 / 友链 + 已运行 / 距上次发文 / 更新 + 年度产出柱状（固定最近三年）与写作日数。
 * 复用现有 sg_total_words / sg_uptime / sg_json；文章时间一次查全在 PHP 侧聚合。
 * 访客数 / 总访问量不在这里算：页脚 busuanzi 镜像异步回写 DOM。任何一步失败都回退默认值，绝不让首页 500。
 */
function sg_webinfo(): array
{
    return sg_stats_disk_cache('webinfo', 'sg_webinfo_uncached');
}

function sg_webinfo_uncached(): array
{
    $words = sg_total_words();
    $posts = (int) (sg_stats()['posts'] ?? 0);
    $friends = count(array_values(array_filter(sg_json('sgFriends', 'friends', []), 'is_array')));
    $uptime = sg_uptime();

    $years = [];
    $writeDays = [];
    $lastPost = 0;
    $lastModified = 0;
    try {
        $db = \Typecho\Db::get();
        $rows = $db->fetchAll(
            $db->select('created', 'modified')->from('table.contents')
                ->where('type = ?', 'post')->where('status = ?', 'publish')
        );
        foreach ($rows as $row) {
            $created = (int) ($row['created'] ?? 0);
            $modified = (int) ($row['modified'] ?? 0);
            if ($created > 0) {
                $y = (int) date('Y', $created);
                $years[$y] = ($years[$y] ?? 0) + 1;
                $writeDays[date('Y-n-j', $created)] = 1;
                if ($created > $lastPost) {
                    $lastPost = $created;
                }
            }
            if ($modified > $lastModified) {
                $lastModified = $modified;
            }
        }
    } catch (\Throwable $e) {
        /* 聚合失败时下面全部走兜底值 */
    }

    $bars = [];
    /* 年度产出（2026-11-17 调整）：固定展示最近三年，当前年恒在列，
       没有文章的年份计 0（柱子由 side-webinfo.php 渲染成 4px 半透明占位）；
       不再按历史发文年向前扩散的旧口径（最多近 8 年）。 */
    $maxYear = (int) date('Y');
    $minYear = $maxYear - 2;
    for ($y = $minYear; $y <= $maxYear; ++$y) {
        $bars[] = ['year' => $y, 'count' => $years[$y] ?? 0];
    }

    $avg = $posts > 0 ? (int) round($words / $posts) : 0;
    $avgText = $avg >= 10000
        ? rtrim(rtrim(number_format($avg / 10000, 1), '0'), '.') . 'w'
        : ($avg >= 1000 ? rtrim(rtrim(number_format($avg / 1000, 1), '0'), '.') . 'k' : (string) $avg);

    return [
        'words'     => sg_words_format($words),
        'avg'       => $avgText,
        'friends'   => $friends,
        'runDays'   => (int) $uptime['days'],
        'sincePost' => $lastPost > 0 ? max(0, (int) floor((time() - $lastPost) / 86400)) : 0,
        'update'    => $lastModified > 0 ? date('n-j', $lastModified) : '—',
        'bars'      => $bars,
        'writeDays' => count($writeDays),
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

/**
 * 全站已发布文章的总字数（P1 小站资讯 KPI）。
 *
 * 逐篇取 text 字段剥标签后累加：Markdown 存的是源码，strip_tags 对纯文本
 * 基本无副作用；不先转 HTML 是为了省掉每篇一次 Markdown 解析。
 * 静态缓存保证一次请求只算一遍；失败回 0。
 */
function sg_total_words(): int
{
    return (int) sg_stats_disk_cache('total-words', static function (): int {
        $count = 0;
        try {
            $db = \Typecho\Db::get();
            $rows = $db->fetchAll(
                $db->select(['table.contents.text' => 'text'])
                    ->from('table.contents')
                    ->where('type = ?', 'post')
                    ->where('status = ?', 'publish')
            );
            foreach ($rows as $row) {
                $text = (string) ($row['text'] ?? '');
                if (strncmp($text, '<!--markdown-->', 15) === 0) {
                    $text = substr($text, 15);
                }
                $text = (string) preg_replace('/\s+/u', '', strip_tags($text));
                $count += function_exists('mb_strlen') ? (int) mb_strlen($text, 'UTF-8') : strlen($text);
            }
        } catch (\Throwable $e) {
            $count = 0;
        }
        return $count;
    });
}

/** 字数展示格式：过万写成「x.x 万」 */
function sg_words_format(int $chars): string
{
    if ($chars >= 10000) {
        return rtrim(rtrim(number_format($chars / 10000, 1), '0'), '.') . ' 万';
    }
    return number_format($chars);
}

/**
 * 本年度产出：今年 1 月 1 日以来发布的普通文章数（不含碎语）。
 * 碎语按 cid 集合在 PHP 侧剔除，避免连表。
 */
function sg_year_posts(): int
{
    try {
        $db = \Typecho\Db::get();
        $start = mktime(0, 0, 0, 1, 1, (int) date('Y'));
        $rows = $db->fetchAll(
            $db->select(['table.contents.cid' => 'cid'])
                ->from('table.contents')
                ->where('type = ?', 'post')
                ->where('status = ?', 'publish')
                ->where('created >= ?', $start !== false ? $start : 0)
        );
        $notes = sg_note_cids();
        $count = 0;
        foreach ($rows as $row) {
            $cid = (int) ($row['cid'] ?? 0);
            if ($cid > 0 && !in_array($cid, $notes, true)) {
                ++$count;
            }
        }
        return $count;
    } catch (\Throwable $e) {
        return 0;
    }
}
