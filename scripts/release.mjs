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

if (!fs.existsSync(viteBin)) {
  console.error('✗ 未找到依赖，请先在 soraglass/ 目录执行：npm install');
  process.exit(1);
}

console.log('[1/5] 构建前端资源（vite build）…');
execFileSync(process.execPath, [viteBin, 'build'], { cwd: root, stdio: 'inherit' });

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
