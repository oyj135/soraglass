/** Regression checks through the actual TS/PHP sidebar modules; no external services. */
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { execFileSync } from 'node:child_process';
import ts from 'typescript';
import { Solar, Lunar } from 'lunar-javascript';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const temp = fs.mkdtempSync(path.join(root, 'scripts/.sidebar-test-'));
const php = process.argv[2] || process.env.PHP_BIN || 'php';
let assertions = 0;
const check = (actual, expected) => { assert.deepEqual(actual, expected); assertions++; };
try {
  const source = fs.readFileSync(path.join(root, 'src/scripts/modules/calendar-data.ts'), 'utf8');
  const compiled = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2020, module: ts.ModuleKind.ES2020 } }).outputText;
  const moduleFile = path.join(temp, 'calendar.mjs'); fs.writeFileSync(moduleFile, compiled);
  const dates = await import(pathToFileURL(moduleFile).href);
  const civil = (year, month, day) => ({ year, month, day, hour: 0, minute: 0, second: 0 });
  const library = { Solar, Lunar };
  const sample = civil(2026, 11, 19);
  check(dates.calendarFacts(sample).week, 47);
  check(dates.calendarFacts(sample).dayOfYear, 323);
  check(dates.lunarFacts(sample, library).label, '丙午马年 · 十月十一');
  check(dates.lunarFacts(sample, library).term, '3天后小雪');
  check(dates.lunarFacts(sample, library).festival, { date: '2027-02-06', days: 79 });
  check(dates.lunarFacts(civil(2027, 2, 6), library).festival, { date: '2027-02-06', days: 0 });
  check(dates.lunarFacts(civil(2027, 2, 7), library).festival.date, '2028-01-26');
  check(dates.lunarFacts(civil(2035, 3, 1), library).festival.date, '2036-01-28');
  check(dates.calendarFacts(civil(2021, 1, 1)).week, 53);
  check(dates.calendarFacts(civil(2021, 1, 1)).weekYear, 2020);
  check(dates.calendarFacts(civil(2024, 2, 29)).dayOfYear, 60);
  check(dates.lunarFacts(civil(2023, 3, 22), library).label.includes('闰二月'), true);
  check(dates.civilDate(new Date('2026-11-18T16:00:00Z'), 'Asia/Shanghai').day, 19);
  check(dates.civilDate(new Date('2026-11-18T16:00:00Z'), 'America/New_York').day, 18);
  check(dates.dayOrdinal(2026, 3, 9) - dates.dayOrdinal(2026, 3, 8), 1);
  check(dates.fallbackFestival(civil(2035, 1, 1), ['2034-02-19']), null);

  const fixture = `<?php
define('SG_ROOT', $argv[2] . '/site/usr/themes/soraglass');
define('__TYPECHO_ROOT_DIR__', $argv[2] . '/site');
@mkdir(SG_ROOT, 0700, true);
$options = ['sgSidebarLocation'=>'visitor', 'sgGeoKey'=>'fixture-key', 'sgWeather'=>'on', 'sgWeatherHost'=>'fixture.qweatherapi.com', 'sgWeatherKey'=>'weather-key', 'sgSiteCity'=>'深圳', 'sgSiteLatitude'=>'22.53', 'sgSiteLongitude'=>'113.93', 'sgSiteTimezone'=>'Asia/Shanghai'];
function sg_opt(string $key, string $default=''): string { global $options; return isset($options[$key]) && $options[$key] !== '' ? $options[$key] : $default; }
function sg_options() { return (object) ['index'=>'https://example.invalid/sub/index.php/']; }
function sg_page_url($slug) { return 'https://example.invalid/sub/'; }
$calls=[];
function sg_http_get($url, $headers=[], $timeout=6, $deadline=null): string {
 global $calls; $calls[]=[$url,$headers,$deadline];
 if (strpos($url,'map.qq.com')!==false) { parse_str(parse_url($url,PHP_URL_QUERY),$q); $city=$q['ip']==='8.8.8.8'?'广州':'北京'; return json_encode(['status'=>0,'result'=>['location'=>['lat'=>23.13,'lng'=>113.26],'ad_info'=>['nation'=>'中国','province'=>'广东省','city'=>$city]]]); }
 if (strpos($url,'/geo/')!==false) return json_encode(['code'=>'200','location'=>[['name'=>'广州','lat'=>'23.13','lon'=>'113.26','tz'=>'Asia/Shanghai']]]);
 return gzencode(json_encode(['validTime'=>'2026-11-19T03:00Z','temperature'=>['value'=>18.5,'unit'=>'°C'],'condition'=>['text'=>'多云','code'=>'101'],'humidity'=>0.65,'metadata'=>['attributions'=>['https://example.invalid/attribution']]]));
}
require $argv[1];
$count=0;
function verify($condition,$message) { global $count; if (!$condition) throw new RuntimeException($message); $count++; }
$_SERVER['REMOTE_ADDR']='8.8.8.8'; $_SERVER['HTTP_X_FORWARDED_FOR']='1.1.1.1';
verify(sg_sidebar_client_ip()==='8.8.8.8','forwarded-header');
verify(sg_sidebar_endpoint()==='https://example.invalid/sub/index.php/?sg-sidebar=context','index-route');
$dir=sg_sidebar_private_dir(); verify($dir!=='' && strpos(str_replace('\\\\','/',$dir),str_replace('\\\\','/',SG_ROOT))!==0,'private-dir');
$first=sg_sidebar_context(); verify($first['location']['status']==='ok','location');
verify($first['weather']['humidity']===65 && $first['weather']['temperature']===18.5,'weather-schema');
verify($first['weather']['observedAt']==='2026-11-19T03:00Z','validTime');
verify(count($calls)===3 && strpos($calls[0][0],'ip=8.8.8.8')!==false,'visitor-ip');
verify(!isset($first['location']['latitude']) && strpos(json_encode($first),'fixture-key')===false && strpos(json_encode($first),'8.8.8.8')===false,'privacy');
$second=sg_sidebar_context(); verify(count($calls)===3 && $second['location']['label']===$first['location']['label'],'cache-hit');
$_SERVER['REMOTE_ADDR']='1.1.1.1'; $third=sg_sidebar_context(); verify($third['location']['label']!==$first['location']['label'],'cache-isolation');
$_SERVER['REMOTE_ADDR']='::ffff:8.8.8.8'; verify(sg_sidebar_client_ip()==='8.8.8.8','mapped-ip');
$_SERVER['REMOTE_ADDR']='2001:4860:4860::8888'; verify(sg_sidebar_client_ip()=== $_SERVER['REMOTE_ADDR'],'ipv6');
$_SERVER['REMOTE_ADDR']='127.0.0.1'; verify(sg_sidebar_client_ip()==='','private-ip');
$runs=0; $producer=function() use (&$runs) { $runs++; return null; };
sg_sidebar_cached($dir,'fail-fixture',900,7200,false,$producer); sg_sidebar_cached($dir,'fail-fixture',900,7200,false,$producer);
verify($runs===1,'failure-cooldown');
$success=sg_sidebar_cached($dir,'stale-fixture',900,7200,false,fn()=>['temperature'=>20]);
$cache=$dir.'/data-'.hash('sha256','stale-fixture').'.json'; $data=sg_sidebar_json_read($cache); $data['at']=time()-1000; sg_sidebar_json_write($cache,$data);
$stale=sg_sidebar_cached($dir,'stale-fixture',900,7200,false,fn()=>null);
verify($stale['stale']===true && $stale['temperature']===20,'stale-weather');
verify(sg_sidebar_rate_allowed($dir,'rate-fixture',true) && !sg_sidebar_rate_allowed($dir,'rate-fixture',true),'refresh-rate');
verify(sg_sidebar_distance(['latitude'=>0,'longitude'=>0],['latitude'=>0,'longitude'=>180])===20015,'antipodal');
verify(sg_sidebar_sun(['latitude'=>69.68,'longitude'=>18.94,'city'=>'Tromso','timezone'=>'Europe/Oslo'],new DateTimeImmutable('2026-06-21'))['rise']==='极昼','polar-day');
verify(sg_sidebar_sun(['latitude'=>10,'longitude'=>10,'city'=>'Unknown','timezone'=>''])['status']==='unavailable','unknown-timezone');
$date=sg_sidebar_date(new DateTimeImmutable('2026-11-19T12:00:00+08:00'));
verify($date['target']['days']===79 && $date['progress'][2]['remaining']===3,'date');
verify(sg_sidebar_date(new DateTimeImmutable('2035-03-01'))['target']===null,'exhausted-table');
$options['sgWeatherHost']='localhost:8080'; verify(sg_sidebar_cfg()['weatherHost']==='','host-validation');
echo json_encode(['count'=>$count,'progress'=>$date['progress'],'cacheDir'=>$dir]);
`;
  const phpFile = path.join(temp, 'fixture.php'); fs.writeFileSync(phpFile, fixture);
  const result = JSON.parse(execFileSync(php, [phpFile, path.join(root, 'lib/sidebar-data.php'), temp], { encoding: 'utf8' }));
  assertions += result.count;
  check(dates.calendarFacts({ ...sample, hour: 12 }).progress, result.progress);
  // Only this fixture's dedicated cache is removed, after checking its resolved task prefix.
  if (result.cacheDir && path.basename(result.cacheDir).startsWith('soraglass-sidebar-')) fs.rmSync(result.cacheDir, { recursive: true, force: true });
  console.log(`PASS sidebar data: ${assertions} date, cache, privacy and provider-contract assertions`);
} finally {
  if (temp.startsWith(path.join(root, 'scripts/.sidebar-test-'))) fs.rmSync(temp, { recursive: true, force: true });
}
