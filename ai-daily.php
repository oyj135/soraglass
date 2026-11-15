<?php
/** Optional server cron entry: php /path/to/usr/themes/soraglass/ai-daily.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$site = $argv[1] ?? dirname(__DIR__, 3);
if (!is_file($site . '/config.inc.php')) { fwrite(STDERR, "Typecho root not found\n"); exit(1); }
// Typecho derives rootUrl from the request; cron has no HTTP request context.
$_SERVER += ['HTTP_HOST' => 'localhost', 'SERVER_NAME' => 'localhost', 'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'SCRIPT_NAME' => '/index.php', 'SCRIPT_FILENAME' => $site . '/index.php', 'SERVER_PORT' => 80];
require $site . '/config.inc.php';
$db = \Typecho\Db::get();
$siteUrl = (string) ($db->fetchRow($db->select('value')->from('table.options')->where('name = ?', 'siteUrl')->where('user = ?', 0))['value'] ?? 'http://localhost/');
if (!defined('__TYPECHO_ROOT_URL__')) { define('__TYPECHO_ROOT_URL__', rtrim($siteUrl, '/')); }
if (!defined('__TYPECHO_URL_PREFIX__')) { define('__TYPECHO_URL_PREFIX__', (parse_url($siteUrl, PHP_URL_SCHEME) ?: 'http') . '://' . (parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost')); }
$_SERVER['HTTP_HOST'] = parse_url($siteUrl, PHP_URL_HOST) ?: 'localhost';
\Widget\Init::alloc(); require_once __DIR__ . '/functions.php';
if (!sg_ai_enabled()) { fwrite(STDERR, "AI model or RSS configuration missing\n"); exit(1); }
if (sg_ai_cfg()['topicMode'] === 'manual') { fwrite(STDERR, "Select automatic daily themes in theme settings first\n"); exit(1); }
$lockFile = sg_cache_file('ai-update-' . md5(SG_ROOT) . '.lock');
if (!$lockFile || !($lock = @fopen($lockFile, 'c'))) { fwrite(STDERR, "Daily cache directory is not writable\n"); exit(1); }
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { echo "Daily generation already running\n"; exit; }
try { $GLOBALS['SG_AI_DEADLINE'] = microtime(true) + 35; $GLOBALS['SG_AI_FEED_DEADLINE'] = microtime(true) + 6; $issues = sg_ai_generate_daily(); echo $issues ? 'Daily themes ready: ' . count($issues) . "\n" : "Daily generation incomplete; previous issue retained\n"; }
finally { flock($lock, LOCK_UN); fclose($lock); }
exit($issues ? 0 : 1);
