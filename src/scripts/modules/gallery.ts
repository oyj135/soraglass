/** Album routes, keyboard navigation and metadata search. */
export function initGallery(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-gallery]');
  if (!root) return;
  const list = root.querySelector<HTMLElement>('[data-sg-gallery-list]');
  const details = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-gallery-detail]'));
  const cards = Array.from(root.querySelectorAll<HTMLAnchorElement>('[data-sg-album]'));
  const head = root.querySelector<HTMLElement>('.sg-page-head');
  const empty = root.querySelector<HTMLElement>('[data-sg-gallery-empty]');
  const input = root.querySelector<HTMLInputElement>('input.sg-search-input');
  const found = root.querySelector<HTMLElement>('[data-sg-gallery-found]');
  const results = root.querySelector<HTMLElement>('[data-sg-gallery-photos]');
  const photos = Array.from(root.querySelectorAll<HTMLAnchorElement>('[data-search-photo]'));
  if (!list || !details.length) return;
  let lastAlbum = '';
  const route = (id: string, push = false, focus = false): void => {
    const detail = details.find(d => d.dataset.sgGalleryDetail === id);
    list.hidden = Boolean(detail);
    details.forEach(d => { d.hidden = d !== detail; });
    if (head) head.hidden = Boolean(detail);
    if (empty && detail) empty.hidden = true;
    if (push) {
      const url = new URL(location.href);
      if (detail) url.searchParams.set('album', id);
      else url.searchParams.delete('album');
      history.pushState(null, '', url);
    }
    if (detail) lastAlbum = id;
    if (focus) {
      const target = detail?.querySelector<HTMLElement>('h1') ?? cards.find(c => c.dataset.sgAlbum === lastAlbum);
      if (target) { target.tabIndex = target instanceof HTMLAnchorElement ? 0 : -1; target.focus({ preventScroll: true }); }
      window.scrollTo({ top: 0, behavior: 'auto' });
    }
  };
  cards.forEach(card => card.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault(); route(card.dataset.sgAlbum ?? '', true, true);
  }));
  root.querySelectorAll<HTMLAnchorElement>('[data-sg-gallery-back]').forEach(back => back.addEventListener('click', event => {
    event.preventDefault(); route('', true, true); applySearch();
  }));
  window.addEventListener('popstate', () => { route(new URL(location.href).searchParams.get('album') ?? '', false, true); });
  const applySearch = (): void => {
    const query = input?.value.trim().toLocaleLowerCase() ?? '';
    if (query) route('', new URL(location.href).searchParams.has('album'));
    let albumHits = 0;
    cards.forEach(card => {
      const hit = !query || (card.dataset.keywords ?? '').toLocaleLowerCase().includes(query);
      card.classList.toggle('sg-filtered-out', !hit); if (hit) albumHits++;
    });
    const grid = root.querySelector<HTMLElement>('.sg-curated-grid');
    grid?.classList.toggle('sg-curated-grid--one', albumHits === 1);
    grid?.classList.toggle('sg-curated-grid--two', albumHits === 2);
    const matches = query ? photos.filter(p => (p.dataset.keywords ?? '').toLocaleLowerCase().includes(query)) : [];
    results?.replaceChildren(...matches.map(photo => {
      const clone = photo.cloneNode(true) as HTMLAnchorElement;
      clone.removeAttribute('data-sg-photo');
      clone.removeAttribute('data-search-photo');
      const album = photo.closest<HTMLElement>('[data-sg-gallery-detail]')?.dataset.sgGalleryDetail ?? '';
      clone.href = '?album=' + encodeURIComponent(album);
      clone.removeAttribute('target');
      clone.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault(); route(album, true, true);
      });
      return clone;
    }));
    if (found) found.hidden = !matches.length;
    const count = root.querySelector<HTMLElement>('[data-sg-gallery-photos-count]');
    if (count) count.textContent = String(matches.length) + ' 张';
    if (empty) empty.hidden = !query || albumHits > 0 || matches.length > 0;
  };
  let timer = 0;
  input?.addEventListener('input', () => { clearTimeout(timer); timer = window.setTimeout(applySearch, 200); });
  input?.addEventListener('keydown', event => {
    if (event.key === 'Enter') { event.preventDefault(); clearTimeout(timer); applySearch(); }
    if (event.key === 'Escape') { input.value = ''; applySearch(); }
  });
  root.querySelectorAll<HTMLImageElement>('img').forEach(img => img.addEventListener('error', () => {
    img.hidden = true;
    const label = document.createElement('span');
    label.className = 'sg-curated-no-preview'; label.textContent = '暂无法预览'; img.parentElement?.append(label);
  }, { once: true }));
  route(new URL(location.href).searchParams.get('album') ?? '');
}
