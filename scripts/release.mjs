/**
 * 一键发布：构建 → 校验 → 生成可直传宝塔的 soraglass.zip → 清理临时 dist/。
 *
 * 结构约定（见 docs/STRUCTURE.md）：
 *   - soraglass/ 只放源码工程，构建产物不落地长期保留
 *   - soraglass.zip 与 soraglass/ 同级，内部顶层目录名为 soraglass
 *   - zip 内 = 运行时必需的 PHP 模板 / 片段 / 辅助库 / 缩略图 + dist/ 构建产物
 *   - docs/ 不进包，文档只在本地 docs/ 维护
 *
 * 用法：在 soraglass/ 目录下执行 npm run release
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { writeZip, readZipEntry } from './zip.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const workspace = path.dirname(root);
const zipPath = path.join(workspace, 'soraglass.zip');
const viteBin = path.join(root, 'node_modules/vite/bin/vite.js');

/**
 * 霞鹜文楷分片字体的 CDN 前缀（2026-11-25 第八十四轮，方案 B）。
 *
 * 换 CDN 只改这一行，再跑一次 npm run release：
 *   - 主站 cdn.jsdelivr.net 在本机实测不可达，gcore 是其国内镜像，作为兜底托管；
 *   - 前缀必须指向保留 `/files/<npm 包原文件名>.woff2` 结构的镜像，否则 404；
 *   - 若改用自建 COS，把前缀换成 `https://<域名>/<路径>` 并照原结构上传 files/ 即可。
 *
 * 源码 main.css 仍 @import node_modules 里的字体包：本地 dev/build 的字体不变，
 * 只有发布包在构建后把 URL 改指 CDN 并删掉本地分片（包体 -9 MB）。
 * 访客仍按 unicode-range 只取命中的 3–8 片，传输量与自托管时一致。
 */
const LXGW_CDN_BASE = 'https://gcore.jsdelivr.net/npm/lxgw-wenkai-webfont@1.7.0';

/**
 * KaTeX 的 @font-face 默认同时声明 woff2 / woff / truetype 三种格式，
 * Vite 会把三种文件全部输出。主流浏览器早已全面支持 woff2，后两种是纯冗余
 * （20 个 woff + 20 个 ttf，约 0.6 MiB）。这里在构建后改写 CSS 引用并删除
 * 对应文件：不改 node_modules，不改源码样式，LXGW WenKai 分片（全为 woff2）不受影响。
 */
function trimLegacyKaTeXFonts() {
  const assetsDir = path.join(root, 'dist/assets');
  if (!fs.existsSync(assetsDir)) return;

  let cssFixed = 0;
  for (const name of fs.readdirSync(assetsDir)) {
    if (!name.endsWith('.css')) continue;
    const abs = path.join(assetsDir, name);
    const css = fs.readFileSync(abs, 'utf8');
    if (!css.includes('format("woff")') && !css.includes('format("truetype")')) continue;
    const next = css.replace(/,\s*url\([^)]*\.(?:woff|ttf)\)\s*format\("(?:woff|truetype)"\)/g, '');
    if (/format\("(?:woff|truetype)"\)/.test(next)) {
      throw new Error(`KaTeX 旧字体引用未清理干净：dist/assets/${name}`);
    }
    fs.writeFileSync(abs, next);
    cssFixed++;
  }

  let filesRemoved = 0;
  for (const name of fs.readdirSync(assetsDir)) {
    if (!/^KaTeX_.*\.(woff|ttf)$/i.test(name)) continue;
    fs.rmSync(path.join(assetsDir, name));
    filesRemoved++;
  }
  if (cssFixed) {
    console.log(`  · KaTeX 字体仅保留 woff2：改写 ${cssFixed} 个 CSS，移除 ${filesRemoved} 个旧格式文件`);
  }
}

/**
 * 把构建产物里的 LXGW WenKai 分片 URL 改指 CDN，并删掉随包的 woff2。
 *
 * Vite 会把 `url(./files/lxgwwenkai-regular-subset-4.woff2)` 重命名成带 hash 的
 * `url(./lxgwwenkai-regular-subset-4-Dd9RLhEm.woff2)`，CDN 上只有不带 hash 的原文件名，
 * 所以这里必须剥掉 hash、补回 /files/ 目录，不能直接替换前缀了事。
 * 闸门：改写后 CSS 若仍指向本地分片，或文件名不匹配导致漏删，直接失败不出包。
 */
