<?php
/**
 * 内联 SVG 图标（不依赖图标库，路径取自 Feather/MIT 风格 24×24 线性图标）。
 * 用法：<?php echo sg_icon('search'); ?>
 *      <?php echo sg_icon('github', 'sg-brand-icon'); ?>
 */
function sg_icon(string $name, string $class = ''): string
{
    $paths = [
        'search'      => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'arrow-left'  => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'arrow-up-right' => '<path d="M7 17 17 7"/><path d="M9 7h8v8"/>',
        'arrow-up'    => '<path d="M12 19V5"/><path d="m5 12 7-7 7 7"/>',
        'arrow-down'  => '<path d="M12 5v14"/><path d="m19 12-7 7-7-7"/>',
        'settings'    => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        /* 源站 card-more-btn 的 fas fa-angle-right：实心细角（>），比线性 chevron 更贴源码 */
        'angle-right'   => '<path d="M10.5 6 16 12 10.5 18 9.1 16.6 13.2 12 9.1 7.4Z" fill="currentColor" stroke="none"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'chevron-up'    => '<path d="m18 15-6-6-6 6"/>',
        'prev'        => '<path d="M18 5v14"/><path d="M5 12h11"/><path d="m16 19-7-7 7-7"/>',
        'next'        => '<path d="M6 5v14"/><path d="M8 12h11"/><path d="m8 5 7 7-7 7"/>',
        'play'        => '<path d="m7 4 12 8-12 8z" fill="currentColor" stroke="none"/>',
        'pause'       => '<path d="M8 5v14M16 5v14" stroke-width="3"/>',
        'sun'         => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'        => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'music'       => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'sliders'     => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        /* 文章头图元信息：日历（发表于）与眼睛（阅读量），同套 24×24 线性风格 */
        'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
        'type'        => '<path d="M4 7V5h16v2"/><path d="M12 5v14"/><path d="M9 19h6"/>',
        'eye'         => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        'tag'         => '<path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2" fill="currentColor" stroke="none"/>',
        'folder'      => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        'image'       => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 18 5-5 4 4 3-3 4 4"/>',
        'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'link'        => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
        'github'      => '<path d="M9 19c-4 1.4-4-2.5-6-3m12 6v-3.9a3.4 3.4 0 0 0-.9-2.6c3-.3 6.2-1.5 6.2-6.7A5.3 5.3 0 0 0 18.9 4a4.9 4.9 0 0 0-.1-3.5s-1.1-.3-3.6 1.4a12.3 12.3 0 0 0-6.4 0C6.3 0.3 5.2 0.6 5.2 0.6A4.9 4.9 0 0 0 5.1 4a5.3 5.3 0 0 0-1.4 3.8c0 5.2 3.2 6.4 6.2 6.7a3.4 3.4 0 0 0-.9 2.6V22"/>',
        'rss'         => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1.6" fill="currentColor" stroke="none"/>',
        'bilibili'    => '<rect x="2" y="6" width="20" height="14" rx="4"/><path d="M7 6 5 3M17 6l2-3"/><path d="M9 11v4M15 11v4"/>',
        'twitter'     => '<path d="M4 4l7.5 9.5L4.5 20h2.3l5.8-6.3L17.5 20H20l-7.8-10L19.4 4h-2.3l-5.3 5.8L7.3 4z"/>',
        'close'       => '<path d="M6 6l12 12M18 6 6 18"/>',
        /* 页脚心跳（2026-11-19 第二十二轮 A4，对齐源站 #heartbeat）：实心心形 */
        'heart'       => '<path d="M12 20.6C7.2 17 3.5 13.6 3.5 9.7 3.5 7 5.5 5 8 5c1.6 0 3.1.8 4 2.1C13 5.8 14.5 5 16 5c2.5 0 4.5 2 4.5 4.7 0 3.9-3.7 7.3-8.5 10.9z" fill="currentColor" stroke="none"/>',
        'map-pin'     => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
        'pin'         => '<path d="M9 3h6l-.9 6.3 2.9 2.7H7l2.9-2.7z"/><path d="M12 12v9"/>',
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
        /* 音乐页与选刊用到的补充图标（同一套 24×24 线性风格） */
        'repeat'      => '<path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'repeat-one'  => '<path d="m17 2 4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/><path d="M11 10h1v5"/>',
        'shuffle'     => '<path d="M16 3h5v5"/><path d="M4 20 21 3"/><path d="M21 16v5h-5"/><path d="m15 15 6 6"/><path d="M4 4l5 5"/>',
        'volume'      => '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/>',
        'volume-x'    => '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="m16 9 5 6M21 9l-5 6"/>',
        'activity'    => '<path d="M3 12h4l3 8 4-16 3 8h4"/>',
        'bookmark'    => '<path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/>',
        'history'     => '<path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/><path d="M12 8v4l3 2"/>',
        'check'       => '<path d="m4 12 5 5L20 6"/>',
        'disc'        => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/>',
        'sliders-h'   => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>',
        'minus'       => '<path d="M5 12h14"/>',
        /* 项目卡（第三十七轮）：星数与分支数改成图标 + 数字，比裸「★ 3」清楚 */
        'star'        => '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
        'fork'        => '<circle cx="6" cy="5" r="2"/><circle cx="18" cy="5" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6 7v2.5A2.5 2.5 0 0 0 8.5 12h7a2.5 2.5 0 0 0 2.5-2.5V7"/><path d="M12 12v5"/>',
        /* P1：文章分享、代码复制、阅读模式 */
        'share'       => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 10.5 6.8-4M8.6 13.5l6.8 4"/>',
        'copy'        => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
        'book-open'   => '<path d="M2 4h6a4 4 0 0 1 4 4v12a3 3 0 0 0-3-3H2z"/><path d="M22 4h-6a4 4 0 0 0-4 4v12a3 3 0 0 1 3-3h7z"/>',
        'user'        => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        /* 导航条目图标（2026-11-17，对齐 Fomalhaut 菜单带图标的形态） */
        'home'        => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'archive'     => '<rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/>',
        'message'     => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'code'        => '<path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/>',
        'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'thumbs-up'   => '<path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3z"/><path d="M7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>',
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    $cls = $class !== '' ? ' class="' . sg_e($class) . '"' : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

/**
 * 语义信息图标：多色填充（2026-11-18，对齐源站 iconfont 多色 sprite 的观感，
 * 路径自绘不抄源站）。主色走 --sg-icon-* 语义 token（昼夜各一档），
 * 副色由 color-mix 叠白派生，白色细节恒 #fff。
 * 未收录的名字回落线条版 sg_icon，调用点可安全混用。
 * 用法：<?php echo sg_icon_color('calendar'); ?>
 */
function sg_icon_color(string $name, string $class = ''): string
{
    /* glyph 内 {m} = 主色、{l} = 副色（主色叠白 55%）；族名对应 tokens.css 的 --sg-icon-*。 */
    $glyphs = [
        'clock'    => ['time', '<circle cx="12" cy="12" r="9" fill="{l}"/><circle cx="12" cy="12" r="9" fill="none" stroke="{m}" stroke-width="1.8"/><path d="M12 7v5l3.2 2.2" fill="none" stroke="{m}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>'],
        'calendar' => ['time', '<rect x="3" y="5" width="18" height="16" rx="3" fill="{l}"/><path d="M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v2H3z" fill="{m}"/><rect x="7" y="2.5" width="2" height="5" rx="1" fill="{m}"/><rect x="15" y="2.5" width="2" height="5" rx="1" fill="{m}"/><circle cx="8" cy="14" r="1.3" fill="#fff"/><circle cx="12" cy="14" r="1.3" fill="#fff"/><circle cx="16" cy="14" r="1.3" fill="#fff"/><circle cx="8" cy="17.5" r="1.3" fill="#fff"/><circle cx="12" cy="17.5" r="1.3" fill="#fff"/>'],
        'folder'   => ['cate', '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="{m}"/><path d="M3 10h18v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" fill="{l}"/>'],
        'tag'      => ['tag', '<path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8z" fill="{m}"/><path d="M20.6 13.4 12 22l-3-3 8.6-8.6z" fill="{l}"/><circle cx="7.5" cy="7.5" r="1.6" fill="#fff"/>'],
        'update'   => ['data', '<path d="M20 12a8 8 0 1 1-2.34-5.66" fill="none" stroke="{m}" stroke-width="2.4" stroke-linecap="round"/><path d="M20 3v4h-4" fill="none" stroke="{l}" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'],
        'eye'      => ['data', '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z" fill="{l}"/><circle cx="12" cy="12" r="3.4" fill="{m}"/><circle cx="12" cy="12" r="1.4" fill="#fff"/>'],
        'comment'  => ['data', '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" fill="{m}"/><circle cx="8" cy="10.5" r="1.2" fill="#fff"/><circle cx="12" cy="10.5" r="1.2" fill="#fff"/><circle cx="16" cy="10.5" r="1.2" fill="#fff"/>'],
        'chart'    => ['data', '<rect x="4" y="12" width="4" height="8" rx="1" fill="{l}"/><rect x="10" y="7" width="4" height="13" rx="1" fill="{m}"/><rect x="16" y="10" width="4" height="10" rx="1" fill="{l}"/>'],
        'type'     => ['data', '<rect x="4" y="4" width="16" height="3" rx="1.5" fill="{m}"/><rect x="10.5" y="4" width="3" height="16" rx="1.5" fill="{m}"/><rect x="8" y="18" width="8" height="2.5" rx="1.25" fill="{l}"/>'],
        'star'     => ['mark', '<path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z" fill="{m}"/><circle cx="18.5" cy="5" r="1.2" fill="{l}"/><circle cx="5.5" cy="18.5" r="1" fill="{l}"/>'],
    ];
    if (!isset($glyphs[$name])) {
        return sg_icon($name, $class);
    }
    [$family, $inner] = $glyphs[$name];
    $main = 'var(--sg-icon-' . $family . ')';
    $light = 'color-mix(in srgb, var(--sg-icon-' . $family . ') 55%, #fff)';
    $inner = str_replace(['{m}', '{l}'], [$main, $light], $inner);
    $cls = 'sg-ci' . ($class !== '' ? ' ' . $class : '');
    return '<svg class="' . sg_e($cls) . '" viewBox="0 0 24 24" aria-hidden="true">' . $inner . '</svg>';
}

/**
 * 社交品牌图标：多色填充 SVG（2026-11-19，对齐源站 iconfont 彩色 sprite 的观感）。
 * 源站社交图标是 iconfont 项目导出的多色 symbol（<svg><use xlink:href="#icon-xx">），
 * 每枚自带品牌色 fill；soraglass 不引外部图标库，路径自绘（品牌几何近似 + 官方品牌色），
 * 未收录的名字回落线条版 sg_icon，调用点可安全混用。
 * github 这类单色品牌走 currentColor（昼夜亮度由 --sg-brand-* token 控制），
 * 其余品牌在 glyph 内写死官方色，CSS 的 color/fill 不会覆盖内联 fill。
 * 用法：<?php echo sg_social_svg(sg_social_icon($item)); ?>
 */
function sg_social_svg(string $name): string
{
    $glyphs = [
        'wechat'   => '<path d="M9.6 3.3c-3.7 0-6.7 2.9-6.7 6.5 0 2 .9 3.8 2.4 5L4.6 17.9l3.3-1.6c.5.1 1.1.2 1.7.2 3.7 0 6.7-2.9 6.7-6.7s-3-6.5-6.7-6.5z" fill="#2DC100"/><circle cx="7.4" cy="8.7" r="1.05" fill="#fff"/><circle cx="11.8" cy="8.7" r="1.05" fill="#fff"/><path d="M14 19.6l-.8 2.3 2.6-1.3c.3 0 .7.1 1.1.1 2.6 0 4.8-1.8 4.8-4.1s-2.2-4.1-4.8-4.1-4.8 1.8-4.8 4.1c0 1.2.6 2.3 1.5 3z" fill="#9BE45C" stroke="#fff" stroke-width="1.1" stroke-linejoin="round"/><circle cx="15.8" cy="14.9" r=".8" fill="#fff"/><circle cx="19.2" cy="14.9" r=".8" fill="#fff"/>',
        'qq'       => '<path d="M12 2.4a6.4 6.4 0 0 0-6.4 6.4c0 1.2.2 2.3.6 3.3-1 1.8-2.1 4.5-2.1 6.4 0 1.7 1 2.4 1.9 2.4 1.4 0 2.4-1.2 3-2.8h6c.6 1.6 1.6 2.8 3 2.8.9 0 1.9-.7 1.9-2.4 0-1.9-1.1-4.6-2.1-6.4.4-1 .6-2.1.6-3.3A6.4 6.4 0 0 0 12 2.4z" fill="#12B7F5"/><ellipse cx="9.8" cy="8.1" rx="2.3" ry="2.7" fill="#fff"/><ellipse cx="14.2" cy="8.1" rx="2.3" ry="2.7" fill="#fff"/><circle cx="9.8" cy="7.9" r="1.15" fill="#182B44"/><circle cx="14.2" cy="7.9" r="1.15" fill="#182B44"/><path d="M10.4 10.6h3.2L12 12.6z" fill="#F5A623"/><ellipse cx="12" cy="16.4" rx="3.3" ry="2.8" fill="#fff"/>',
        'bilibili' => '<path d="M7.4 3.2 9.2 5.9M16.6 3.2 14.8 5.9" fill="none" stroke="#23ADE5" stroke-width="1.8" stroke-linecap="round"/><rect x="2.4" y="5.9" width="19.2" height="13.8" rx="4.2" fill="#23ADE5"/><rect x="7.6" y="10" width="2.1" height="5.6" rx="1.05" fill="#fff"/><rect x="14.3" y="10" width="2.1" height="5.6" rx="1.05" fill="#fff"/>',
        'mail'     => '<rect x="2.4" y="4.9" width="19.2" height="14.2" rx="3.2" fill="#3D7BE0"/><path d="M5.6 4.9h12.8a3.2 3.2 0 0 1 3.2 3.2v.7L12 14.4 2.4 8.8v-.7a3.2 3.2 0 0 1 3.2-3.2z" fill="#6BA6FF"/><path d="m2.4 8.8 9.6 5.6 9.6-5.6" fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>',
        'leetcode' => '<path class="sg-si-lc-c" d="M12.4 4.9a7.1 7.1 0 1 0 0 14.2" fill="none" stroke="#1E2A38" stroke-width="3.4" stroke-linecap="round"/><path d="m13.2 4.6 4.3-2.7" fill="none" stroke="#FFA116" stroke-width="3.2" stroke-linecap="round"/><path d="M13 19.1h5.4" fill="none" stroke="#FFA116" stroke-width="3.2" stroke-linecap="round"/>',
        'rss'      => '<circle cx="5.3" cy="18.7" r="2.4" fill="#F7841D"/><path d="M3.4 12.4a8.2 8.2 0 0 1 8.2 8.2" fill="none" stroke="#F7841D" stroke-width="3" stroke-linecap="round"/><path d="M3.4 4.6A16.2 16.2 0 0 1 19.6 20.8" fill="none" stroke="#FBB03B" stroke-width="3" stroke-linecap="round"/>',
        'twitter'  => '<path d="M22 5.8c-.7.3-1.5.5-2.3.6a4.1 4.1 0 0 0 1.8-2.2c-.8.5-1.7.8-2.6 1a4.1 4.1 0 0 0-7 3.7A11.6 11.6 0 0 1 3.4 4.6a4.1 4.1 0 0 0 1.3 5.5c-.7 0-1.3-.2-1.9-.5v.1c0 2 1.4 3.6 3.3 4a4.2 4.2 0 0 1-1.9.1 4.1 4.1 0 0 0 3.8 2.8A8.2 8.2 0 0 1 2 18.3a11.6 11.6 0 0 0 6.3 1.8c7.5 0 11.7-6.3 11.7-11.7v-.5c.8-.6 1.5-1.3 2-2.1z" fill="#1DA1F2"/>',
        'github'   => '<path d="M12 2a10 10 0 0 0-3.2 19.5c.5.1.7-.2.7-.5v-2c-2.8.6-3.4-1.2-3.4-1.2-.4-1.1-1-1.4-1-1.4-.9-.6.1-.6.1-.6 1 .1 1.5 1 1.5 1 .9 1.5 2.3 1.1 2.9.8.1-.7.4-1.1.7-1.4-2.2-.2-4.6-1.1-4.6-5 0-1.1.4-2 1-2.7-.1-.3-.4-1.3.1-2.7 0 0 .8-.3 2.7 1a9.3 9.3 0 0 1 5 0c1.9-1.3 2.7-1 2.7-1 .5 1.4.2 2.4.1 2.7.6.7 1 1.6 1 2.7 0 3.9-2.4 4.8-4.6 5 .4.3.7 1 .7 2v3c0 .3.2.6.7.5A10 10 0 0 0 12 2z" fill="currentColor"/>',
    ];
    if (!isset($glyphs[$name])) {
        return sg_icon($name);
    }
    return '<svg class="sg-si" viewBox="0 0 24 24" aria-hidden="true">' . $glyphs[$name] . '</svg>';
}

/** 社交图标名 → 站点图标；未收录时退化为 link 图标 */
function sg_social_icon(array $item): string
{
    $key = strtolower(trim((string) ($item['icon'] ?? '')));
    if ($key === '') {
        $name = strtolower((string) ($item['name'] ?? ''));
        foreach (['github' => 'github', 'mail' => 'mail', '邮箱' => 'mail', 'bilibili' => 'bilibili', '哔哩' => 'bilibili', 'b站' => 'bilibili', 'rss' => 'rss', 'x' => 'twitter', 'twitter' => 'twitter', '微信' => 'wechat', 'weixin' => 'wechat', 'wechat' => 'wechat', 'qq' => 'qq', '力扣' => 'leetcode', 'leetcode' => 'leetcode'] as $needle => $icon) {
            if ($name !== '' && str_contains($name, $needle)) {
                return $icon;
            }
        }
        return 'link';
    }
    return $key;
}

/**
 * 社交图标的品牌色 class：图标名 → sg-social--<brand>。
 * 未收录的图标名退到 link（青瓷），避免 class 拼出 CSS 非法字符；
 * 与 sg_social_icon() 配套，供作者卡社交行按平台着色（2026-11-18；
 * 2026-11-19 补 wechat/qq/leetcode 三品牌，与 sg_social_svg() 的多色 glyph 对齐）。
 */
function sg_social_brand_class(array $item): string
{
    $name = sg_social_icon($item);
    $brand = in_array($name, ['rss', 'github', 'mail', 'bilibili', 'twitter', 'wechat', 'qq', 'leetcode'], true) ? $name : 'link';
    return 'sg-social--' . $brand;
}
