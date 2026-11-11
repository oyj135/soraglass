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
        'plus'        => '<path d="M12 5v14M5 12h14"/>',
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
