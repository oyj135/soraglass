<?php
/** Homepage context: local dates; bounded server-side location/weather requests. */
function sg_sidebar_timezone(string $name = ''): DateTimeZone
{
    try { return new DateTimeZone($name !== '' ? $name : sg_opt('sgCalendarTimezone', 'Asia/Shanghai')); }
    catch (Throwable $e) { return new DateTimeZone('Asia/Shanghai'); }
}

function sg_sidebar_coordinates($latitude, $longitude): ?array
{
    if (!is_numeric($latitude) || !is_numeric($longitude)) return null;
    $lat = (float) $latitude; $lng = (float) $longitude;
    return is_finite($lat) && is_finite($lng) && abs($lat) <= 90 && abs($lng) <= 180
        ? ['latitude' => $lat, 'longitude' => $lng] : null;
}

function sg_sidebar_cfg(): array
{
    $mode = sg_opt('sgSidebarLocation', 'site');
    if (!in_array($mode, ['off', 'site', 'visitor'], true)) $mode = 'off';
    $coords = sg_sidebar_coordinates(sg_opt('sgSiteLatitude'), sg_opt('sgSiteLongitude'));
    $city = trim(sg_opt('sgSiteCity'));
    $site = $coords && $city !== '' ? array_merge($coords, [
        'city' => mb_substr($city, 0, 80), 'label' => mb_substr($city, 0, 80),
        'timezone' => sg_sidebar_timezone(sg_opt('sgSiteTimezone', 'Asia/Shanghai'))->getName(),
        'source' => 'site', 'observedAt' => time(),
    ]) : null;
    $host = strtolower(trim(sg_opt('sgWeatherHost')));
    // A dedicated QWeather host, no user-controlled scheme, port, path or redirect.
    // 和风 2026 起改用账号专属 Host，形如 abc1234xyz.re.qweatherapi.com（可为 1–3 级子域），
    // 旧规则只认单级子域，会把合法 Host 判为非法后静默清空。
    if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.){1,3}qweatherapi\.com$/D', $host)) $host = '';
    // 和风 JWT：kid = 凭据 ID，iss = 开发者 ID（Q 开头），sub = 项目 ID，私钥为 Ed25519 PKCS#8 PEM。
    $id = static function (string $value): string {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $value) ? $value : '';
    };
    $pem = str_replace("\r", '', trim(sg_opt('sgWeatherJwtKey')));
    if ($pem !== '' && (strpos($pem, '-----BEGIN PRIVATE KEY-----') === false || strlen($pem) > 4096)) $pem = '';
    return [
        'mode' => $mode, 'detail' => sg_opt('sgCalendarDetail', 'on') === 'on',
        'timezone' => sg_sidebar_timezone()->getName(), 'site' => $site,
        // 城市名单独留一份：经纬度留空时用它走 GeoAPI 反查坐标（见 sg_sidebar_geo_city）。
        'cityName' => mb_substr($city, 0, 80),
        // 时区设置原值：留空才采用 GeoAPI 返回的 IANA 时区，填了就尊重填写值。
        'siteTimezone' => trim((string) sg_opt('sgSiteTimezone', '')),
        'geoKey' => trim(sg_opt('sgGeoKey')), 'weatherKey' => trim(sg_opt('sgWeatherKey')),
        'weatherHost' => $host, 'weather' => sg_opt('sgWeather', 'off') === 'on',
        'jwt' => [
            'kid' => $id(trim(sg_opt('sgWeatherJwtKid'))),
            'iss' => $id(trim(sg_opt('sgWeatherDevId'))),
            'sub' => $id(trim(sg_opt('sgWeatherProjectId'))),
            'key' => $pem,
        ],
    ];
}

/** JWT 三项与私钥齐备时才算配置完成；缺一项就退回 API KEY。 */
function sg_sidebar_jwt_ready(array $jwt): bool
{
    return $jwt['kid'] !== '' && $jwt['iss'] !== '' && $jwt['sub'] !== '' && $jwt['key'] !== '';
}

/** 当前 PHP 是否能完成 Ed25519 签名：sodium（PHP 7.2+）或 PHP 8.4+ 的 openssl。 */
function sg_sidebar_jwt_supported(): bool
{
    return function_exists('sodium_crypto_sign_seed_keypair') && function_exists('sodium_crypto_sign_detached');
}

