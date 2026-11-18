<?php
/* Isolated integration fixture: no Typecho database or real API credentials. */
$mode = $argv[1];
$endpoint = $argv[2];
$cacheDir = $argv[3];
define('SG_ROOT', dirname(__DIR__));
function sg_opt(string $key, string $default = ''): string {
    global $endpoint, $mode;
    $cfg = [
        'sgAiProvider'=>'custom', 'sgAiEndpoint'=>$endpoint.'/model', 'sgAiKey'=>'local-test-key', 'sgAiTopicMode'=>str_starts_with($mode, 'daily') ? 'auto' : 'manual',
        'sgAiModel'=>'fixture', 'sgAiTopics'=>"alpha\nbeta\ngamma", 'sgAiCount'=>'2',
        'sgAiFeedsAi'=>$endpoint."/feed/a\n".$endpoint.'/feed/b',
        'sgAiFeedsPaper'=>$endpoint."/feed/c\n".$endpoint.'/feed/d', 'sgBriefingBg'=>'dist/img/ph-1.svg',
    ];
    return $cfg[$key] ?? $default;
}
function sg_cache_file(string $name): string {
    global $cacheDir, $mode;
    return $mode === 'no-cache' ? '' : $cacheDir.DIRECTORY_SEPARATOR.$name;
}
function sg_user_is_admin(): bool { return false; }
if (!in_array($mode, ['page-no-fpm', 'get', 'post'], true)) {
    function fastcgi_finish_request(): bool {
        global $cacheDir;
        file_put_contents($cacheDir.'/finished.json', json_encode(['session'=>session_status()]));
        return true;
    }
}
require SG_ROOT.'/lib/http.php';
require SG_ROOT.'/lib/fields.php';
require SG_ROOT.'/lib/ai.php';
require SG_ROOT.'/lib/ai-daily.php';
if ($mode === 'daily-generate' || $mode === 'daily-force') {
    $issues = sg_ai_generate_daily($mode === 'daily-force');
    echo json_encode(['issues' => $issues, 'saved' => sg_ai_read_daily(), 'file' => sg_ai_daily_file(), 'error' => sg_ai_error()]); exit;
}
if ($mode === 'daily-history') {
    $issues = sg_ai_read_daily();
    foreach ([1, 2] as $ago) { $date = date('Y.m.d', time() - $ago * 86400); $old = array_map(static fn($i) => array_merge($i, ['date' => $date]), $issues); sg_ai_store_file(sg_ai_daily_file(date('Ymd', time() - $ago * 86400)), json_encode(['date' => $date, 'issues' => $old])); }
    echo json_encode(['dates' => array_values(array_unique(array_column(sg_ai_history(), 'date'))), 'count' => count(sg_ai_history())]); exit;
}
$paths = array_map(static fn($theme)=>sg_ai_cache_file($theme), ['alpha','beta','gamma']);
if (str_starts_with($mode, 'seed')) {
    $seededPaths = [];
    foreach (['alpha','beta','gamma'] as $i=>$theme) {
        if ($mode === 'seed-two' && $i === 2) continue;
        $path = $paths[$i];
        if ($mode === 'seed-old' || $mode === 'seed-legacy-old') $path=preg_replace('/-\d{8}\.json$/', '-'.date('Ymd', time()-86400).'.json', $path);
        if ($mode === 'seed-legacy-old') $path=sys_get_temp_dir().DIRECTORY_SEPARATOR.basename($path);
        $seededPaths[] = $path;
        file_put_contents($path, json_encode(['date'=>date('Y.m.d',time()-86400),'theme'=>$theme,'items'=>[['title'=>'cached']]]));
    }
    echo json_encode(['paths'=>array_merge($paths,$seededPaths)]);
    exit;
}
$start=microtime(true);
if ($mode === 'get' || $mode === 'post') {
    $body=$mode==='get' ? sg_http_get($endpoint.'/hang',[],1,$start+.15) : sg_http_post_json($endpoint.'/hang',[],[],1,$start+.15);
    echo json_encode(['elapsed'=>microtime(true)-$start,'empty'=>$body==='']);
    exit;
}
ini_set('session.save_path', $cacheDir);
session_id('sora'.substr(md5($endpoint),0,20));
session_start();
$issues=sg_ai_issues();
echo json_encode(['elapsed'=>microtime(true)-$start,'count'=>count(array_unique(array_column($issues, 'theme'))),'paths'=>$paths]);
flush();
