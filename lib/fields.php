<?php
/**
 * 文章自定义字段读取。
 * 源项目 frontmatter 的 cover / description 在此映射为 Typecho 自定义字段。
 */
function sg_field($archive, string $key, string $default = ''): string
{
    $value = $archive->fields->{$key};
    return (is_string($value) && $value !== '') ? $value : $default;
}

function sg_cover($archive): string
{
    return sg_field($archive, 'cover', sg_opt('sgDefaultCover', sg_theme_url('dist/img/cover-default.svg')));
}

function sg_description($archive): string
{
    $custom = sg_field($archive, 'description', '');
    if ($custom !== '') {
        return $custom;
    }
    return sg_e($archive->excerpt(120, '...'));
}

/** 安全地渲染主题配置中的 JSON 字段值 */
function sg_value($row, string $key, string $default = ''): string
{
    if (!is_array($row) || !isset($row[$key]) || !is_scalar($row[$key]) || (string) $row[$key] === '') {
        return $default;
    }
    return sg_e((string) $row[$key]);
}

/**
 * 静态数据（JSON）里的媒体地址归一化。
 * 外链 / 站内绝对路径原样返回；写成 `dist/img/x.svg` 这种主题相对路径时补上主题前缀，
 * 否则浏览器会按当前页面 URL 解析成 /dist/... 导致 404。
 */
function sg_media(string $value, string $fallback = ''): string
{
    $value = trim($value);
    if ($value === '') {
        return $fallback;
    }
    if (preg_match('#^(?:https?:)?//#i', $value) === 1
        || str_starts_with($value, 'data:')
        || str_starts_with($value, '/')) {
        return $value;
    }
    return sg_theme_url($value);
}

/** 文章分类列表（名称 + 链接），取不到时返回空数组 */
function sg_categories($archive): array
{
    try {
        $cats = $archive->categories;
        if (!is_array($cats)) {
            return [];
        }
        $out = [];
        foreach ($cats as $cat) {
            if (is_array($cat) && isset($cat['name'])) {
                $out[] = ['name' => (string) $cat['name'], 'permalink' => (string) ($cat['permalink'] ?? '')];
            } elseif (is_object($cat) && isset($cat->name)) {
                $out[] = ['name' => (string) $cat->name, 'permalink' => (string) ($cat->permalink ?? '')];
            }
        }
        return $out;
    } catch (\Throwable $e) {
        return [];
    }
}

/** 取文章的第一个分类名，用于封面徽章（取不到时返回默认值） */
function sg_first_category($archive, string $default = '文章'): string
{
    $cats = sg_categories($archive);
    return $cats ? $cats[0]['name'] : $default;
}

/** 以字符串形式取摘要（excerpt 是 echo 型方法，这里用缓冲转为字符串） */
function sg_excerpt($archive, int $length = 60, string $suffix = '...'): string
{
    try {
        ob_start();
        $archive->excerpt($length, $suffix);
        return trim((string) ob_get_clean());
    } catch (\Throwable $e) {
        return '';
    }
}

/** 把标签渲染成一组胶囊（保留原生标签链接） */
function sg_tag_chips($archive): string
{
    try {
        ob_start();
        $archive->tags('@@SG@@', false, '');
        $raw = trim((string) ob_get_clean());
    } catch (\Throwable $e) {
        return '';
    }
    if ($raw === '') {
        return '';
    }
    $html = '';
    foreach (array_filter(array_map('trim', explode('@@SG@@', $raw))) as $item) {
        $item = str_replace('<a ', '<a class="sg-tag-link" ', (string) $item);
        $html .= '<span class="sg-meta-chip is-pink">#' . $item . '</span>';
    }
    return $html;
}
