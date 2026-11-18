import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync, execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { createServer } from 'node:http';
import { writeZip, readZipEntry } from './zip.mjs';
const root = path.resolve(import.meta.dirname, '..');
const php = process.env.PHP_BIN || 'php';
const run = promisify(execFile);
function runPhp(code, args = []) {
  return execFileSync(php, [...args, '-r', code], { cwd: root, encoding: 'utf8' }).trim();
}
test('ZIP local and central timestamps use a valid modification date', () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sg-zip-test-'));
  try {
    const file = path.join(dir, 'test.zip');
    const mtime = new Date(2026, 10, 18, 10, 12, 34);
    writeZip(file, [{ name: 'theme/测试.txt', data: Buffer.from('hello'), mtime }]);
    const bytes = fs.readFileSync(file);
    const central = bytes.readUInt32LE(bytes.length - 6);
    const date = ((2026 - 1980) << 9) | (11 << 5) | 18;
    const time = (10 << 11) | (12 << 5) | 17;
    assert.equal(bytes.readUInt16LE(12), date);
    assert.equal(bytes.readUInt16LE(10), time);
    assert.equal(bytes.readUInt16LE(central + 14), date);
    assert.equal(bytes.readUInt16LE(central + 12), time);
    assert.equal(readZipEntry(file, 'theme/测试.txt'), 'hello');
  } finally { fs.rmSync(dir, { recursive: true, force: true }); }
});
for (const group of ['visitor', 'subscriber', 'contributor', 'editor', 'administrator']) {
  test(`admin boundary rejects non-administrators: ${group}`, () => {
    const result = runPhp(`namespace Widget { class User { static function alloc() { return new self; } function hasLogin() { return '${group}' !== 'visitor'; } function pass($role, $return) { return '${group}' === $role; } } } namespace { require 'lib/config.php'; echo sg_user_is_admin() ? 'yes' : 'no'; }`);
    assert.equal(result, group === 'administrator' ? 'yes' : 'no');
  });
}
test('site statistics share one query batch per PHP request', () => {
  const result = runPhp(`namespace Typecho { class Db { static $queries = 0; static function get() { return new self; } function select($fields) { return $this; } function from($table) { return $this; } function where(...$args) { return $this; } function fetchObject($query) { self::$queries++; return (object)['num'=>7]; } } } namespace { function sg_gallery_catalog() { return ['photoTotal'=>3]; } function sg_notes_count() { return 2; } require 'lib/stats.php'; $a=sg_stats(); $b=sg_stats(); echo json_encode([$a === $b, \\Typecho\\Db::$queries, $a]); }`);
  assert.deepEqual(JSON.parse(result), [true, 3, { posts: '7', notes: '2', photos: '3', tags: '7', categories: '7' }]);
});
for (const adapter of ['curl', 'streams']) {
  test(`HTTP ${adapter} keeps public redirects but never forwards credentials or POST data`, async () => {
    const hits = [];
    const server = createServer((req, res) => {
      hits.push({ path: req.url, method: req.method });
      req.resume();
      if (req.url === '/redirect') { res.writeHead(307, { Location: '/target' }); res.end(); }
      else res.end('ok');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const base = `http://127.0.0.1:${server.address().port}`;
    const invoke = async code => {
      const { stdout, stderr } = await run(php, [...(adapter === 'streams' ? ['-n'] : []), '-r', `require 'lib/http.php'; ${code}`], { cwd: root, timeout: 5000 });
      assert.equal(stderr, '');
      return stdout.trim();
    };
    try {
      assert.equal(await invoke(`echo function_exists('curl_init') ? 'curl' : 'streams';`), adapter);
      assert.equal(await invoke(`echo sg_http_get('${base}/redirect', ['Accept: text/plain']);`), 'ok');
      assert.equal(hits.filter(h => h.path === '/target').length, 1);
      hits.length = 0;
      assert.equal(await invoke(`echo sg_http_get('${base}/target', ["X-Test: value\\r\\nInjected: value"]);`), '');
      assert.equal(hits.length, 0);
      for (const header of ['Authorization: Bearer fixture', 'x-api-key: fixture']) {
        await invoke(`echo sg_http_get('${base}/redirect', ['${header}']);`);
        assert.equal(hits.filter(h => h.path === '/target').length, 0);
      }
      await invoke(`echo sg_http_post_json('${base}/redirect', ['text'=>'fixture']);`);
      assert.equal(hits.filter(h => h.path === '/target').length, 0);
      assert.equal(await invoke(`echo sg_http_post_json('${base}/target', ['text'=>'fixture']);`), 'ok');
    } finally { await new Promise(resolve => server.close(resolve)); }
  });
}
test('HTTP GET rejects local stream protocols in the streams adapter', () => {
  const result = runPhp("require 'lib/http.php'; echo sg_http_get('data://text/plain,not-http') === '' ? 'blocked' : 'leaked';", ['-n']);
  assert.equal(result, 'blocked');
});