/** 从 PKCS#8 PEM 取出 32 字节 Ed25519 种子（DER 末 32 字节）。 */
function sg_sidebar_jwt_seed(string $pem): ?string
{
    $body = preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    $der = is_string($body) ? base64_decode($body, true) : null;
    if (!is_string($der) || strlen($der) < 32 || substr($der, 0, 1) !== "\x30") return null;
    $seed = substr($der, -32);
    return strlen($seed) === 32 ? $seed : null;
}

/** 生成和风 JWT：header {alg,kid} + payload {iss,sub,iat,exp}，Ed25519 签名后 Base64URL 拼接。 */
function sg_sidebar_jwt(array $jwt, ?int $now = null): ?string
{
    if (!sg_sidebar_jwt_ready($jwt) || !sg_sidebar_jwt_supported()) return null;
    $seed = sg_sidebar_jwt_seed($jwt['key']);
    if ($seed === null) return null;
    $pair = sodium_crypto_sign_seed_keypair($seed);
    $secret = sodium_crypto_sign_secretkey($pair);
    $b64 = static fn(string $raw): string => rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    $iat = ($now ?? time()) - 30;
    $data = $b64(json_encode(['alg' => 'EdDSA', 'kid' => $jwt['kid']], JSON_UNESCAPED_SLASHES))
        . '.' . $b64(json_encode(['iss' => $jwt['iss'], 'sub' => $jwt['sub'], 'iat' => $iat, 'exp' => $iat + 900], JSON_UNESCAPED_SLASHES));
    $signature = sodium_crypto_sign_detached($data, $secret);
    return $data . '.' . $b64($signature);
}

/** 和风请求头：JWT 优先（Bearer），否则 API KEY。两者都没有返回 null。 */
function sg_sidebar_weather_auth(array $cfg): ?array
{
    $jwt = $cfg['jwt'] ?? ['kid' => '', 'iss' => '', 'sub' => '', 'key' => ''];
    if (sg_sidebar_jwt_ready($jwt)) {
        $token = sg_sidebar_jwt($jwt);
        return $token === null ? null : ['Accept: application/json', 'Authorization: Bearer ' . $token];
    }
    if ($cfg['weatherKey'] === '' || strpbrk($cfg['weatherKey'], "\r\n") !== false) return null;
    return ['Accept: application/json', 'X-QW-Api-Key: ' . $cfg['weatherKey']];
}

function sg_sidebar_date(?DateTimeImmutable $now = null): array
{
    $now = ($now ?? new DateTimeImmutable('now'))->setTimezone(sg_sidebar_timezone());
    $today = $now->setTime(0, 0);
    // Civil-calendar fractions: the same ordinal-day formula is used by TS, including DST days.
    $ordinal = static fn(DateTimeImmutable $d): int => (int) (strtotime($d->format('Y-m-d') . ' UTC') / 86400);
    $day = $ordinal($today);
    $fraction = ((int) $now->format('H') * 3600 + (int) $now->format('i') * 60 + (int) $now->format('s')) / 86400;
    $monday = $today->modify('-' . ((int) $today->format('N') - 1) . ' days');
    $yearStart = $today->setDate((int) $today->format('Y'), 1, 1);
    $monthStart = $today->setDate((int) $today->format('Y'), (int) $today->format('n'), 1);
    $progress = [];
    foreach ([[$yearStart, $yearStart->modify('+1 year')], [$monthStart, $monthStart->modify('+1 month')], [$monday, $monday->modify('+7 days')]] as [$start, $end]) {
        $a = $ordinal($start); $b = $ordinal($end);
        // 两位小数：与源站 aside-calendar.js 的 (Math.round(pct*10000)/100) 逐位一致，
        // 60 秒刷新时进度条才会缓慢前移；整数会让它在 1% 刻度上停住。
        $progress[] = ['percent' => round(max(0, min(100, ($day + $fraction - $a) / ($b - $a) * 100)), 2), 'remaining' => $b - $day - 1];
    }
    $dates = ['2026-02-17', '2027-02-06', '2028-01-26', '2029-02-13', '2030-02-03', '2031-01-23', '2032-02-11', '2033-01-31', '2034-02-19'];
    $target = null;
    foreach ($dates as $date) {
        if ($date >= $today->format('Y-m-d')) {
            $target = ['date' => $date, 'days' => $ordinal(new DateTimeImmutable($date, $today->getTimezone())) - $day]; break;
        }
    }
    return ['now' => $now, 'progress' => $progress, 'target' => $target, 'fallbackDates' => $dates];
}

