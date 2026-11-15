import { pageOn, pageTimeout, pageInterval, pageFrame, onPageDispose, pageSignal } from './lifecycle';
/** Public paged albums and on-demand photos; all mutations stay in the admin API. */
import { initLightbox } from './article';
interface Photo { id: string; album: string; url: string; thumb: string; caption: string; date: string; preview: boolean; width: number; height: number }
interface Album { id: string; title: string; description: string; date: string; count: number; cover: string; coverThumb: string; stack?: string[]; featured: boolean; photos: Photo[] }
interface AlbumPage { albums: Album[]; total: number; page: number; pages: number; openAlbum?: Album }
interface PhotoPage { photos: Photo[]; total: number; page: number; pages: number }

export function initGallery(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-gallery]');
  if (!root) return;
  const list = root.querySelector<HTMLElement>('[data-sg-gallery-list]'), grid = root.querySelector<HTMLElement>('.sg-curated-grid');
  if (!list || !grid) return;
  const head = root.querySelector<HTMLElement>('.sg-page-head'), input = root.querySelector<HTMLInputElement>('input.sg-search-input');
  const empty = root.querySelector<HTMLElement>('[data-sg-gallery-empty]'), status = root.querySelector<HTMLElement>('[data-gallery-status]');
  const found = root.querySelector<HTMLElement>('[data-sg-gallery-found]'), results = root.querySelector<HTMLElement>('[data-sg-gallery-photos]');
  const moreAlbums = root.querySelector<HTMLButtonElement>('[data-gallery-albums-more]'), moreSearch = root.querySelector<HTMLButtonElement>('[data-gallery-search-more]');
  const pageAbort = pageSignal(); onPageDispose(() => { routeVersion++; searchVersion++; searchController?.abort(); });
  const initial = JSON.parse(root.querySelector('[data-gallery-catalog]')?.textContent || '{"albums":[],"page":1,"pages":1,"total":0}') as AlbumPage;
  const known = new Map(initial.albums.map(a => [a.id, a]));
  if (initial.openAlbum) known.set(initial.openAlbum.id, initial.openAlbum);
  let baseAlbums = initial.albums, albumPage = 1, albumPages = initial.pages, searchPage = 1, searchPages = 1;
  let lastAlbum = '', query = '', searchVersion = 0, routeVersion = 0, searchController: AbortController | null = null;
  const photoLoads = new Map<HTMLElement, Promise<void>>();
  const message = (text = ''): void => { if (status) { status.textContent = text; status.hidden = !text; } };
  const node = <T extends keyof HTMLElementTagNameMap>(tag: T, text = '', cls = ''): HTMLElementTagNameMap[T] => {
    const n = document.createElement(tag); n.textContent = text; if (cls) n.className = cls; return n;
  };
  async function read<T>(op: string, values: Record<string, string | number>, signal?: AbortSignal): Promise<T> {
    const url = new URL(root!.dataset.endpoint!, location.href); url.searchParams.set('op', op);
    Object.entries(values).forEach(([k, v]) => url.searchParams.set(k, String(v)));
    const controller = new AbortController(), abort = (): void => controller.abort();
    signal?.addEventListener('abort', abort, { once: true }); if (signal?.aborted) abort();
    pageAbort.addEventListener('abort', abort, {once:true}); if (pageAbort.aborted) abort();
    const timer = pageTimeout(abort, 20000);
    try { const response = await fetch(url, { signal: controller.signal, credentials: 'omit' }); const data = await response.json() as { ok: boolean; data: T; error?: string }; if (!response.ok || !data.ok) throw new Error(data.error || '读取失败，请重试。'); return data.data; }
    finally { clearTimeout(timer); signal?.removeEventListener('abort', abort); pageAbort.removeEventListener('abort', abort); }
  }
  function image(src: string, original: string, alt: string, width = 0, height = 0): HTMLImageElement {
    const img = node('img'); img.src = src || original; img.alt = alt; img.loading = 'lazy'; img.decoding = 'async';
    if (width > 0 && height > 0) { img.width = width; img.height = height; }
    let fallback = false; img.addEventListener('error', () => {
      if (!fallback && original && img.src !== original) { fallback = true; img.src = original; }
      else { img.hidden = true; img.parentElement?.append(node('span', '暂无法预览，可打开原文件', 'sg-curated-no-preview')); }
    }); return img;
  }
  function albumCard(a: Album): HTMLAnchorElement {
    known.set(a.id, a); const card = node('a', '', 'sg-curated-album'); card.href = '?album=' + encodeURIComponent(a.id); card.dataset.sgAlbum = a.id;
    const cover = node('div', '', 'sg-curated-image'), photo = a.photos[0];
    if (a.coverThumb) {
      const stack = node('div', '', 'sg-album-stack');
      for (const n of [1, 0]) { const paper = node('span', '', `sg-album-paper sg-album-paper--${n}`); paper.setAttribute('aria-hidden', 'true'); paper.append(image(a.stack?.[n] || a.coverThumb, a.cover, '')); stack.append(paper); }
      const front = node('div', '', 'sg-album-front'); front.append(image(a.coverThumb, a.cover, a.title, photo?.width, photo?.height), node('span', `${a.count} 张照片 · 打开相册`, 'sg-album-open')); stack.append(front); cover.append(stack);
    } else cover.append(node('span', '原文件收藏', 'sg-curated-no-preview'));
    const meta = node('div', '', 'sg-curated-meta'), text = node('div');
    if (a.featured) text.append(node('span', '精选相册', 'sg-curated-featured'));
    text.append(node('span', a.date || '时间未标记', 'sg-curated-date'), node('h2', a.title)); if (a.description) text.append(node('p', a.description));
    const count = node('span', `${a.count} 张 `, 'sg-curated-count'), arrow = node('span', '↗'); arrow.setAttribute('aria-hidden', 'true'); count.append(arrow);
    meta.append(text, count); card.append(cover, meta); return card;
  }
  function renderAlbums(albums: Album[], append = false): void {
    if (!append) grid!.replaceChildren(); grid!.append(...albums.map(albumCard)); const count = grid!.children.length;
    grid!.classList.toggle('sg-curated-grid--one', count === 1); grid!.classList.toggle('sg-curated-grid--two', count === 2);
  }
  function photoCard(p: Photo, album?: Album, search = false): HTMLAnchorElement {
    const caption = p.caption || album?.title || '光影瞬间', link = node('a', '', 'sg-photo');
    link.href = search ? '?album=' + encodeURIComponent(p.album) : p.url;
    if (search) link.dataset.sgAlbum = p.album; else if (p.preview) link.dataset.sgPhoto = ''; else { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
    link.append(p.preview ? image(p.thumb, p.url, caption, p.width, p.height) : node('span', '打开原文件 ↗', 'sg-photo-file-label'));
    const info = node('span', '', 'sg-photo-caption'); info.append(node('b', caption)); if (p.date) info.append(node('time', p.date)); link.append(info); return link;
  }
  function makeDetail(a: Album): HTMLElement {
    const section = node('section', '', 'sg-gallery-detail'); section.dataset.sgGalleryDetail = a.id; section.dataset.loaded = '0'; section.hidden = true;
    const top = node('div', '', 'sg-gallery-detail-top'), back = node('a', '← 返回画廊', 'sg-back'); back.href = '?'; back.dataset.sgGalleryBack = '';
    top.append(back, node('span', a.date, 'sg-gallery-detail-date'));
    const title = node('div', '', 'sg-gallery-detail-title'); title.append(node('h1', a.title)); if (a.description) title.append(node('p', a.description));
    const row = node('div', '', 'sg-gallery-detail-row'); row.append(title, node('span', `共 ${a.count} 瞬间`, 'sg-gallery-detail-count'));
    const heading = node('div', '', 'sg-gallery-detail-head'); heading.append(top, row);
    const photos = node('div', '', 'sg-gallery-masonry'); photos.dataset.sgPhotoGroup = '';
    const more = node('button', '加载更多照片', 'sg-gallery-more'); more.type = 'button'; more.dataset.galleryPhotosMore = ''; more.hidden = true;
    section.append(heading, photos, more); root!.append(section); return section;
  }
  function loadPhotos(section: HTMLElement, page = 1): Promise<void> {
    const pending = photoLoads.get(section); if (pending) return pending;
    section.setAttribute('aria-busy', 'true');
    const loading = (async (): Promise<void> => {
    try {
      const data = await read<PhotoPage>('gallery-photos', { album: section.dataset.sgGalleryDetail!, page }), box = section.querySelector<HTMLElement>('[data-sg-photo-group]')!;
      if (page === 1) box.replaceChildren(); box.append(...data.photos.map(p => photoCard(p, known.get(p.album))));
      section.dataset.loaded = '1'; section.dataset.photoPage = String(data.page); section.dataset.photoPages = String(data.pages);
      section.querySelector<HTMLButtonElement>('[data-gallery-photos-more]')!.hidden = data.page >= data.pages; initLightbox();
    } finally { section.setAttribute('aria-busy', 'false'); photoLoads.delete(section); }
    })();
    photoLoads.set(section, loading); return loading;
  }
  async function route(id: string, push = false, focus = false): Promise<void> {
    const version = ++routeVersion;
    try {
      let detail = [...root!.querySelectorAll<HTMLElement>('[data-sg-gallery-detail]')].find(d => d.dataset.sgGalleryDetail === id);
      if (id && !detail) {
        let album = known.get(id); if (!album) { message('正在读取相册…'); album = (await read<AlbumPage>('gallery', { album: id })).albums[0]; }
        if (version !== routeVersion) return; if (!album) throw new Error('相册暂不可用，请返回画廊。'); known.set(id, album); detail = makeDetail(album);
      }
      if (version !== routeVersion) return;
      list!.hidden = Boolean(detail); root!.querySelectorAll<HTMLElement>('[data-sg-gallery-detail]').forEach(d => { d.hidden = d !== detail; });
      if (head) head.hidden = Boolean(detail); if (empty && detail) empty.hidden = true;
      if (push) { const url = new URL(location.href); if (detail) url.searchParams.set('album', id); else url.searchParams.delete('album'); history.pushState(null, '', url); }
      if (detail) { lastAlbum = id; if (detail.dataset.loaded !== '1') { message('正在读取照片…'); await loadPhotos(detail); } }
      if (version !== routeVersion) return; message();
      if (focus) { const target = detail?.querySelector<HTMLElement>('h1') ?? [...grid!.querySelectorAll<HTMLAnchorElement>('[data-sg-album]')].find(c => c.dataset.sgAlbum === lastAlbum); if (target) { target.tabIndex = detail ? -1 : 0; target.focus({ preventScroll: true }); } window.scrollTo({ top: 0, behavior: 'auto' }); }
    } catch (e) { if (version === routeVersion) message(e instanceof Error ? e.message : '读取失败，请重试。'); }
  }
  async function search(): Promise<void> {
    const version = ++searchVersion; searchController?.abort(); const controller = new AbortController(); searchController = controller;
    query = input?.value.trim() || ''; await route('', location.search.includes('album='));
    if (version !== searchVersion) return;
    if (!query) { albumPage = 1; albumPages = initial.pages; renderAlbums(baseAlbums); if (found) found.hidden = true; results?.replaceChildren(); if (empty) empty.hidden = true; if (moreSearch) moreSearch.hidden = true; if (moreAlbums) moreAlbums.hidden = baseAlbums.length >= initial.total; return; }
    message('正在查找收藏…');
    try {
      const [a, p] = await Promise.all([read<AlbumPage>('gallery', { q: query }, controller.signal), read<PhotoPage>('gallery-search', { q: query }, controller.signal)]);
      if (version !== searchVersion) return;
      renderAlbums(a.albums); albumPage = a.page; albumPages = a.pages; searchPage = p.page; searchPages = p.pages;
      results?.replaceChildren(...p.photos.map(photo => photoCard(photo, known.get(photo.album), true)));
      const count = root!.querySelector('[data-sg-gallery-photos-count]'); if (count) count.textContent = `${p.total} 张`;
      if (found) found.hidden = !p.total; if (empty) empty.hidden = a.total > 0 || p.total > 0;
      if (moreAlbums) moreAlbums.hidden = a.page >= a.pages; if (moreSearch) moreSearch.hidden = p.page >= p.pages; message();
    } catch (e) { if (version === searchVersion) message(e instanceof Error && e.name !== 'AbortError' ? e.message : '读取超时，请重试。'); }
  }
  root.addEventListener('click', event => {
    const target = (event.target as Element).closest<HTMLElement>('[data-sg-album],[data-sg-gallery-back],[data-gallery-photos-more]');
    if (!target || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return; event.preventDefault();
    if (target.hasAttribute('data-sg-gallery-back')) { void route('', true, true); }
    else if (target.dataset.sgAlbum) { void route(target.dataset.sgAlbum, true, true); }
    else { const section = target.closest<HTMLElement>('[data-sg-gallery-detail]')!, button = target as HTMLButtonElement; if (button.disabled) return; button.disabled = true; void loadPhotos(section, Number(section.dataset.photoPage || 1) + 1).catch(e => message(e.message)).finally(() => { button.disabled = false; }); }
  });
  moreAlbums?.addEventListener('click', async () => {
    moreAlbums!.disabled = true; const requestedQuery = query, version = searchVersion;
    try { const page = query ? albumPage + 1 : Math.floor(baseAlbums.length / 24) + 1, data = await read<AlbumPage>('gallery', { page, q: query }); if (version !== searchVersion || query !== requestedQuery) return; renderAlbums(data.albums, true); albumPage = data.page; albumPages = data.pages; if (!query) baseAlbums = [...baseAlbums, ...data.albums]; moreAlbums!.hidden = albumPage >= albumPages; }
    catch (e) { message(e instanceof Error ? e.message : '读取失败'); } finally { moreAlbums!.disabled = false; }
  });
  moreSearch?.addEventListener('click', async () => {
    moreSearch!.disabled = true; const version = searchVersion;
    try { const data = await read<PhotoPage>('gallery-search', { page: searchPage + 1, q: query }); if (version !== searchVersion) return; results?.append(...data.photos.map(p => photoCard(p, known.get(p.album), true))); searchPage = data.page; searchPages = data.pages; moreSearch!.hidden = searchPage >= searchPages; }
    catch (e) { message(e instanceof Error ? e.message : '读取失败'); } finally { moreSearch!.disabled = false; }
  });
  let timer = 0; const schedule = (): void => { clearTimeout(timer); timer = pageTimeout(() => { void search(); }, 200); };
  input?.addEventListener('input', e => { if (!(e as InputEvent).isComposing) schedule(); }); input?.addEventListener('compositionend', schedule);
  input?.addEventListener('keydown', e => { if (e.isComposing) return; if (e.key === 'Enter' || e.key === 'Escape') { e.preventDefault(); if (e.key === 'Escape') input.value = ''; clearTimeout(timer); void search(); } });
  pageOn(window, 'popstate', () => { void route(new URL(location.href).searchParams.get('album') || '', false, true); });
  root.querySelectorAll<HTMLImageElement>('img').forEach(img => {
    const a = img.closest<HTMLElement>('[data-sg-album]'), original = a ? known.get(a.dataset.sgAlbum!)?.cover : img.closest<HTMLAnchorElement>('[data-sg-photo]')?.href;
    let retried = false; img.addEventListener('error', () => { if (!retried && original && img.src !== original) { retried = true; img.src = original; } else { img.hidden = true; img.parentElement?.append(node('span', '暂无法预览', 'sg-curated-no-preview')); } });
  });
  void route(new URL(location.href).searchParams.get('album') || '');
}
