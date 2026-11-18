/**
 * 生成自托管思源宋体（Noto Serif SC）：
 *   拉取 Google Fonts 的 @font-face 清单（含 unicode-range 分片）
 *   → 下载每个 woff2 到 src/font/noto-serif-sc/
 *   → 改写为本地相对路径，输出 src/styles/font.css
 *
 * 只在需要重建字体时运行：node scripts/fonts.mjs
 * 产物进 dist/assets/（Vite 带 hash），线上不依赖 Google Fonts。
 *
 * 注意：Noto Serif SC 在 Google Fonts 上是可变字体，400/700/900 共用同一批
 * 分片文件（实测 303 条 @font-face 只对应 101 个 URL）。因此这里按 URL 去重，
 * 每个分片只声明一条 `font-weight: 400 900`，CSS 体积能省掉约三分之二。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outDir = path.join(root, 'src/font/noto-serif-sc');
const cssOut = path.join(root, 'src/styles/font.css');
const WEIGHTS = [400, 700, 900];
const RANGE_DECL = `${WEIGHTS[0]} ${WEIGHTS[WEIGHTS.length - 1]}`;
const UA =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

fs.mkdirSync(outDir, { recursive: true });

const url =
  'https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@' + WEIGHTS.join(';') + '&display=swap';
console.log('拉取字体清单…', url);
const res = await fetch(url, { headers: { 'User-Agent': UA } });
if (!res.ok) throw new Error('拉取 Google Fonts CSS 失败：' + res.status);
const css = await res.text();

const blocks = css.split('@font-face').slice(1);
console.log('分包条目：', blocks.length);

/** 按远端 URL 去重；同一 URL 会以多个 font-weight 重复出现 */
const seen = new Map();
for (const block of blocks) {
  const range = /unicode-range:\s*([^;]+);/.exec(block)?.[1]?.trim();
  const remote = /url\((https:\/\/[^)]+\.woff2)\)/.exec(block)?.[1];
  if (!range || !remote || seen.has(remote)) continue;
  seen.set(remote, range);
}

const faces = [];
let index = 0;
for (const [remote, range] of seen) {
  index += 1;
  const name = `subset-${String(index).padStart(3, '0')}.woff2`;
  const file = path.join(outDir, name);

  if (!fs.existsSync(file) || fs.statSync(file).size === 0) {
    const bin = await fetch(remote, { headers: { 'User-Agent': UA } });
    if (!bin.ok) throw new Error(`下载失败 ${remote}：${bin.status}`);
    fs.writeFileSync(file, Buffer.from(await bin.arrayBuffer()));
  }

  faces.push({ range, name });
  if (index % 25 === 0) console.log('  已处理', index);
}

const head = `/* 自动生成，请勿手改。重新生成：node scripts/fonts.mjs
 * 字体：Noto Serif SC（SIL Open Font License 1.1），可变字重 400–900，按 unicode-range 分片按需加载。 */
`;
const body = faces
  .map(
    (f) => `@font-face {
  font-family: 'Noto Serif SC';
  font-style: normal;
  font-weight: ${RANGE_DECL};
  font-display: swap;
  src: url('../font/noto-serif-sc/${f.name}') format('woff2');
  unicode-range: ${f.range};
}`
  )
  .join('\n');

fs.writeFileSync(cssOut, head + body + '\n', 'utf8');

const total = faces.reduce((sum, f) => sum + fs.statSync(path.join(outDir, f.name)).size, 0);
console.log(`\n✓ 字体分片 ${faces.length} 个 / ${(total / 1048576).toFixed(2)} MB`);
console.log('  ' + path.relative(root, outDir).replace(/\\/g, '/'));
console.log('  ' + path.relative(root, cssOut).replace(/\\/g, '/'));