function sg_sidebar_endpoint(): string
{
    $options = sg_options();
    $index = is_object($options) ? (string) $options->index : '';
    if ($index === '') $index = sg_page_url('');
    return $index . (strpos($index, '?') === false ? '?' : '&') . 'sg-sidebar=context';
}

/** Trust REMOTE_ADDR only. Nginx real_ip must restore it before PHP behind a proxy. */
function sg_sidebar_client_ip(): string
{
    $raw = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (strpos($raw, '::ffff:') === 0 && filter_var(substr($raw, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $raw = substr($raw, 7);
    return filter_var($raw, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? $raw : '';
}

/** Never use sg_cache_file's public dist/ fallback for visitor information. */
function sg_sidebar_private_dir(): string
{
    static $dir = null;
    if ($dir !== null) return $dir;
    $dir = '';
    $temp = @realpath(sys_get_temp_dir());
    if (!$temp) return $dir;
    $siteRoot = defined('__TYPECHO_ROOT_DIR__') ? (string) __TYPECHO_ROOT_DIR__ : dirname(SG_ROOT, 3);
    foreach ([$siteRoot, SG_ROOT, (string) ($_SERVER['DOCUMENT_ROOT'] ?? '')] as $root) {
        $resolved = $root !== '' ? @realpath($root) : false;
        if (!$resolved) continue;
        $base = strtolower(str_replace('\\', '/', rtrim($resolved, '/\\'))) . '/';
        $candidate = strtolower(str_replace('\\', '/', rtrim($temp, '/\\'))) . '/';
        if (strpos($candidate, $base) === 0) return $dir;
    }
    $candidate = $temp . DIRECTORY_SEPARATOR . 'soraglass-sidebar-' . substr(hash('sha256', SG_ROOT . '|' . sg_sidebar_endpoint()), 0, 16);
    if (!is_dir($candidate) && !@mkdir($candidate, 0700, true)) return $dir;
    if (is_link($candidate) || !is_writable($candidate)) return $dir;
    @chmod($candidate, 0700);
    return $dir = $candidate;
}

function sg_sidebar_json_read(string $file): ?array
{
    if ($file === '' || !is_file($file)) return null;
    $raw = @file_get_contents($file);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

function sg_sidebar_json_write(string $file, array $data): bool
{
    $tmp = $file . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || @file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0600);
    if (@rename($tmp, $file)) return true;
    @unlink($tmp); return false;
}

function sg_sidebar_secret(string $dir): string
{
    $handle = @fopen($dir . '/secret', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) { if ($handle) fclose($handle); return ''; }
    $secret = stream_get_contents($handle);
    if (!is_string($secret) || strlen($secret) !== 64) {
        $secret = bin2hex(random_bytes(32)); rewind($handle); ftruncate($handle, 0); fwrite($handle, $secret); fflush($handle); @chmod($dir . '/secret', 0600);
    }
    flock($handle, LOCK_UN); fclose($handle); return $secret;
}

/** One compact rate ledger and a bounded cache avoid a file per rejected visitor. */
function sg_sidebar_rate_allowed(string $dir, string $identity, bool $refresh): bool
{
    $file = $dir . '/rate.json';
    $handle = @fopen($dir . '/rate.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) { if ($handle) fclose($handle); return false; }
    try {
        $now = time(); $ledger = sg_sidebar_json_read($file) ?? ['start' => $now, 'total' => 0, 'visitors' => []];
        if ($now - (int) $ledger['start'] >= 60) $ledger = ['start' => $now, 'total' => 0, 'visitors' => []];
        $visitor = $ledger['visitors'][$identity] ?? ['count' => 0, 'refresh' => 0];
        if ((int) $ledger['total'] >= 120 || (int) $visitor['count'] >= 12 || ($refresh && $now - (int) $visitor['refresh'] < 15)) return false;
        $ledger['total']++; $visitor['count']++;
        if ($refresh) $visitor['refresh'] = $now;
        $ledger['visitors'][$identity] = $visitor;
        return sg_sidebar_json_write($file, $ledger);
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

/** Cache a bounded upstream value; failures have a separate cooldown and optional stale value. */
function sg_sidebar_cached(string $dir, string $key, int $ttl, int $staleTtl, bool $refresh, callable $producer, bool $canRequest = true): ?array
{
    $file = $dir . '/data-' . hash('sha256', $key) . '.json';
    $cached = sg_sidebar_json_read($file);
    $age = $cached ? max(0, time() - (int) ($cached['at'] ?? 0)) : PHP_INT_MAX;
    if ($cached && $age < $ttl && (!$refresh || $age < 15)) return array_merge($cached['value'], ['stale' => false]);
    $fallback = $cached && $age <= $staleTtl ? array_merge($cached['value'], ['stale' => true]) : null;
    if (!$canRequest) return $fallback;
    if (is_file($file . '.fail') && time() - (int) filemtime($file . '.fail') < 60) return $fallback;
    if (!is_file($file . '.lock')) {
        $entries = glob($dir . '/data-*.lock') ?: [];
        if (count($entries) >= 512) {
            foreach ($entries as $old) if (time() - (int) filemtime($old) > 43200) {
                @unlink(substr($old, 0, -5)); @unlink(substr($old, 0, -5) . '.fail'); @unlink($old);
            }
            if (count(glob($dir . '/data-*.lock') ?: []) >= 512) return $fallback;
        }
    }
    $handle = @fopen($file . '.lock', 'c+');
    if (!$handle || !flock($handle, LOCK_EX | LOCK_NB)) { if ($handle) fclose($handle); return $fallback; }
    try {
        @touch($file . '.lock');
        $value = $producer();
        if (!is_array($value)) { @file_put_contents($file . '.fail', 'unavailable'); return $fallback; }
        if (!sg_sidebar_json_write($file, ['at' => time(), 'value' => $value])) return $fallback;
        @unlink($file . '.fail');
        return array_merge($value, ['stale' => false]);
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function sg_sidebar_get_json(string $url, array $headers, float $deadline): ?array
{
    $raw = sg_http_get($url, $headers, 3, $deadline);
    if (substr($raw, 0, 2) === "\x1f\x8b") $raw = function_exists('gzdecode') ? (gzdecode($raw) ?: '') : '';
    $data = $raw !== '' ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

function sg_sidebar_geo(string $ip, string $key, float $deadline): ?array
{
    if ($ip === '' || $key === '' || strpbrk($key, "\r\n") !== false) return null;
    $url = 'https://apis.map.qq.com/ws/location/v1/ip?' . http_build_query(['ip' => $ip, 'key' => $key], '', '&', PHP_QUERY_RFC3986);
    // X-SG-Private also ensures the existing HTTP helper never follows a credential-bearing redirect.
    $data = sg_sidebar_get_json($url, ['Accept: application/json', 'X-SG-Private: 1'], $deadline);
    if (!$data || (int) ($data['status'] ?? -1) !== 0) return null;
    $result = $data['result'] ?? []; $ad = $result['ad_info'] ?? [];
    $coords = sg_sidebar_coordinates($result['location']['lat'] ?? null, $result['location']['lng'] ?? null);
    $text = static fn($v): string => is_scalar($v) ? mb_substr(trim((string) $v), 0, 80) : '';
    $nation = $text($ad['nation'] ?? ''); $province = $text($ad['province'] ?? '');
    $city = $text($ad['city'] ?? ''); $district = $text($ad['district'] ?? '');
    if ($nation === '' && $city === '') return null;
    // 2026-11-20：口径对齐源站 —— 国内拼「省 市 区」，国外只写国家名。
    // 逐级过滤空值，避免出现「杭州市 杭州市」这类重复段。
    $label = $nation === '中国'
        ? implode(' ', array_unique(array_filter([$province, $city, $district])))
        : $nation;
    if ($label === '') $label = implode(' ', array_unique(array_filter([$nation, $province, $city])));
    // IP 只回显给访客本人（源站把 IP 印在欢迎语末尾）；经纬度仍然不下发。
    return array_merge($coords ?? ['latitude' => null, 'longitude' => null], [
        'label' => $label, 'city' => $city, 'nation' => $nation,
        'province' => $province, 'district' => $district, 'ip' => $text($result['ip'] ?? ''),
        'timezone' => $nation === '中国' ? 'Asia/Shanghai' : '',
        'source' => 'tencent', 'observedAt' => time(),
    ]);
}

/**
 * 和风 GeoAPI 按城市名解析坐标与时区（2026-11-20）。
 *
 * 和风 `/{host}/geo/v2/city/lookup` 的 location 既吃经纬度也吃城市名/拼音/LocationID，
 * 返回的 lat / lon / tz 正好就是「站长城市纬度 / 经度 / 时区」三个输入框要填的东西。
 * 凭据与 Host 与天气同一套，所以这里只做代理、不引入新配置；密钥始终留在服务端。
 * 城市名支持「杭州市西湖区」这类「市 + 区县」写法：拆成区县主词查询、再按市级名过滤（见下方）。
 */
function sg_sidebar_geo_city(string $name, array $cfg, float $deadline, ?array $headers = null): ?array
{
    $name = trim($name);
    if ($name === '' || $cfg['weatherHost'] === '') return null;
    $headers = $headers ?? sg_sidebar_weather_auth($cfg);
    if ($headers === null) return null;
    $lookup = static function (string $location, int $number) use ($cfg, $headers, $deadline): ?array {
        $data = sg_sidebar_get_json('https://' . $cfg['weatherHost'] . '/geo/v2/city/lookup?' . http_build_query([
            'location' => $location, 'lang' => 'zh', 'number' => $number,
        ], '', '&', PHP_QUERY_RFC3986), $headers, $deadline);
        $list = $data['location'] ?? null;
        return ($data['code'] ?? '') === '200' && is_array($list) && $list ? $list : null;
    };
    // 2026-11-20：支持「杭州市西湖区」这类「市 + 区县」写法。
    // GeoAPI 对整串的模糊匹配命中率低（常直接返回空列表），但它会把每条结果的 adm1/adm2 一并给出，
    // 所以拆成「主词 = 区县名」去查，再用市级名过滤结果；拆完仍查不到时回退整串再试一次。
    // 不用 adm 参数：它按文档只吃英文/拼音区划名，中文市名靠结果过滤更稳。
    $main = $name; $admHint = '';
    $cut = mb_strpos($name, '市');
    if ($cut !== false && ($tail = trim(mb_substr($name, $cut + 1))) !== '') {
        $head = trim(mb_substr($name, 0, $cut));
        $admHint = preg_match('/(?:省|自治区|特别行政区)([^省]*)$/u', $head, $m) ? trim($m[1]) : $head;
        $admHint = (string) preg_replace('/市$/u', '', $admHint);
        $main = $tail;
    }
    $list = $lookup($main, $admHint !== '' ? 10 : 1) ?? ($main !== $name ? $lookup($name, 1) : null);
    if (!$list) return null;
    $location = $list[0];
    if ($admHint !== '') {
        foreach ($list as $item) {
            $adm2 = (string) ($item['adm2'] ?? ''); $adm1 = (string) ($item['adm1'] ?? '');
            if (($adm2 !== '' && (mb_strpos($adm2, $admHint) !== false || mb_strpos($admHint, $adm2) !== false))
                || ($adm1 !== '' && mb_strpos($adm1, $admHint) !== false)) { $location = $item; break; }
        }
    }
    $point = sg_sidebar_coordinates($location['lat'] ?? null, $location['lon'] ?? null);
    if (!$point) return null;
    $tz = (string) ($location['tz'] ?? '');
    try { new DateTimeZone($tz); } catch (Throwable $e) { $tz = ''; }
    $label = (string) ($location['name'] ?? $name);
    return array_merge($point, [
        'city' => mb_substr($label, 0, 80), 'label' => mb_substr($label, 0, 80),
        'timezone' => $tz !== '' ? $tz : sg_sidebar_timezone()->getName(),
        'adm' => trim(implode(' · ', array_filter([(string) ($location['adm1'] ?? ''), (string) ($location['adm2'] ?? '')]))),
        'source' => 'geo', 'observedAt' => time(),
    ]);
}

function sg_sidebar_distance(array $a, array $b): ?int
{
    $x = sg_sidebar_coordinates($a['latitude'] ?? null, $a['longitude'] ?? null);
    $y = sg_sidebar_coordinates($b['latitude'] ?? null, $b['longitude'] ?? null);
    if (!$x || !$y) return null;
    $lat1 = deg2rad($x['latitude']); $lat2 = deg2rad($y['latitude']);
    $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(deg2rad($y['longitude'] - $x['longitude']) / 2) ** 2;
    return (int) round(6371 * 2 * asin(sqrt(min(1.0, max(0.0, $h)))));
}

function sg_sidebar_sun(?array $city, ?DateTimeImmutable $now = null): array
{
    $coords = $city ? sg_sidebar_coordinates($city['latitude'] ?? null, $city['longitude'] ?? null) : null;
    if (!$coords || empty($city['timezone'])) return ['status' => 'unavailable'];
    try { $timezone = new DateTimeZone($city['timezone']); } catch (Throwable $e) { return ['status' => 'unavailable']; }
    $today = ($now ?? new DateTimeImmutable('now'))->setTimezone($timezone)->setTime(0, 0);
    $sun = date_sun_info($today->getTimestamp(), $coords['latitude'], $coords['longitude']);
    // 极昼极夜（true/false）不输出该行，与源站 sunTimes 返回 null 的处理一致。
    if (!is_int($sun['sunrise']) || !is_int($sun['sunset'])) return ['status' => 'unavailable', 'reason' => 'polar'];
    $format = static fn(int $value): string => (new DateTimeImmutable('@' . $value))->setTimezone($timezone)->format('H:i');
    return ['status' => 'ok', 'date' => $today->format('Y-m-d'), 'timezone' => $timezone->getName(), 'city' => $city['city'], 'rise' => $format($sun['sunrise']), 'set' => $format($sun['sunset'])];
}

function sg_sidebar_weather(array $city, array $cfg, float $deadline, ?array $headers = null): ?array
{
    $coords = sg_sidebar_coordinates($city['latitude'] ?? null, $city['longitude'] ?? null);
    if (!$coords || $cfg['weatherHost'] === '') return null;
    $headers = $headers ?? sg_sidebar_weather_auth($cfg);
    if ($headers === null) return null;
    $base = 'https://' . $cfg['weatherHost'];
    // GeoAPI resolves a city and its WGS84 weather coordinates/timezone, rather than blindly reusing map coordinates.
    $geo = sg_sidebar_get_json($base . '/geo/v2/city/lookup?' . http_build_query([
        'location' => $coords['longitude'] . ',' . $coords['latitude'], 'lang' => 'zh', 'number' => 1,
    ], '', '&', PHP_QUERY_RFC3986), $headers, $deadline);
    $location = $geo['location'][0] ?? null;
    if (($geo['code'] ?? '') !== '200' || !is_array($location)) return null;
    $point = sg_sidebar_coordinates($location['lat'] ?? null, $location['lon'] ?? null);
    if (!$point) return null;
    $data = sg_sidebar_get_json($base . '/weather/v1/current/' . $point['latitude'] . '/' . $point['longitude'] . '?lang=zh', $headers, $deadline);
    // Current Weather API uses structured quantities, not the legacy v7 now.temp response.
    $temperature = $data['temperature']['value'] ?? null;
    $condition = $data['condition']['text'] ?? null;
    $humidity = $data['humidity'] ?? null;
    if (!is_numeric($temperature) || !is_string($condition) || !is_numeric($humidity)) return null;
    $tz = (string) ($location['tz'] ?? '');
    try { new DateTimeZone($tz); } catch (Throwable $e) { $tz = ''; }
    // 2026-11-20：日历卡天气块补三档实返数据（体感 / 风向风级 / 能见度）。
    // 全部按「有就带、没有就不带」处理 —— 接口降级时不写空键，前端缺字段即不渲染。
    $feelsLike = $data['feelsLike']['value'] ?? null;
    $windScale = $data['wind']['scale'] ?? null;
    $compass = strtolower((string) ($data['wind']['direction']['compass'] ?? ''));
    $visibility = $data['visibility']['value'] ?? null;
    $compassMap = ['n' => '北风', 'nne' => '东北偏北风', 'ne' => '东北风', 'ene' => '东北偏东风',
        'e' => '东风', 'ese' => '东南偏东风', 'se' => '东南风', 'sse' => '东南偏南风',
        's' => '南风', 'ssw' => '西南偏南风', 'sw' => '西南风', 'wsw' => '西南偏西风',
        'w' => '西风', 'wnw' => '西北偏西风', 'nw' => '西北风', 'nnw' => '西北偏北风'];
    return [
        'city' => mb_substr((string) ($location['name'] ?? $city['city']), 0, 80),
        'temperature' => round((float) $temperature, 1), 'condition' => mb_substr($condition, 0, 80),
        'humidity' => (int) round(max(0, min(1, (float) $humidity)) * 100),
        'feelsLike' => is_numeric($feelsLike) ? round((float) $feelsLike, 1) : null,
        'windDir' => $compassMap[$compass] ?? '',
        'windScale' => is_numeric($windScale) ? (int) $windScale : null,
        'visibility' => is_numeric($visibility) ? round((float) $visibility / 1000, 1) : null,
        'icon' => (string) ($data['condition']['code'] ?? ''),
        'observedAt' => (string) ($data['validTime'] ?? ''), 'fetchedAt' => time(),
        'timezone' => $tz, 'latitude' => $point['latitude'], 'longitude' => $point['longitude'],
        'source' => 'qweather',
        'attributions' => array_values(array_filter(array_slice((array) ($data['metadata']['attributions'] ?? []), 0, 6), static fn($url) => is_string($url) && strlen($url) <= 512 && preg_match('#^https://#', $url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_USER) === null)),
    ];
}

function sg_sidebar_context(bool $refresh = false): array
{
    $cfg = sg_sidebar_cfg(); $city = $cfg['site'];
    $location = ['status' => $cfg['mode'] === 'off' ? 'disabled' : 'unavailable', 'reason' => 'not-configured'];
    $weather = ['status' => $cfg['weather'] ? 'unavailable' : 'disabled'];
    $dir = ''; $identity = ''; $allowed = false;
    // 站点模式只填了城市名、没填经纬度时也要备好缓存目录与限流身份 —— 解析结果要落盘缓存。
    // 欢迎卡关闭（mode=off）但开了天气时同样要解析：天气只需要坐标，与欢迎卡开关无关。
    $needsGeoLookup = $city === null && $cfg['cityName'] !== '' && ($cfg['mode'] !== 'off' || $cfg['weather']);
    if (($cfg['mode'] === 'visitor' && $cfg['geoKey'] !== '') || $cfg['weather'] || $needsGeoLookup) {
        $dir = sg_sidebar_private_dir();
        $secret = $dir !== '' ? sg_sidebar_secret($dir) : '';
        $identity = $secret !== '' ? hash_hmac('sha256', sg_sidebar_client_ip() ?: (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $secret) : '';
        $allowed = $identity !== '' && sg_sidebar_rate_allowed($dir, $identity, $refresh);
        if (!$allowed) $location['reason'] = $dir === '' ? 'private-cache-unavailable' : 'rate-limited';
    }
    $deadline = microtime(true) + 6;
    // 城市名 → 坐标兜底（2026-11-20）：站长城市纬度/经度留空时，用和风 GeoAPI 反查并缓存 30 天。
    // 这样后台只填「杭州」也能有日出日落、距离与天气；缓存键含城市名，改城市会立即重新解析。
    if ($needsGeoLookup) {
        $auth = $cfg['weatherHost'] !== '' ? sg_sidebar_weather_auth($cfg) : null;
        if ($auth === null) {
            $location['reason'] = 'geo-credential-missing';
        } else {
            $value = sg_sidebar_cached($dir, 'geo-city|' . hash('sha256', $cfg['weatherHost'] . '|' . $cfg['cityName']), 2592000, 2592000, $refresh && $allowed,
                static fn() => sg_sidebar_geo_city($cfg['cityName'], $cfg, $deadline, $auth), $allowed);
            if ($value) {
                // 显式填写的时区优先；没填时用 GeoAPI 返回的 IANA 时区。
                $city = $cfg['siteTimezone'] !== '' ? array_merge($value, ['timezone' => $cfg['timezone']]) : $value;
            } else {
                $location['reason'] = $allowed ? 'geo-unavailable' : 'rate-limited';
            }
        }
    }
    if ($cfg['mode'] === 'visitor' && $identity !== '' && $cfg['geoKey'] !== '') {
        $ip = sg_sidebar_client_ip();
        $geo = $ip !== '' ? sg_sidebar_cached($dir, 'geo|' . hash('sha256', $cfg['geoKey']) . '|' . $identity, 43200, 43200, $refresh && $allowed,
            static fn() => sg_sidebar_geo($ip, $cfg['geoKey'], $deadline), $allowed) : null;
        if ($geo) {
            $city = $geo;
            $location = array_merge($geo, ['status' => 'ok', 'distance' => $cfg['site'] ? sg_sidebar_distance($cfg['site'], $geo) : null]);
        } else $location['reason'] = $ip === '' ? 'non-public-ip' : 'provider-unavailable';
    } elseif ($cfg['mode'] === 'site' && $city) $location = array_merge($city, ['status' => 'site']);
    // 天气失败原因要能回读：/index.php?sg-sidebar=context 里的 weather.reason 直接说明卡在哪一步，
    // 否则 host 非法、凭据缺失、sodium 不可用、上游 401、限流全都会显示同一句「天气暂不可用」。
    if ($cfg['weather']) {
        if (!$city) {
            $weather['reason'] = 'city-missing';
        } elseif ($cfg['weatherHost'] === '') {
            $weather['reason'] = 'host-invalid';
        } elseif (($authHeaders = sg_sidebar_weather_auth($cfg)) === null) {
            if (!sg_sidebar_jwt_ready($cfg['jwt'])) $weather['reason'] = 'credential-missing';
            elseif (!sg_sidebar_jwt_supported()) $weather['reason'] = 'signing-unavailable';
            elseif (sg_sidebar_jwt_seed($cfg['jwt']['key']) === null) $weather['reason'] = 'key-unreadable';
            else $weather['reason'] = 'credential-invalid';
        } elseif ($identity === '') {
            $weather['reason'] = 'cache-unavailable';
        } else {
            // 凭据标识随认证方式变化（JWT 用 kid，API KEY 用 key 的哈希），换认证不会吃到旧缓存。
            $credential = sg_sidebar_jwt_ready($cfg['jwt']) ? 'jwt|' . $cfg['jwt']['kid'] : 'key|' . $cfg['weatherKey'];
            $key = 'weather|' . hash('sha256', $cfg['weatherHost'] . '|' . $credential) . '|' . round((float) $city['latitude'], 2) . ',' . round((float) $city['longitude'], 2);
            $value = sg_sidebar_cached($dir, $key, 900, 7200, false, static fn() => sg_sidebar_weather($city, $cfg, $deadline, $authHeaders), $allowed);
            if ($value) {
                $weather = array_merge($value, ['status' => 'ok']);
                $city = array_merge($city, ['latitude' => $value['latitude'], 'longitude' => $value['longitude'], 'timezone' => $value['timezone'], 'city' => $value['city']]);
            } else {
                $weather['reason'] = $allowed ? 'provider-unavailable' : 'rate-limited';
            }
        }
    }
    // 经纬度只在服务端使用，浏览器只拿展示字段；IP 仅回显给访客本人（源站欢迎语末尾同样印 IP）。
    unset($location['latitude'], $location['longitude'], $weather['latitude'], $weather['longitude']);
    return ['location' => $location, 'weather' => $weather, 'sun' => sg_sidebar_sun($city), 'generatedAt' => time()];
}

function sg_sidebar_dispatch(): void
{
    if (!isset($_GET['sg-sidebar']) || !is_string($_GET['sg-sidebar']) || $_GET['sg-sidebar'] !== 'context') return;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); echo '{"error":"method-not-allowed"}'; exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;
    try { $data = sg_sidebar_context(($_GET['refresh'] ?? '') === '1'); }
    catch (Throwable $e) { $data = ['location' => ['status' => 'unavailable'], 'weather' => ['status' => 'unavailable'], 'sun' => ['status' => 'unavailable'], 'generatedAt' => time()]; }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); exit;
}

/**
 * 后台「按城市名查询坐标」的只读端点：`/?sg-geo=杭州`。
 *
 * 只返回 name / lat / lon / tz，不写任何配置、不改任何状态，所以不需要 _ 令牌；
 * 但仍要求管理员身份，密钥也只在服务端参与请求（浏览器只看到一个城市名）。
 */
function sg_sidebar_geo_dispatch(): void
{
    if (!isset($_GET['sg-geo']) || !is_string($_GET['sg-geo'])) return;
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        // Typecho 的 Response 在 shutdown 时会把状态码写回 200，所以两边都要设。
        http_response_code(405);
        try { \Typecho\Response::getInstance()->setStatus(405); } catch (\Throwable $e) {}
        header('Allow: GET'); echo '{"error":"method-not-allowed"}'; exit;
    }
    if (!sg_user_is_admin()) {
        http_response_code(403);
        try { \Typecho\Response::getInstance()->setStatus(403); } catch (\Throwable $e) {}
        echo '{"error":"forbidden"}'; exit;
    }
    $name = trim(preg_replace('/\s+/u', ' ', (string) $_GET['sg-geo']));
    if ($name === '' || mb_strlen($name) > 40) { echo '{"error":"invalid-city"}'; exit; }
    $cfg = sg_sidebar_cfg();
    if ($cfg['weatherHost'] === '') { echo '{"error":"host-missing"}'; exit; }
    $auth = sg_sidebar_weather_auth($cfg);
    if ($auth === null) { echo '{"error":"credential-missing"}'; exit; }
    $value = sg_sidebar_geo_city($name, $cfg, microtime(true) + 6, $auth);
    if (!$value) { echo '{"error":"lookup-failed"}'; exit; }
    echo json_encode([
        'ok' => true, 'name' => $value['city'], 'adm' => $value['adm'] ?? '',
        'latitude' => $value['latitude'], 'longitude' => $value['longitude'], 'timezone' => $value['timezone'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG); exit;
}