function externalizeLxgwFonts() {
  const assetsDir = path.join(root, 'dist/assets');
  if (!fs.existsSync(assetsDir)) return;

  const localUrl = /url\(\.\/(lxgwwenkai-(?:regular|bold)-subset-\d+)-[A-Za-z0-9_-]+\.woff2\)/g;
  const emittedFile = /^lxgwwenkai-(?:regular|bold)-subset-\d+-[A-Za-z0-9_-]+\.woff2$/;
  let rewritten = 0;

  for (const name of fs.readdirSync(assetsDir)) {
    if (!name.endsWith('.css')) continue;
    const abs = path.join(assetsDir, name);
    const css = fs.readFileSync(abs, 'utf8');
    if (!css.includes('url(./lxgwwenkai-')) continue;
    const next = css.replace(localUrl, (_, original) => {
      rewritten++;
      return `url(${LXGW_CDN_BASE}/files/${original}.woff2)`;
    });
    if (/url\(\.\/lxgwwenkai-/.test(next)) {
      throw new Error(`LXGW 分片仍有本地 URL 未改写：dist/assets/${name}`);
    }
    fs.writeFileSync(abs, next);
  }

  let filesRemoved = 0;
  for (const name of fs.readdirSync(assetsDir)) {
    if (!emittedFile.test(name)) continue;
    fs.rmSync(path.join(assetsDir, name));
    filesRemoved++;
  }
  if (rewritten !== filesRemoved) {
    throw new Error(`LXGW 分片改写数与删除数不一致：${rewritten} 条 URL / ${filesRemoved} 个文件`);
  }
  if (rewritten) {
    console.log(`  · LXGW 分片改走 CDN：${rewritten} 条 URL → ${LXGW_CDN_BASE}，移除 ${filesRemoved} 个本地分片`);
  }
}

if (!fs.existsSync(viteBin)) {
  console.error('✗ 未找到依赖，请先在 soraglass/ 目录执行：npm install');
  process.exit(1);
}

console.log('[1/5] 构建前端资源（vite build）…');
execFileSync(process.execPath, [viteBin, 'build'], { cwd: root, stdio: 'inherit' });
trimLegacyKaTeXFonts();
externalizeLxgwFonts();

console.log('\n[2/5] 全量静态校验…');
execFileSync(process.execPath, [path.join(root, 'scripts/check.mjs')], { cwd: root, stdio: 'inherit' });

console.log('\n[3/5] 收集运行时文件…');
const files = [];
function add(rel) {
  const abs = path.join(root, rel);
  if (!fs.existsSync(abs)) throw new Error(`必需文件缺失：soraglass/${rel}`);
  files.push({ rel, abs });
}
function addDir(dir, skip = () => false) {
  const base = path.join(root, dir);
  if (!fs.existsSync(base)) throw new Error(`必需目录缺失：soraglass/${dir}`);
  const stack = [base];
  while (stack.length) {
    const current = stack.pop();
    for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
      const abs = path.join(current, entry.name);
      const relInDir = path.relative(base, abs).replace(/\\/g, '/');
      if (skip(relInDir)) continue;
      if (entry.isDirectory()) stack.push(abs);
      else files.push({ rel: path.relative(root, abs).replace(/\\/g, '/'), abs });
    }
  }
}

for (const entry of fs.readdirSync(root, { withFileTypes: true })) {
  if (entry.isFile() && (entry.name.endsWith('.php') || entry.name === 'screenshot.png')) add(entry.name);
}
addDir('partials');
addDir('lib');
// dist/ 里的 preview-*.html 与 _*.html 是本地视觉核对的临时页，不进部署包
addDir('dist', (rel) => /^(preview-.*|_.*)\.html$/.test(rel));
// 时光页数据：docs/HISTORY.md 是唯一母本（check.mjs 禁止源码树出现 .md），
// 发布时拷为 soraglass/data/history.md 供 page-time.php 读取
const historySrc = path.join(workspace, 'docs', 'HISTORY.md');
if (!fs.existsSync(historySrc)) throw new Error('缺少 docs/HISTORY.md，时光页将没有数据，请先从 IMPLEMENTATION.md 轮次记录生成');
files.push({ rel: 'data/history.md', abs: historySrc });

