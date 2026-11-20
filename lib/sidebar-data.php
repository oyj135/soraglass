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
    if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.qweatherapi\.com$/D', $host)) $host = '';
    return [
        'mode' => $mode, 'detail' => sg_opt('sgCalendarDetail', 'on') === 'on',
        'timezone' => sg_sidebar_timezone()->getName(), 'site' => $site,
        'geoKey' => trim(sg_opt('sgGeoKey')), 'weatherKey' => trim(sg_opt('sgWeatherKey')),
        'weatherHost' => $host, 'weather' => sg_opt('sgWeather', 'off') === 'on',
    ];
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
    $nation = $text($ad['nation'] ?? ''); $city = $text($ad['city'] ?? '');
    if ($nation === '' && $city === '') return null;
    $label = implode(' ', array_unique(array_filter([$nation, $text($ad['province'] ?? ''), $city])));
    return array_merge($coords ?? ['latitude' => null, 'longitude' => null], [
        'label' => $label, 'city' => $city, 'nation' => $nation,
        'timezone' => $nation === '中国' ? 'Asia/Shanghai' : '',
        'source' => 'tencent', 'observedAt' => time(),
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

function sg_sidebar_weather(array $city, array $cfg, float $deadline): ?array
{
    $coords = sg_sidebar_coordinates($city['latitude'] ?? null, $city['longitude'] ?? null);
    if (!$coords || $cfg['weatherHost'] === '' || $cfg['weatherKey'] === '' || strpbrk($cfg['weatherKey'], "\r\n") !== false) return null;
    $base = 'https://' . $cfg['weatherHost'];
    $headers = ['Accept: application/json', 'X-QW-Api-Key: ' . $cfg['weatherKey']];
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
    return [
        'city' => mb_substr((string) ($location['name'] ?? $city['city']), 0, 80),
        'temperature' => round((float) $temperature, 1), 'condition' => mb_substr($condition, 0, 80),
        'humidity' => (int) round(max(0, min(1, (float) $humidity)) * 100),
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
    if (($cfg['mode'] === 'visitor' && $cfg['geoKey'] !== '') || $cfg['weather']) {
        $dir = sg_sidebar_private_dir();
        $secret = $dir !== '' ? sg_sidebar_secret($dir) : '';
        $identity = $secret !== '' ? hash_hmac('sha256', sg_sidebar_client_ip() ?: (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), $secret) : '';
        $allowed = $identity !== '' && sg_sidebar_rate_allowed($dir, $identity, $refresh);
        if (!$allowed) $location['reason'] = $dir === '' ? 'private-cache-unavailable' : 'rate-limited';
    }
    $deadline = microtime(true) + 6;
    if ($cfg['mode'] === 'visitor' && $identity !== '' && $cfg['geoKey'] !== '') {
        $ip = sg_sidebar_client_ip();
        $geo = $ip !== '' ? sg_sidebar_cached($dir, 'geo|' . hash('sha256', $cfg['geoKey']) . '|' . $identity, 43200, 43200, $refresh && $allowed,
            static fn() => sg_sidebar_geo($ip, $cfg['geoKey'], $deadline), $allowed) : null;
        if ($geo) {
            $city = $geo;
            $location = array_merge($geo, ['status' => 'ok', 'distance' => $cfg['site'] ? sg_sidebar_distance($cfg['site'], $geo) : null]);
        } else $location['reason'] = $ip === '' ? 'non-public-ip' : 'provider-unavailable';
    } elseif ($cfg['mode'] === 'site' && $city) $location = array_merge($city, ['status' => 'site']);
    if ($cfg['weather'] && $identity !== '' && $city) {
        $key = 'weather|' . hash('sha256', $cfg['weatherHost'] . '|' . $cfg['weatherKey']) . '|' . round((float) $city['latitude'], 2) . ',' . round((float) $city['longitude'], 2);
        $value = sg_sidebar_cached($dir, $key, 900, 7200, false, static fn() => sg_sidebar_weather($city, $cfg, $deadline), $allowed);
        if ($value) {
            $weather = array_merge($value, ['status' => 'ok']);
            $city = array_merge($city, ['latitude' => $value['latitude'], 'longitude' => $value['longitude'], 'timezone' => $value['timezone'], 'city' => $value['city']]);
        }
    }
    // Coordinates are used internally; the browser gets display data, never the visitor IP or precise location.
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
