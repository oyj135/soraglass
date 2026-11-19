/**
 * 可复现的浏览器交互验证（P1b，2026-11-19）。
 *
 * 不依赖 puppeteer/playwright（线上不跑 npm、避免新增重依赖），用 Node 22 内置
 * WebSocket 直连 headless Chrome 的 DevTools Protocol（CDP）。测试对象为
 * `preview.mjs` 生成的静态页（加载真实构建产物 CSS/JS，DOM 挂点与模板一致）。
 *
 * 覆盖 CUA/截图路径无法稳定验证的动态行为：
 *   1. 顶栏方向切换：顶部显示菜单 → 向下滚显示页名、菜单撤出 → 向上滚恢复菜单；
 *   2. 二者互斥，任何时刻不同框；页名存在时菜单 visibility:hidden 不进焦点树。
 *
 * 用法：npm run build && node scripts/preview.mjs && node scripts/verify-browser.mjs
 * 退出码：全部断言通过 0，任一失败 1。可纳入 release/check 的独立步骤。
 */
import { spawn } from 'node:child_process';
import http from 'node:http';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import assert from 'node:assert/strict';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const preview = path.join(root, 'dist/preview-index.html');

// ---- 定位 Chrome/Edge 可执行文件 ----
const CANDIDATES = [
  process.env.CHROME_BIN,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
  'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
].filter(Boolean);
const chrome = CANDIDATES.find(p => { try { return fs.existsSync(p); } catch { return false; } });
if (!chrome || !fs.existsSync(preview)) {
  console.error('SKIP 浏览器验证：未找到 Chrome/Edge 或缺少 dist/preview-index.html（先跑 build + preview:static）');
  process.exit(0); // 环境缺依赖时跳过而非阻断，保证 check/release 在任意机器可用
}

// ---- 内置静态服务器：file:// 下 type=module 会被 CORS 拦截，必须走 http ----
const MIME = { '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
  '.woff2': 'font/woff2', '.svg': 'image/svg+xml', '.png': 'image/png' };
