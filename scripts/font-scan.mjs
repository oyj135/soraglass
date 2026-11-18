/**
 * 字体分片命中扫描 —— 回答一个问题：preload 哪几片最划算。
 *
 * 背景：Noto Serif SC 被切成 101 个 unicode-range 分片，浏览器只下载「页面里
 * 真的出现过的字」所在的那几片。所以要挑出 preload 的目标，必须知道哪几片
 * 覆盖面最大。这个脚本抓若干真实页面，按 unicode-range 与页面字符求交集，
 * 算出每片命中的字符数与出现次数，再排序。
 *
 * 用法（页面要能访问；本地沙箱最方便）：
 *   node scripts/font-scan.mjs http://127.0.0.1:8100/ \
 *        http://127.0.0.1:8100/projects.html \
 *        http://127.0.0.1:8100/index.php/archives/24/
 *
 * ⚠️ 重新执行 `npm run fonts` 之后必须重跑本脚本：分片是按 Google Fonts
 *    返回的顺序编号的，上游改动会让编号全体位移，`lib/assets.php` 里
 *    `sg_font_preload()` 的 096/095/094 就会指向完全不同的文件
 *    —— 不会报错，只是白下将近 200 KB。这是这套方案唯一的长期风险点。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/** 解析 font.css → [{ n: '096', rng: [[start, end], ...] }] */
function readFaces() {
  const css = fs.readFileSync(path.join(root, 'src/styles/font.css'), 'utf8');
  const faces = [];
  let cur = null;
  for (const line of css.split('\n')) {
    const s = line.trim();
    const name = /subset-(\d+)\.woff2/.exec(s);
    if (name) {
      cur = name[1];
      continue;
    }
    if (!s.startsWith('unicode-range') || !cur) continue;
    const raw = s.slice(s.indexOf(':') + 1).replace(';', '').trim();
    const rng = [];
    for (const part of raw.split(',')) {
      const token = part.trim().toUpperCase().replace('U+', '');
      if (!token) continue;
      if (token.includes('-')) {
        const [a, b] = token.split('-');
        rng.push([parseInt(a, 16), parseInt(b, 16)]);
      } else {
        const v = parseInt(token, 16);
        rng.push([v, v]);
      }
    }
    faces.push({ n: cur, rng });
    cur = null;
  }
  return faces;
}

const sizeOf = (n) => {
  const file = path.join(root, 'src/font/noto-serif-sc/subset-' + n + '.woff2');
  return fs.existsSync(file) ? fs.statSync(file).size : 0;
};

/** 只留非 ASCII 字符：ASCII 段本身有系统后备字体，收益远小于中文分片 */
function charFreq(html) {
  const text = html
    .replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&[a-zA-Z#0-9]+;/g, ' ');
  const freq = new Map();
  for (const ch of text) {
    const code = ch.codePointAt(0);
    if (code > 0x7f) freq.set(code, (freq.get(code) || 0) + 1);
  }
  return freq;
}

const urls = process.argv.slice(2);
if (!urls.length) {
  console.error('用法：node scripts/font-scan.mjs <页面 URL> [更多 URL…]');
  process.exit(1);
}

const faces = readFaces();
if (!faces.length) {
  console.error('src/styles/font.css 里没有解析到分片，先跑 npm run fonts');
  process.exit(1);
}

const pages = [];
for (const url of urls) {
  let html = '';
  try {
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    html = await res.text();
  } catch (err) {
    console.warn(`跳过 ${url}：${err.message}`);
    continue;
  }
  const freq = charFreq(html);
  const used = faces.filter((f) => [...freq.keys()].some((c) => f.rng.some(([a, b]) => a <= c && c <= b)));
  const kb = used.reduce((sum, f) => sum + sizeOf(f.n), 0) / 1024;
  pages.push({ url, freq, used, kb });
  console.log(`· ${url}\n  汉字种类 ${String(freq.size).padStart(4)} ｜ 命中分片 ${String(used.length).padStart(2)} ｜ 实际体积 ${kb.toFixed(0)} KB`);
}
if (!pages.length) process.exit(1);

// 跨页合并：按「出现次数」排序 —— 频次比种类更贴近阅读时的实际负载
const agg = new Map();
for (const p of pages) {
  for (const [code, n] of p.freq) {
    const face = faces.find((f) => f.rng.some(([a, b]) => a <= code && code <= b));
    if (face) agg.set(face.n, (agg.get(face.n) || 0) + n);
  }
}
const total = [...agg.values()].reduce((a, b) => a + b, 0);
const ranked = [...agg.entries()].sort((a, b) => b[1] - a[1]).map(([n, hits]) => ({ n, hits, size: sizeOf(n) }));

console.log('\n片\tKB\t次数\t次数占比\t累计占比');
let acc = 0;
let kbAcc = 0;
for (const r of ranked.slice(0, 10)) {
  acc += r.hits;
  kbAcc += r.size;
  console.log(
    `${r.n}\t${(r.size / 1024).toFixed(1)}\t${r.hits}\t${((r.hits / total) * 100).toFixed(1)}%\t\t${((acc / total) * 100).toFixed(1)}%（累计 ${(kbAcc / 1024).toFixed(0)} KB）`,
  );
}

// 候选组合：给出「每页覆盖率」而不只是合并数字，避免某一页被平均掉
console.log('\n=== 候选 preload 组合 ===');
for (const ids of [[ranked[0].n, ranked[1].n], [ranked[0].n, ranked[1].n, ranked[2].n], ranked.slice(0, 4).map((r) => r.n)]) {
  const kb = ids.reduce((sum, n) => sum + sizeOf(n), 0) / 1024;
  console.log(`\n${ids.join(' + ')}  =  ${kb.toFixed(0)} KB`);
  for (const p of pages) {
    const sel = faces.filter((f) => ids.includes(f.n));
    let chars = 0;
    let hits = 0;
    for (const [code, n] of p.freq) {
      if (!sel.some((f) => f.rng.some(([a, b]) => a <= code && code <= b))) continue;
      chars += 1;
      hits += n;
    }
    const allHits = [...p.freq.values()].reduce((a, b) => a + b, 0);
    console.log(`  ${p.url.padEnd(46)} 字 ${(chars / p.freq.size * 100).toFixed(0).padStart(3)}%   次 ${(hits / allHits * 100).toFixed(0).padStart(3)}%`);
  }
}

console.log('\n把选定的编号写进 lib/assets.php 的 sg_font_preload()：' + ranked.slice(0, 3).map((r) => `'${r.n}'`).join(', '));
