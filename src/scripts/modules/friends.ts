/** 胶囊保持原生链接行为；悬停/聚焦只同步左侧站点介绍。 */
export function initFriends(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-friends]');
  if (!root) return;
  const cards = Array.from(root.querySelectorAll<HTMLAnchorElement>('[data-sg-friend]'));
  const name = root.querySelector<HTMLElement>('[data-sg-friend-name]');
  const description = root.querySelector<HTMLElement>('[data-sg-friend-description]');
  const avatarElement = root.querySelector<HTMLImageElement>('[data-sg-friend-avatar]');
  const visit = root.querySelector<HTMLAnchorElement>('[data-sg-friend-visit]');
  if (!name || !description || !avatarElement || !visit || !cards.length) return;
  let avatar = avatarElement;

  let selected: HTMLAnchorElement | undefined;
  const select = (card: HTMLAnchorElement): void => {
    if (card === selected) return;
    selected?.classList.remove('is-selected');
    selected = card;
    card.classList.add('is-selected');
    name.textContent = card.dataset.name ?? '';
    description.textContent = card.dataset.description ?? '';
    const source = card.dataset.avatar ?? '';
    if (avatar.getAttribute('src') !== source) {
      // 全局图片兜底在图片节点上保存重试状态；换节点避免旧图重试覆盖新站点。
      const next = document.createElement('img');
      next.className = avatar.className;
      next.width = avatar.width;
      next.height = avatar.height;
      next.alt = '';
      next.setAttribute('data-sg-friend-avatar', '');
      avatar.replaceWith(next);
      next.src = source;
      avatar = next;
    }
    visit.href = card.href;
    visit.setAttribute('aria-label', card.getAttribute('aria-label') ?? '访问站点（新窗口）');
  };
  cards.forEach((card) => {
    card.addEventListener('pointerenter', (event) => {
      if (event.pointerType === 'touch') return;
      // 聚焦中的链接优先，鼠标经过不改变键盘用户正在阅读的介绍。
      if (root.contains(document.activeElement)) return;
      select(card);
    });
    card.addEventListener('focus', () => select(card));
  });
  select(cards[0]);
}