const server = http.createServer((req, res) => {
  const urlPath = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  const rel = urlPath === '/' ? '/preview-index.html' : urlPath;
  const file = path.join(root, 'dist', path.normalize(rel).replace(/^([/\\])+/, ''));
  if (!file.startsWith(path.join(root, 'dist'))) { res.writeHead(403).end(); return; }
  fs.readFile(file, (err, buf) => {
    if (err) { res.writeHead(404).end(); return; }
    res.writeHead(200, { 'Content-Type': MIME[path.extname(file)] || 'application/octet-stream' });
    res.end(buf);
  });
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const httpPort = server.address().port;

const userDataDir = fs.mkdtempSync(path.join(os.tmpdir(), 'sg-verify-chrome-'));
const portFile = path.join(userDataDir, 'DevToolsActivePort');
const child = spawn(chrome, [
  '--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage',
  '--no-first-run', '--no-default-browser-check', '--hide-scrollbars',
  `--remote-debugging-port=0`, `--user-data-dir=${userDataDir}`,
  '--window-size=1280,900', 'about:blank',
], { stdio: 'ignore' });

let ws;
const cleanup = () => {
  try { ws?.close(); } catch { /* noop */ }
  try { child.kill('SIGKILL'); } catch { /* noop */ }
  try { server.close(); } catch { /* noop */ }
  try { fs.rmSync(userDataDir, { recursive: true, force: true }); } catch { /* noop */ }
};
process.on('exit', cleanup);
process.on('SIGINT', () => { cleanup(); process.exit(130); });

const sleep = ms => new Promise(r => setTimeout(r, ms));

async function waitForPort() {
  for (let i = 0; i < 100; i++) {
    if (fs.existsSync(portFile)) {
      const lines = fs.readFileSync(portFile, 'utf8').split('\n');
      const port = Number(lines[0]);
      const pathPart = lines[1] || '/devtools/browser';
      if (port) return { wsUrl: `ws://127.0.0.1:${port}${pathPart}` };
    }
    if (child.exitCode !== null) throw new Error('Chrome 启动失败');
    await sleep(100);
  }
  throw new Error('等待 DevToolsActivePort 超时');
}

// ---- 极简 CDP 客户端 ----
let msgId = 0;
const pending = new Map();
function send(method, params = {}, sessionId) {
  const id = ++msgId;
  return new Promise((resolve, reject) => {
    pending.set(id, { resolve, reject });
    ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
  });
}

async function main() {
  const { wsUrl } = await waitForPort();
  ws = new WebSocket(wsUrl);
  await new Promise((resolve, reject) => {
    ws.onopen = resolve;
    ws.onerror = err => reject(new Error(`WebSocket 连接失败: ${err.message || err}`));
  });
  ws.onmessage = ev => {
    const msg = JSON.parse(ev.data);
    if (msg.id && pending.has(msg.id)) {
      const { resolve, reject } = pending.get(msg.id);
      pending.delete(msg.id);
      if (msg.error) reject(new Error(`${msg.error.message}`)); else resolve(msg.result);
    }
  };

  const { targetId } = await send('Target.createTarget', { url: 'about:blank' });
  const { sessionId } = await send('Target.attachToTarget', { targetId, flatten: true });
  await send('Page.enable', {}, sessionId);
  await send('Runtime.enable', {}, sessionId);
  const pageUrl = `http://127.0.0.1:${httpPort}/preview-index.html`;
  await send('Page.navigate', { url: pageUrl }, sessionId);
  // 等页面脚本执行完（load + 一点缓冲）
  await sleep(900);

  const evalJs = async expression => {
    const r = await send('Runtime.evaluate', {
      expression, returnByValue: true, awaitPromise: true,
    }, sessionId);
    if (r.exceptionDetails) throw new Error(`页面脚本异常: ${r.exceptionDetails.text}`);
    return r.result.value;
  };

  const snapshot = () => evalJs(`(() => {
    const nav = document.querySelector('.sg-nav');
    const page = document.querySelector('.sg-nav-page');
    const links = document.querySelector('.sg-nav-links');
    const cs = el => {
      if (!el) return null;
      const s = getComputedStyle(el);
      return { opacity: s.opacity, visibility: s.visibility, display: s.display };
    };
    return {
      y: window.scrollY,
      docH: document.documentElement.scrollHeight,
      navClass: nav ? nav.className : '',
      pageHidden: page ? page.hasAttribute('hidden') : null,
      page: cs(page),
      links: cs(links),
    };
  })()`);

  const results = [];
  const check = (name, cond, detail) => {
    results.push({ name, ok: !!cond, detail });
  };

  // 1) 顶部初始态：菜单可见，页名隐藏（hidden 尚未移除或 visibility:hidden）
  const top = await snapshot();
  check('页面可滚动（内容足够长）', top.docH > 1200, `docH=${top.docH}`);
  check('页名已由 JS 填充（hidden 移除）', top.pageHidden === false, `pageHidden=${top.pageHidden}`);
  check('顶部：菜单可见', top.links?.visibility === 'visible' && Number(top.links?.opacity) > 0.9, JSON.stringify(top.links));
  check('顶部：页名隐藏', top.page?.visibility === 'hidden' || Number(top.page?.opacity) < 0.1, JSON.stringify(top.page));

  // 2) 向下滚：触发 is-hidden，页名出现、菜单撤出
  await evalJs('window.scrollTo(0, 600)');
  await sleep(250);
  await evalJs('window.scrollTo(0, 900)');
  await sleep(250);
  const down = await snapshot();
  check('下滑：nav 进入 is-hidden', /is-hidden/.test(down.navClass), `class=${down.navClass}`);
  check('下滑：页名可见', down.page?.visibility === 'visible' && Number(down.page?.opacity) > 0.9, JSON.stringify(down.page));
  check('下滑：菜单撤出（visibility:hidden 不进焦点树）', down.links?.visibility === 'hidden', JSON.stringify(down.links));
  check('下滑：页名与菜单互斥不同框', !(
    down.page?.visibility === 'visible' && down.links?.visibility === 'visible'
  ), `page=${down.page?.visibility} links=${down.links?.visibility}`);

  // 3) 向上滚：移除 is-hidden，菜单恢复、页名隐藏
  await evalJs('window.scrollTo(0, 400)');
  await sleep(250);
  const up = await snapshot();
  check('上滑：nav 退出 is-hidden', !/is-hidden/.test(up.navClass), `class=${up.navClass}`);
  check('上滑：菜单恢复可见', up.links?.visibility === 'visible', JSON.stringify(up.links));
  check('上滑：页名重新隐藏', up.page?.visibility === 'hidden', JSON.stringify(up.page));

  // 输出
  let failed = 0;
  for (const r of results) {
    console.log(`${r.ok ? 'PASS' : 'FAIL'}  ${r.name}${r.ok ? '' : '  -> ' + r.detail}`);
    if (!r.ok) failed++;
  }
  console.log(`\n浏览器交互验证：${results.length - failed}/${results.length} 通过`);
  if (failed) process.exitCode = 1;
}

main().catch(err => {
  console.error('浏览器验证异常:', err.message);
  process.exitCode = 1;
}).finally(() => {
  // 给 CDP 关闭留一帧，然后清理
  setTimeout(() => { cleanup(); }, 100);
});
