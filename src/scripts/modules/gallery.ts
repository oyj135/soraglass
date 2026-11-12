/**
 * 光影画廊（page-photowall.php）：列表 ↔ 专辑详情 + 搜索分组。
 *
 * Typecho 没有二级路由，源站的 /photowall/[album] 只能用单页状态模拟。
 * 用 history.pushState 写 ?album=<id>，浏览器后退键自然回到列表，
 * 刷新也能停在当前专辑 —— 这是唯一「看起来像路由」又不改 Nginx 的做法。
 *
 * 搜索对齐源站 PhotoWallClient：
 *   命中「单张照片」→ 单独一组瀑布流（标题带数量）
 *   命中「相册」→ 相关相册分组标题 + 过滤后的拍立得
 * 两组都可能为空，空则连标题一起收起来。
 *
 * DOM 契约：
 *   根      [data-sg-gallery]
 *   列表    [data-sg-gallery-list]
 *   专辑卡  [data-sg-album="<id>" data-keywords="…"]
 *   打开    [data-sg-album-open="<id>"]
 *   详情    [data-sg-gallery-detail="<id>"] hidden
 *   返回    [data-sg-gallery-back]
 *   空态    [data-sg-gallery-empty]
 *   页头    .sg-page-head（进详情 hidden，回列表恢复；服务端也按 ?album= 预藏）
 *   搜索    [data-sg-gallery-found] + [data-sg-gallery-photos-head|photos|albums-head]
 *   图       [data-search-photo data-caption data-album-name]
 */

const DEBOUNCE = 250;
const PARAM = 'album';

