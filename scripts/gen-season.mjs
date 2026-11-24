#!/usr/bin/env node
/**
 * 生成二十四节气日期表 data/season-terms.php
 *
 * 母本：lunar-javascript 1.7.8 —— 与前端 side-calendar / calendar-data 同一套实现，
 * 所以「表 vs 前端库」的一致性由生成方式本身保证；再叠一道逐日自检兜住手滑。
 *
 * 格式：每年一行，24 个 MMDD 连写，键即 i（0=春分 … 18=冬至, 19=小寒 … 23=惊蛰）。
 * 按 i 存而不按日期顺序存，读取时 $row[$i] 是直接下标，省掉取模换算。
 * i=19..23 的日期落在公历 1–3 月，日期回绕属正常。
 *
 * 用法：node scripts/gen-season.mjs [--check-only]
 * 自检失败或数据不全时以非零码退出。
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import lunar from 'lunar-javascript';

const { Solar } = lunar;
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const outFile = path.join(root, 'data', 'season-terms.php');

const FROM = 1900;
const TO = 2100;
const NAMES = [
  '春分', '清明', '谷雨', '立夏', '小满', '芒种', '夏至', '小暑', '大暑', '立秋', '处暑', '白露',
  '秋分', '寒露', '霜降', '立冬', '小雪', '大雪', '冬至', '小寒', '大寒', '立春', '雨水', '惊蛰',
];
const TOTAL = (TO - FROM + 1) * 24;

const mmdd = (month, day) => String(month).padStart(2, '0') + String(day).padStart(2, '0');

function collect() {
  const rows = new Map();
  const missing = [];
  for (let year = FROM; year <= TO; year++) {
    const found = new Array(24).fill('');
    const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
    const days = leap ? 366 : 365;
    const base = Date.UTC(year, 0, 1);
    for (let d = 0; d < days; d++) {
      const dt = new Date(base + d * 86400000);
      const month = dt.getUTCMonth() + 1;
      const day = dt.getUTCDate();
      const name = Solar.fromYmd(year, month, day).getLunar().getJieQi();
      if (!name) continue;
      const i = NAMES.indexOf(name);
      if (i < 0) continue;
      if (!found[i]) found[i] = mmdd(month, day);
    }
    const gap = found.findIndex(v => !v);
    if (gap >= 0) {
      missing.push(`${year} 缺 ${NAMES[gap]}`);
      continue;
    }
    rows.set(year, found.join(''));
  }
  return { rows, missing };
}

function verify(rows) {
  const errors = [];
  let checked = 0;
  for (const [year, row] of rows) {
    const lookup = new Map();
    for (let i = 0; i < 24; i++) lookup.set(row.slice(i * 4, i * 4 + 4), NAMES[i]);
    const leap = (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
    const days = leap ? 366 : 365;
    const base = Date.UTC(year, 0, 1);
    for (let d = 0; d < days; d++) {
      const dt = new Date(base + d * 86400000);
      const month = dt.getUTCMonth() + 1;
      const day = dt.getUTCDate();
      const key = mmdd(month, day);
      const name = Solar.fromYmd(year, month, day).getLunar().getJieQi();
      const tabled = lookup.get(key);
      checked++;
      if (name && tabled !== name) errors.push(`${year}-${key} 当日为 ${name}，表内为 ${tabled ?? '空'}`);
      if (!name && tabled) errors.push(`${year}-${key} 表内记为 ${tabled}，当日并非节气`);
    }
  }
  return { checked, errors };
}

const { rows, missing } = collect();
if (missing.length) {
  console.error('✗ 节气数据不全，拒绝生成：');
  for (const line of missing.slice(0, 10)) console.error('  ' + line);
  if (missing.length > 10) console.error(`  …共 ${missing.length} 年`);
  process.exit(1);
}
if (rows.size !== TO - FROM + 1) {
  console.error(`✗ 年份数不符：期望 ${TO - FROM + 1}，实得 ${rows.size}`);
  process.exit(1);
}

const { checked, errors } = verify(rows);
if (errors.length) {
  console.error('✗ 逐日自检失败（表与 lunar-javascript 不一致）：');
  for (const line of errors.slice(0, 10)) console.error('  ' + line);
  process.exit(1);
}

const lines = [];
lines.push('<?php');
lines.push('/**');
lines.push(' * 二十四节气日期表（生成物，请勿手改）');
lines.push(' *');
lines.push(' * 生成：node scripts/gen-season.mjs');
lines.push(' * 母本：lunar-javascript 1.7.8（与前端日历同一套历法实现）');
lines.push(' * 格式：每年一行，24 个 MMDD 连写，键即 i（0=春分 … 18=冬至, 19=小寒 … 23=惊蛰）');
lines.push(' * 说明：i=19..23 的日期在公历 1–3 月，日期回绕属正常；');
lines.push(' *       一年恰好 24 个节气且都落在年内，故一个公历年即一整组。');
lines.push(' * 自检：生成时逐日比对 ' + checked + ' 天，全部一致才写盘。');
lines.push(' */');
lines.push('return [');
for (const [year, row] of rows) lines.push(`    ${year} => '${row}',`);
lines.push('];');
lines.push('');

const next = lines.join('\n');
const prev = fs.existsSync(outFile) ? fs.readFileSync(outFile, 'utf8') : '';
if (prev !== next) {
  fs.mkdirSync(path.dirname(outFile), { recursive: true });
  fs.writeFileSync(outFile, next);
  console.log(`✓ 写入 data/season-terms.php（${rows.size} 年 × 24 = ${TOTAL} 个节气日，自检 ${checked} 天）`);
} else {
  console.log(`✓ data/season-terms.php 已是最新（${rows.size} 年 × 24 = ${TOTAL} 个节气日，自检 ${checked} 天）`);
}
