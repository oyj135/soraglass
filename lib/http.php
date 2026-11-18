<?php
/** A deadline covers the complete cURL attempt; bounded calls never retry via PHP streams. */
function sg_http_timeout_ms(int $timeout, ?float $deadline): int
{
    $milliseconds = max(1, $timeout) * 1000;
    return $deadline === null ? $milliseconds
        : min($milliseconds, max(0, (int) floor(($deadline - microtime(true)) * 1000)));
}

function sg_http_input_valid(string $url, array $headers): bool
{
    if (preg_match('#^https?://#i', $url) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    foreach ($headers as $header) {
        if (strpbrk($header, "\r\n") !== false) {
            return false;
        }
    }
    return true;
}

function sg_http_curl_options(): array
{
    $options = [CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if (defined('CURLOPT_PROTOCOLS')) {
        $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    if (defined('CURLOPT_REDIR_PROTOCOLS')) {
        $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
    }
    // 宝塔的 CA 文件可能在 open_basedir 之外，先检查前缀再访问文件系统。
    $cainfo = (string) ini_get('curl.cainfo');
    if ($cainfo !== '') {
        $allowed = trim((string) ini_get('open_basedir'));
        $ok = $allowed === '';
        foreach (explode(PATH_SEPARATOR, $allowed) as $dir) {
            $dir = rtrim(trim($dir), '/\\');
            if ($dir !== '' && strpos($cainfo, $dir . DIRECTORY_SEPARATOR) === 0) {
                $ok = true;
                break;
            }
        }
        if ($ok && @is_file($cainfo)) {
            $options[CURLOPT_CAINFO] = $cainfo;
        }
    }
    return $options;
}

function sg_http_get(string $url, array $headers = [], int $timeout = 6, ?float $deadline = null): string
{
    if (!sg_http_input_valid($url, $headers)) return '';
    $timeoutMs = sg_http_timeout_ms($timeout, $deadline);
    if ($timeoutMs === 0 || ($deadline !== null && !function_exists('curl_init'))) return '';
    // 流适配器会转发自定义头；只有公开请求可自动重定向。
    $follow = parse_url($url, PHP_URL_USER) === null;
    foreach ($headers as $header) {
        if (!preg_match('/^(Accept|Accept-Language|User-Agent|X-GitHub-Api-Version):/i', $header)) $follow = false;
    }
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';
    $headers = array_merge(['User-Agent: ' . $ua], $headers);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, sg_http_curl_options() + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(3000, $timeoutMs),
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if (is_string($body) && $body !== '') return $body;
        if ($deadline !== null) return '';
    }
    $ctx = stream_context_create(['http' => [
        'timeout' => max(1, $timeout),
        'header' => implode("\r\n", $headers) . "\r\n",
        'follow_location' => (int) $follow,
        'max_redirects' => 3,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    return is_string($body) ? $body : '';
}

function sg_http_post_json(string $url, array $payload, array $headers = [], int $timeout = 20, ?float $deadline = null): string
{
    if (!sg_http_input_valid($url, $headers)) return '';
    $timeoutMs = sg_http_timeout_ms($timeout, $deadline);
    if ($timeoutMs === 0 || ($deadline !== null && !function_exists('curl_init'))) return '';
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($body)) return '';
    $headers = array_merge([
        'User-Agent: soraglass/1.0 (+https://github.com/oyj135/soraglass)',
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($body),
    ], $headers);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, sg_http_curl_options() + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min(5000, $timeoutMs),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        // 保留 API 的错误响应体供上层解析；3xx 不转发凭据或请求正文。
        if (is_string($result) && $result !== '' && (($status >= 200 && $status < 300) || $status >= 400)) return $result;
        if ($deadline !== null || ($status >= 300 && $status < 400)) return '';
    }
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'timeout' => max(1, $timeout),
        'header' => implode("\r\n", $headers) . "\r\n",
        'content' => $body,
        'ignore_errors' => true,
        'follow_location' => 0,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return is_string($result) ? $result : '';
}
