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
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
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
        'map-pin'     => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/>',
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
    ];
    if (!isset($paths[$name])) {
        return '';
    }
    $cls = $class !== '' ? ' class="' . sg_e($class) . '"' : '';
    return '<svg' . $cls . ' viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

/** 社交图标名 → 站点图标；未收录时退化为 link 图标 */
function sg_social_icon(array $item): string
{
    $key = strtolower(trim((string) ($item['icon'] ?? '')));
    if ($key === '') {
        $name = strtolower((string) ($item['name'] ?? ''));
        foreach (['github' => 'github', 'mail' => 'mail', '邮箱' => 'mail', 'bilibili' => 'bilibili', '哔哩' => 'bilibili', 'rss' => 'rss', 'x' => 'twitter', 'twitter' => 'twitter'] as $needle => $icon) {
            if ($name !== '' && str_contains($name, $needle)) {
                return $icon;
            }
        }
        return 'link';
    }
    return $key;
}
