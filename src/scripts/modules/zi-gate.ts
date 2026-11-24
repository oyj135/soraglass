/**
 * 字海 · 秘境彩蛋门（M5）
 *
 * 触发：访客在字海认满 3 个「仅一见」的冷字（localStorage 本地记账，不上传）；
 * 门厅页脚那枚暗印亮起，点击进 ?gate=1 的深夜来信；信页核对记账后揭信。
 * 无 JS 时门恒隐、信恒锁：藏东西的方式本身不依赖服务端状态。
 * 跳转地址由服务端拼好在 data-sg-zi-gate-url（兼容 ?p= 型无伪静态地址）。
 */

const KEY = 'sg-zi-rare';
const NEED = 3;
const KEEP = 10;

function visited(): string[] {
  try {
    const raw = window.localStorage.getItem(KEY);
    const parsed: unknown = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed)
      ? parsed.filter((item): item is string => typeof item === 'string' && item.length > 0)
      : [];
  } catch {
    return [];
  }
}

function unlocked(): boolean {
  return visited().length >= NEED;
}

function remember(ch: string): void {
  const list = visited();
  if (list.includes(ch)) return;
  list.push(ch);
  try {
    window.localStorage.setItem(KEY, JSON.stringify(list.slice(-KEEP)));
  } catch {
    /* 隐私模式写不进就作罢：这一访客与秘境无缘 */
  }
}

export function initZiGate(): void {
  /* 孤证字卡：记下这个字 */
  const rare = document.querySelector<HTMLElement>('[data-sg-zi-rare-char]');
  if (rare) {
    const ch = (rare.dataset.sgZiRareChar ?? '').trim();
    if (ch) remember(ch);
  }

  /* 门厅页脚的暗印：认满才亮，点击进服务端备好的秘境地址 */
  const door = document.querySelector<HTMLElement>('[data-sg-zi-gate]');
  if (door && unlocked()) {
    if (!door.dataset.sgGateBound) {
      door.dataset.sgGateBound = '1';
      door.addEventListener('click', () => {
        const url = door.dataset.sgZiGateUrl ?? '';
        if (url) window.location.assign(url);
      });
    }
    door.hidden = false;
  }

  /* 信页：已解锁则揭信 */
  const letter = document.querySelector<HTMLElement>('[data-sg-zi-gate-letter]');
  if (letter && unlocked()) letter.classList.add('is-open');
}
