/**
 * 极简 ZIP 读写工具（纯 Node 内置模块，无第三方依赖）。
 * 用于 release.mjs 生成部署包，以及 check.mjs 在 dist/ 被清理后回读包内 manifest。
 */
import fs from 'node:fs';
import { deflateRawSync, inflateRawSync } from 'node:zlib';

const CRC_TABLE = new Uint32Array(256);
for (let n = 0; n < 256; n++) {
  let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
  CRC_TABLE[n] = c >>> 0;
}

function crc32(buf) {
  let c = 0xffffffff;
  for (const byte of buf) c = CRC_TABLE[(c ^ byte) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

/** entries: [{ name: 'soraglass/index.php', data: Buffer, mtime?: Date }] */
export function writeZip(outPath, entries) {
  const locals = [];
  const centrals = [];
  let offset = 0;

  for (const entry of entries) {
    const name = Buffer.from(entry.name, 'utf8');
    const data = Buffer.isBuffer(entry.data) ? entry.data : Buffer.from(entry.data);
    const packed = deflateRawSync(data, { level: 9 });
    const crc = crc32(data);
    const modified = entry.mtime ?? new Date();
    const year = Math.max(1980, Math.min(2107, modified.getFullYear()));
    const date = ((year - 1980) << 9) | ((modified.getMonth() + 1) << 5) | modified.getDate();
    const time = (modified.getHours() << 11) | (modified.getMinutes() << 5) | Math.floor(modified.getSeconds() / 2);

    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(20, 4);       // version needed
    local.writeUInt16LE(0x0800, 6);   // UTF-8 filename flag
    local.writeUInt16LE(8, 8);        // deflate
    local.writeUInt16LE(time, 10);
    local.writeUInt16LE(date, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(packed.length, 18);
    local.writeUInt32LE(data.length, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);       // extra length
    locals.push(local, name, packed);

    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(0x0314, 4); // made by: UNIX
    central.writeUInt16LE(20, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(8, 10);
    central.writeUInt16LE(time, 12);
    central.writeUInt16LE(date, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(packed.length, 20);
    central.writeUInt32LE(data.length, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);

    offset += local.length + name.length + packed.length;
  }

  const centralDir = Buffer.concat(centrals);
  const eocd = Buffer.alloc(22);
  eocd.writeUInt32LE(0x06054b50, 0);
  eocd.writeUInt16LE(entries.length, 8);
  eocd.writeUInt16LE(entries.length, 10);
  eocd.writeUInt32LE(centralDir.length, 12);
  eocd.writeUInt32LE(offset, 16);

  fs.writeFileSync(outPath, Buffer.concat([...locals, centralDir, eocd]));
}

/** 解析中央目录，返回 name -> { method, compSize, size, localOffset } */
export function parseZip(zipPath) {
  const buf = fs.readFileSync(zipPath);
  let eocd = -1;
  for (let i = buf.length - 22; i >= Math.max(0, buf.length - 65558); i--) {
    if (buf.readUInt32LE(i) === 0x06054b50) { eocd = i; break; }
  }
  if (eocd < 0) throw new Error('ZIP 结构损坏：未找到 EOCD');

  const count = buf.readUInt16LE(eocd + 10);
  let cursor = buf.readUInt32LE(eocd + 16);
  const map = new Map();
  for (let i = 0; i < count; i++) {
    if (buf.readUInt32LE(cursor) !== 0x02014b50) throw new Error('ZIP 结构损坏：中央目录签名不匹配');
    const method = buf.readUInt16LE(cursor + 10);
    const compSize = buf.readUInt32LE(cursor + 20);
    const size = buf.readUInt32LE(cursor + 24);
    const nameLen = buf.readUInt16LE(cursor + 28);
    const extraLen = buf.readUInt16LE(cursor + 30);
    const commentLen = buf.readUInt16LE(cursor + 32);
    const localOffset = buf.readUInt32LE(cursor + 42);
    const name = buf.subarray(cursor + 46, cursor + 46 + nameLen).toString('utf8');
    map.set(name, { method, compSize, size, localOffset });
    cursor += 46 + nameLen + extraLen + commentLen;
  }
  return { buf, map };
}

export function readZipNames(zipPath) {
  return [...parseZip(zipPath).map.keys()];
}

/** 读取单个条目文本，找不到返回 null */
export function readZipEntry(zipPath, name, encoding = 'utf8') {
  const { buf, map } = parseZip(zipPath);
  const meta = map.get(name);
  if (!meta) return null;
  const nameLen = buf.readUInt16LE(meta.localOffset + 26);
  const extraLen = buf.readUInt16LE(meta.localOffset + 28);
  const start = meta.localOffset + 30 + nameLen + extraLen;
  const packed = buf.subarray(start, start + meta.compSize);
  const raw = meta.method === 8 ? inflateRawSync(packed) : packed;
  return encoding ? raw.toString(encoding) : raw;
}
