<?php
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Radio;

if (!defined('SG_ROOT')) {
    define('SG_ROOT', __DIR__);
}
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/assets.php';
require_once __DIR__ . '/lib/fields.php';
require_once __DIR__ . '/lib/icons.php';

/**
 * 主题设置。Typecho 1.3 中 Textarea / Select 必须提供第 4 个参数 $label。
 * 值以 JSON 存在 options 表 theme:<主题目录名>，模板用 $this->options->字段名 读取。
 */
function themeConfig($form): void
{
    $form->addInput(new Text('sgAuthorName', null, '博主', _t('博主昵称'), _t('显示在首页个人卡片')));
    $form->addInput(new Text('sgAvatarUrl', null, '', _t('头像地址'), _t('留空使用主题内置 SVG 头像')));
    $form->addInput(new Text('sgNavTitle', null, '', _t('导航前缀'), _t('留空则使用站点名称')));
    $form->addInput(new Text('sgNavSuffix', null, 'の', _t('导航分隔符'), _t('例：の')));
    $form->addInput(new Text('sgNavAfter', null, '空璃', _t('导航后缀'), _t('例：空璃')));

    $form->addInput(new Textarea('sgBgImages', null, '', _t('背景壁纸'), _t('每行一个图片 URL，留空使用主题内置渐变壁纸')));
    $form->addInput(new Text('sgThemeColors', null, '#a18cd1,#fbc2eb,#a1c4fd,#c2e9fb', _t('呼吸渐变色'), _t('英文逗号分隔的十六进制色值')));
    $form->addInput(new Text('sgDefaultCover', null, '', _t('默认文章封面'), _t('文章未设置 cover 字段时使用')));
    $form->addInput(new Textarea('sgDanmaku', null, '', _t('背景弹幕'), _t('每行一条，留空使用 dist/data/danmaku.json')));

    $form->addInput(new Textarea('sgSocial', null, '', _t('社交链接 JSON'), _t('格式：[{"name":"GitHub","url":"...","icon":"github"}]')));
    $form->addInput(new Textarea('sgFriends', null, '', _t('友链 JSON'), _t('留空使用 dist/data/friends.json')));
    $form->addInput(new Textarea('sgProjects', null, '', _t('项目 JSON'), _t('留空使用 dist/data/projects.json')));
    $form->addInput(new Textarea('sgAlbums', null, '', _t('相册 JSON'), _t('留空使用 dist/data/albums.json')));
    $form->addInput(new Textarea('sgPlaylist', null, '', _t('歌单 JSON'), _t('留空使用 dist/data/playlist.json；填本地或可直链的音频 URL')));
    $form->addInput(new Textarea('sgBriefing', null, '', _t('每日选刊 JSON'), _t('数组，每项含 date/title/desc/cover/label/meta；留空使用 dist/data/briefing.json')));
    $form->addInput(new Textarea('sgFooterBadges', null, '', _t('页脚徽章 JSON'), _t('格式：[{"name":"Typecho","color":"#6366f1"}]')));
    $form->addInput(new Text('sgIcp', null, '', _t('备案号'), _t('阿里云国内域名需备案，填入后显示在页脚仪表盘')));

    $form->addInput(new Text('sgStatPosts', null, '', _t('统计·文章数'), _t('留空自动统计已发布文章')));
    $form->addInput(new Text('sgStatNotes', null, '', _t('统计·杂谈数'), _t('留空统计分类缩略名为 chatter 的文章，可按需手填')));
    $form->addInput(new Text('sgStatPhotos', null, '', _t('统计·照片数'), _t('留空自动统计附件数量')));
    $form->addInput(new Text('sgUptimeSince', null, '', _t('建站日期'), _t('格式 2024-01-01，用于页脚「已稳定运行」；留空取最早文章时间')));

    $petOptions = ['none' => _t('不显示'), 'cat' => _t('暹罗猫立绘'), 'fish' => _t('大肥鱼立绘')];
    $form->addInput(new Select('sgPet', $petOptions, 'none', _t('博客宠物'), _t('仅静态立绘，不含 AI 对话')));
    $seasonOptions = ['none' => _t('关闭'), 'sakura' => _t('樱花'), 'snow' => _t('飘雪'), 'fireflies' => _t('萤火虫')];
    $form->addInput(new Select('sgSeason', $seasonOptions, 'none', _t('季节粒子'), _t('移动端与 reduced-motion 下自动关闭')));
    $effectOptions = ['on' => _t('开启'), 'off' => _t('关闭')];
    $form->addInput(new Radio('sgEffects', $effectOptions, 'on', _t('全局粒子特效'), _t('低配设备或移动端自动降级')));
    $form->addInput(new Radio('sgClickEffect', $effectOptions, 'on', _t('点击涟漪'), _t('点击页面时的粒子反馈')));
    $form->addInput(new Radio('sgFloatingPlayer', $effectOptions, 'on', _t('浮动播放器'), _t('右下角迷你播放器，切页后继续播放')));
    $form->addInput(new Radio('sgSplash', $effectOptions, 'on', _t('开屏动画'), _t('进入站点时约 0.7 秒的品牌遮罩')));
}

/**
 * 主题初始化钩子。
 * 注意 1.3 的 typed property：不要在首页上下文读取归档缩略名等未初始化属性。
 */
function themeInit($archive): void
{
    sg_context($archive);
}

/** 归档列表统一排除 revision（1.3 起已发布内容的修订以 revision 类型保存） */
function sg_recent_posts($archive, int $limit = 5)
{
    return $archive->widget('Widget_Contents_Post_Recent', 'pageSize=' . $limit);
}

/**
 * 取某个分类下的文章（用于首页杂谈轮播、说说/杂谈列表页）。
 * 先校验分类是否存在，避免 Widget_Archive 在 execute 阶段抛异常；失败返回 null。
 */
function sg_category_posts($archive, string $slug, int $limit = 4)
{
    try {
        $exists = false;
        $archive->widget('Widget_Metas_Category_List')->to($sgAllCats);
        while ($sgAllCats->next()) {
            if ((string) $sgAllCats->slug === $slug) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            return null;
        }
        return $archive->widget('Widget_Archive@sg_cat_' . $slug, 'pageSize=' . $limit . '&type=category', 'category=' . $slug);
    } catch (\Throwable $e) {
        return null;
    }
}