export function initGallery(): void {
  const root = document.querySelector<HTMLElement>('[data-sg-gallery]');
  if (!root) return;

  const list = root.querySelector<HTMLElement>('[data-sg-gallery-list]');
  const details = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-gallery-detail]'));
  const cards = Array.from(root.querySelectorAll<HTMLElement>('[data-sg-album]'));
  const empty = root.querySelector<HTMLElement>('[data-sg-gallery-empty]');
  /* 详情是「二级路由」，页头（大标题 + 副标题 + 搜索）属于列表，进详情整块收起。
     服务端对 ?album=<id> 已经先藏过一次（避免刷新闪标题），这里负责来回切。 */
  const head = root.querySelector<HTMLElement>('.sg-page-head');
  const input = root.querySelector<HTMLInputElement>('input.sg-search-input')
    ?? document.querySelector<HTMLInputElement>('main input.sg-search-input');

  if (!list || !details.length) return;

  const setHead = (visible: boolean): void => {
    if (head) head.hidden = !visible;
  };

  const show = (id: string, push: boolean): void => {
    const known = details.some((d) => d.dataset.sgGalleryDetail === id);
    list.hidden = known;
    details.forEach((detail) => {
      detail.hidden = detail.dataset.sgGalleryDetail !== id;
    });
    if (!known) {
      setHead(true);
      return;
    }
    setHead(false);

    if (push) {
      try {
        const url = new URL(window.location.href);
        url.searchParams.set(PARAM, id);
        window.history.pushState({ album: id }, '', url.toString());
      } catch {
        /* 受限环境忽略 */
      }
    }
    window.scrollTo({ top: 0, behavior: 'auto' });
  };

  const showList = (push: boolean): void => {
    list.hidden = false;
    details.forEach((detail) => {
      detail.hidden = true;
    });
    setHead(true);
    if (push) {
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete(PARAM);
        window.history.pushState(null, '', url.toString());
      } catch {
        /* 受限环境忽略 */
      }
    }
  };

  cards.forEach((card) => {
    const id = card.dataset.sgAlbum ?? '';
    const openers = Array.from(card.querySelectorAll<HTMLElement>('[data-sg-album-open]'));
    const targets = openers.length ? openers : [card];
    targets.forEach((el) => {
      el.addEventListener('click', () => show(id, true));
    });
  });

  root.querySelectorAll<HTMLElement>('[data-sg-gallery-back]').forEach((btn) => {
    btn.addEventListener('click', () => showList(true));
  });

  // 后退/前进：URL 里没有 album 就回列表，有就进对应专辑
  window.addEventListener('popstate', () => {
    let id = '';
    try {
      id = new URL(window.location.href).searchParams.get(PARAM) ?? '';
    } catch {
      id = '';
    }
    if (id) show(id, false);
    else showList(false);
  });

  // 刷新或带参进入时还原到对应专辑
  let initial = '';
  try {
    initial = new URL(window.location.href).searchParams.get(PARAM) ?? '';
  } catch {
    initial = '';
  }

  /* ---------------- 搜索 ---------------- */
  const found = root.querySelector<HTMLElement>('[data-sg-gallery-found]');
  const photosHead = root.querySelector<HTMLElement>('[data-sg-gallery-photos-head]');
  const photosBox = root.querySelector<HTMLElement>('[data-sg-gallery-photos]');
  const photosCount = root.querySelector<HTMLElement>('[data-sg-gallery-photos-count]');
  const albumsHead = root.querySelector<HTMLElement>('[data-sg-gallery-albums-head]');
  const albumsCount = root.querySelector<HTMLElement>('[data-sg-gallery-albums-count]');
  const photos = Array.from(root.querySelectorAll<HTMLElement>('[data-search-photo]'));

  if (input) {
    const albumKeywords = cards.map((el) => (el.dataset.keywords ?? '').toLowerCase());
    let timer = 0;

    const applySearch = (): void => {
      const query = input.value.trim().toLowerCase();

      if (query === '') {
        if (found) found.hidden = true;
        if (photosHead) photosHead.hidden = true;
        if (photosBox) photosBox.hidden = true;
        if (albumsHead) albumsHead.hidden = true;
        cards.forEach((card) => card.classList.remove('sg-filtered-out'));
        if (empty) empty.hidden = true;
        return;
      }

      // 搜索时强制回列表，否则用户在详情里搜什么都没反应
      showList(false);

      // 单张照片：图注或相册名命中
      let photoHits = 0;
      photos.forEach((photo) => {
        const haystack = `${photo.dataset.caption ?? ''} ${photo.dataset.albumName ?? ''}`.toLowerCase();
        const hit = haystack.includes(query);
        photo.classList.toggle('sg-filtered-out', !hit);
        if (hit) photoHits += 1;
      });

      const showPhotos = photoHits > 0;
      if (found) found.hidden = !showPhotos;
      if (photosHead) photosHead.hidden = !showPhotos;
      if (photosBox) photosBox.hidden = !showPhotos;
      if (photosCount) photosCount.textContent = String(photoHits);

      // 相关相册
      let albumHits = 0;
      cards.forEach((card, i) => {
        const hit = (albumKeywords[i] ?? '').includes(query);
        card.classList.toggle('sg-filtered-out', !hit);
        if (hit) albumHits += 1;
      });

      if (albumsHead) albumsHead.hidden = albumHits === 0;
      if (albumsCount) albumsCount.textContent = String(albumHits);
      if (empty) empty.hidden = photoHits > 0 || albumHits > 0;

      // 淡入放在这里而不是防抖回调里：按 Enter 是直接调 applySearch 的，
      // 若只在回调里复位，回车后那 250ms 内容虽已就绪却仍是透明的。
      if (found) found.style.opacity = '';
    };

    // 250ms 防抖 + 淡出淡入，避免每敲一个字都重排一次瀑布流
    input.addEventListener('input', () => {
      window.clearTimeout(timer);
      if (found) found.style.opacity = '0';
      timer = window.setTimeout(applySearch, DEBOUNCE);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        input.value = '';
        applySearch();
      }
      // 这个输入框是站内搜索表单的一部分，必须拦住 Enter，否则会被带到首页搜索
      if (event.key === 'Enter') {
        event.preventDefault();
        applySearch();
      }
    });
  }

  if (initial) show(initial, false);
}