// 节气表：scripts/gen-season.mjs 生成，线上不跑 Node，所以必须随包发布。
// data/ 不在 addDir 白名单里，漏 push 的后果是岁候页读不到表、整页空态。
const termsSrc = path.join(root, 'data/season-terms.php');
if (!fs.existsSync(termsSrc)) throw new Error('缺少 data/season-terms.php，请先 node scripts/gen-season.mjs');
files.push({ rel: 'data/season-terms.php', abs: termsSrc });


// 占位清零闸门（评审稿 §4.3）：〔待写〕是对渲染结果有约束的可见标记，
// 忘了替换就是「把占位当成品发出去」，所以在打包前直接失败。
const placeholder = '〔待写〕';
for (const dir of ['lib', 'data', 'partials']) {
  const stack = [path.join(root, dir)];
  while (stack.length) {
    const current = stack.pop();
    for (const entry of fs.readdirSync(current, { withFileTypes: true })) {
      const abs = path.join(current, entry.name);
      if (entry.isDirectory()) { stack.push(abs); continue; }
      if (!/\.(php|json|md)$/.test(entry.name)) continue;
      if (fs.readFileSync(abs, 'utf8').includes(placeholder)) {
        throw new Error(`存在占位文案 ${placeholder}：${path.relative(root, abs).replace(/\\/g, '/')}，请先定稿再发布`);
      }
    }
  }
}

const seasonPage = path.join(root, 'page-season.php');
if (fs.existsSync(seasonPage) && fs.readFileSync(seasonPage, 'utf8').includes(placeholder)) {
  throw new Error(`存在占位文案 ${placeholder}：page-season.php，请先定稿再发布`);
}

files.sort((a, b) => a.rel.localeCompare(b.rel));

const entries = files.map(({ rel, abs }) => ({
  name: `soraglass/${rel}`,
  data: fs.readFileSync(abs),
  mtime: fs.statSync(abs).mtime,
}));

console.log('\n[4/5] 写出部署包…');
writeZip(zipPath, entries);

const pluginRoot = path.join(workspace, 'plugins/SoraglassGallery');
if (!fs.existsSync(path.join(pluginRoot, 'vendor/autoload.php'))) throw new Error('缺少插件 SDK，请先在 plugins/SoraglassGallery 执行 composer install --no-dev');
const pluginEntries = [];
function collectPlugin(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (['tests', '.git', '.github', 'sample', 'samples', 'examples', 'docs', 'downloads'].includes(entry.name)) continue;
    const abs = path.join(dir, entry.name);
    if (entry.isDirectory()) collectPlugin(abs);
    else pluginEntries.push({ name: 'SoraglassGallery/' + path.relative(pluginRoot, abs).replace(/\\/g, '/'), data: fs.readFileSync(abs), mtime: fs.statSync(abs).mtime });
  }
}
collectPlugin(pluginRoot);
writeZip(path.join(workspace, 'SoraglassGallery.zip'), pluginEntries.sort((a,b) => a.name.localeCompare(b.name)));
console.log(`  插件部署包：${path.join(workspace, 'SoraglassGallery.zip')}（含云 SDK）`);

console.log('\n[5/5] 清理临时构建产物 dist/，恢复三件套结构…');
fs.rmSync(path.join(root, 'dist'), { recursive: true, force: true });

// 用最终状态再校验一次：此时 manifest 应从 zip 回读
execFileSync(process.execPath, [path.join(root, 'scripts/check.mjs')], { cwd: root, stdio: 'inherit' });

const size = fs.statSync(zipPath).size;
console.log(`\n✓ 发布包已生成：${zipPath}`);
console.log(`  ${entries.length} 个文件 / ${(size / 1048576).toFixed(2)} MB`);
const manifestText = readZipEntry(zipPath, 'soraglass/dist/.vite/manifest.json');
if (manifestText) {
  const entry = JSON.parse(manifestText)['src/scripts/main.ts'];
  console.log(`  入口资源：${entry?.file} + ${(entry?.css ?? []).join(', ')}`);
}
console.log('  上传目标（宝塔）：/www/wwwroot/<域名>/usr/themes/ → 解压为 soraglass/');
