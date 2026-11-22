/**
 * 全量静态校验。
 *
 * 结构前提（见 docs/STRUCTURE.md）：
 *   <工作区>/soraglass/      主题源码工程（唯一源码根）
 *   <工作区>/docs/           全部沉淀文档与配置说明
 *   <工作区>/soraglass.zip   可直传宝塔的运行包（内含 dist/）
 * dist/ 是临时构建产物，发布后被清理；此时 manifest 与静态数据从 zip 回读校验。
 *
 * PHP 语法当前由 php-parser 解析，不依赖本机 PHP CLI。若后续改为 php -l，
 * 需同时保证 release 在无参数调用时能找到可配置的 PHP 路径，不能以整段 check 替代。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Engine from 'php-parser';
import { readZipEntry, readZipNames } from './zip.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const workspace = path.dirname(root);
const errors = [];
const warnings = [];
const skip = new Set(['node_modules', '.git', '.vite', 'dist', 'src', 'scripts', 'public', 'docs', 'deploy', 'vendor']);

// ---------- 1) PHP 模板 ----------
const phpFiles = [];
function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    if (skip.has(entry.name)) continue;
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.php')) phpFiles.push(full);
  }
}
walk(root);
walk(path.join(workspace, 'plugins/SoraglassGallery'));

const parser = new Engine({ parser: { php7: true, suppressErrors: false } });
for (const file of phpFiles) {
  const src = fs.readFileSync(file, 'utf8');
  const rel = path.relative(root, file).replace(/\\/g, '/');
  try {
    parser.parseCode(src, path.basename(file));
  } catch (err) {
    errors.push(`[PHP 语法] soraglass/${rel}: ${err.message}`);
  }
  // 只检查主题根目录下的独立页模板；partials/page-head.php 是共享片段，不需要模板头
  if (/^page-[^/]+\.php$/.test(rel) && !src.includes('@package custom')) {
    errors.push(`[自定义模板] soraglass/${rel} 缺少 @package custom 注释头`);
  }
}

// ---------- 2) manifest / 静态数据：优先临时 dist，回退部署包 ----------
const distDir = path.join(root, 'dist');
const zipPath = path.join(workspace, 'soraglass.zip');
const distReady = fs.existsSync(path.join(distDir, '.vite/manifest.json'));
let zipNames = null;
let manifest = null;

if (distReady) {
  manifest = JSON.parse(fs.readFileSync(path.join(distDir, '.vite/manifest.json'), 'utf8'));
} else if (fs.existsSync(zipPath)) {
  const text = readZipEntry(zipPath, 'soraglass/dist/.vite/manifest.json');
  if (!text) {
    errors.push('[构建] soraglass.zip 内缺少 dist/.vite/manifest.json，请执行 npm run release');
  } else {
    manifest = JSON.parse(text);
    zipNames = readZipNames(zipPath);
  }
} else {
  errors.push('[构建] 缺少 dist/.vite/manifest.json 与 soraglass.zip，请执行 npm run release');
}

const hasDistFile = (rel) => distReady
  ? fs.existsSync(path.join(distDir, rel))
  : (zipNames ?? []).includes(`soraglass/dist/${rel}`);

if (manifest) {
  if (!manifest['src/scripts/main.ts']) errors.push('[构建] manifest 缺少 src/scripts/main.ts 入口');
  if (!hasDistFile('data/friends.json')) errors.push('[构建] dist/data/ 静态数据缺失，检查 publicDir 配置');
  const entry = manifest['src/scripts/main.ts'];
  for (const rel of [entry?.file, ...(entry?.css ?? [])]) {
    if (rel && !hasDistFile(rel)) errors.push(`[构建] 入口资源未进包：dist/${rel}`);
  }
}

// ---------- 3) 模板资源引用 ----------
if (manifest) {
  for (const file of phpFiles) {
    const src = fs.readFileSync(file, 'utf8');
    for (const match of src.matchAll(/sg_asset\(\s*'([^']+)'\s*\)/g)) {
      if (!manifest[match[1]]) {
        errors.push(`[资源引用] ${path.relative(root, file)} 引用了 manifest 不存在的入口 ${match[1]}`);
      }
    }
  }
}

// ---------- 4) 目录结构与职责隔离 ----------
const sourceRequired = [
  'index.php', 'header.php', 'footer.php', 'post.php', 'page.php',
  'archive.php', '404.php', 'functions.php', 'sidebar.php', 'screenshot.png',
  'package.json', 'package-lock.json', 'vite.config.ts', 'tsconfig.json', '.gitignore',
  'scripts/check.mjs', 'scripts/release.mjs', 'scripts/zip.mjs',
  'src/styles/main.css', 'src/scripts/main.ts', 'public/data/playlist.json',
  'lib/config.php', 'lib/assets.php', 'lib/fields.php', 'lib/admin.php',
  'lib/http.php', 'lib/playlist.php', 'lib/stats.php',
  // P0 拆分后 components/ 的 9 个子文件（聚合入口不得缺失任一）
  ...['base', 'nav', 'floating', 'profile', 'player', 'home-content', 'article', 'pages', 'interactions']
    .map(n => `src/styles/components/${n}.css`),
];
for (const rel of sourceRequired) {
  if (!fs.existsSync(path.join(root, rel))) errors.push(`[源码缺失] soraglass/${rel}`);
}

const docsRequired = ['AGENS.md', 'IMPLEMENTATION.md', 'STRUCTURE.md', 'deploy/baota.conf'];
for (const rel of docsRequired) {
  if (!fs.existsSync(path.join(workspace, 'docs', rel))) errors.push(`[文档缺失] docs/${rel}`);
}

// 顶层仅允许三件套 + 只读参考源码 + 已有平台/协作元数据目录
// （.zwork / .kimi-attachments 都是宿主会话附件目录，与 .workbuddy/.agents 同类，
//   .zwork 2026-11-17 登记、.kimi-attachments 2026-11-18 登记；
//   壁纸候选 / 配色 是用户本地的素材目录，不是文档，2026-11-21 登记「配色」；
//   vectorizer 是用户的描摹 SVG 素材目录（墨龙/作者卡场景），2026-11-22 登记）
const allowedTop = new Set(['soraglass', 'docs', 'soraglass.zip', 'plugins', 'SoraglassGallery.zip', 'hexo-theme-fomalhaut', '.workbuddy', '.agents', '.zwork', '.kimi-attachments', '.git', '壁纸候选', '配色', 'vectorizer']);
for (const entry of fs.readdirSync(workspace)) {
  if (!allowedTop.has(entry)) errors.push(`[顶层结构] 多余条目 ${entry}，顶层只允许三件套及已列明的参考/协作目录`);
  if (entry.endsWith('.md')) errors.push(`[文档归置] 根目录不应有 ${entry}，沉淀文档统一放 docs/`);
}

// soraglass/ 内只允许源码工程
for (const entry of fs.readdirSync(root)) {
  if (entry.endsWith('.md')) errors.push(`[源码隔离] soraglass/ 内不应有文档 ${entry}`);
  if (entry === 'docs' || entry === 'deploy') errors.push(`[源码隔离] soraglass/ 内不应有 ${entry}/`);
  if (entry === 'dist') warnings.push('[临时产物] soraglass/dist/ 存在；npm run release 打包后会自动清理');
}
if (phpFiles.length === 0) errors.push('[源码缺失] 未发现任何 PHP 模板');

// components.css 汇总入口的 @import 顺序即层叠顺序，必须与约定一致
{
  const entry = path.join(root, 'src/styles/components.css');
  if (fs.existsSync(entry)) {
    const expected = ['base', 'nav', 'floating', 'profile', 'player', 'home-content', 'article', 'pages', 'interactions']
      .map(n => `@import "./components/${n}.css";`);
    const src = fs.readFileSync(entry, 'utf8');
    const actual = src.split(/\r?\n/).map(l => l.trim()).filter(l => l.startsWith('@import "./components/'));
    if (JSON.stringify(actual) !== JSON.stringify(expected)) {
      errors.push('[样式顺序] components.css 的 9 条 @import 顺序与约定不符，层叠会改变');
    }
  }
}

// ---------- 5) Typecho 1.3 表单兼容性 ----------
const fn = phpFiles.map(file => fs.readFileSync(file, 'utf8')).join('\n');
for (const match of fn.matchAll(/new\s+(Textarea|Select)\s*\(([^;]*?)\)\s*;/gs)) {
  if (match[2].split(',').length < 4) {
    errors.push(`[1.3 兼容] ${match[1]} 构造参数不足 4 个，缺少 $label 标题`);
  }
}

// ---------- 6) 模板包含与静态数据引用 ----------
for (const file of phpFiles) {
  const src = fs.readFileSync(file, 'utf8');
  const rel = path.relative(root, file).replace(/\\/g, '/');
  for (const match of src.matchAll(/->need\(\s*['"]([^'"]+)['"]\s*\)/g)) {
    if (!fs.existsSync(path.join(root, match[1]))) {
      errors.push(`[模板引用] ${rel} -> need('${match[1]}') 文件不存在`);
    }
  }
  for (const match of src.matchAll(/sg_json\(\s*'[^']*'\s*,\s*'([^']+)'/g)) {
    if (!hasDistFile(`data/${match[1]}.json`)) {
      errors.push(`[静态数据] ${rel} 引用了不存在的 dist/data/${match[1]}.json`);
    }
  }
}

console.log(`检查源码 PHP ${phpFiles.length} 个，manifest 来源：${distReady ? '临时 dist/' : 'soraglass.zip'}，问题 ${errors.length} 项`);
for (const e of errors) console.error('  ✗ ' + e);
for (const w of warnings) console.warn('  ! ' + w);
if (errors.length) process.exit(1);
console.log('  ✓ PHP 语法、自定义模板注释头、资源引用、顶层结构、文档归置、1.3 表单兼容性全部通过');
