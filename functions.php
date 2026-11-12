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
 * 后台「格式化 JSON」按钮的内联脚本。
 *
 * 为什么挂在 description 上而不是 addItem(Layout)：Typecho 1.3 的
 * Element::render() 会把 description 原样输出（允许 HTML），这是唯一
 * 不依赖 Layout 构造签名、也不依赖后台能否加载外部资源的路子。
 * 脚本自己去页面里找 7 个 JSON 文本域，逐个插入按钮，找不到就什么都不做。
 *
 * 解析失败时不动原值，只把输入框描红并在旁边说明 —— 避免「点了格式化，
 * 数据被清空」这种最糟的结果。
 */
function sg_admin_json_tools(): string
{
    $names = json_encode([
        'sgSocial',
        'sgFriends',
        'sgProjects',
        'sgAlbums',
        'sgPlaylist',
        'sgPoster',
        'sgBriefing',
        'sgFooterBadges',
    ], JSON_UNESCAPED_SLASHES);

    return <<<HTML
<script>
(function () {
  var fields = {$names};
  var OK = '#646970';
  var BAD = '#d63638';

  function tipOf(el) {
    return document.getElementById('sg-json-tip-' + el.name);
  }

  function format(el) {
    var raw = el.value.trim();
    var tip = tipOf(el);
    if (raw === '') {
      if (tip) { tip.textContent = '当前为空，留空会回落到 dist/data 里的内置示例。'; tip.style.color = OK; }
      el.focus();
      return;
    }
    var data;
    try {
      data = JSON.parse(raw);
    } catch (err) {
      el.style.borderColor = BAD;
      if (tip) { tip.textContent = 'JSON 有语法错误，已保留原内容：' + err.message; tip.style.color = BAD; }
      return;
    }
    el.value = JSON.stringify(data, null, 2);
    el.style.borderColor = '';
    if (tip) { tip.textContent = '已格式化，点「保存设置」后生效。'; tip.style.color = OK; }
  }

  function boot() {
    fields.forEach(function (name) {
      var el = document.querySelector('textarea[name="' + name + '"]');
      if (!el || el.getAttribute('data-sg-json-ready')) { return; }
      el.setAttribute('data-sg-json-ready', '1');

      var wrap = document.createElement('div');
      wrap.style.cssText = 'display:flex;align-items:center;gap:.5rem;margin:.35rem 0 0';

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'btn btn-s';
      btn.textContent = '格式化 JSON';
      btn.addEventListener('click', function () { format(el); });

      var tip = document.createElement('span');
      tip.id = 'sg-json-tip-' + name;
      tip.style.cssText = 'font-size:12px;color:' + OK;

      wrap.appendChild(btn);
      wrap.appendChild(tip);
      el.insertAdjacentElement('afterend', wrap);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
</script>
HTML;
}

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

    $form->addInput(new Textarea('sgSocial', null, '', _t('社交链接 JSON'), _t('格式：[{"name":"GitHub","url":"...","icon":"github"}]') . sg_admin_json_tools()));
    $form->addInput(new Textarea('sgFriends', null, '', _t('友链 JSON'), _t('留空使用 dist/data/friends.json')));
    $form->addInput(new Textarea('sgProjects', null, '', _t('项目 JSON'), _t('留空使用 dist/data/projects.json')));
    $form->addInput(new Textarea('sgAlbums', null, '', _t('相册 JSON'), _t('留空使用 dist/data/albums.json')));

    $posterModes = ['album' => _t('光影画廊（最新相册）'), 'post' => _t('最新文章'), 'custom' => _t('自定义')];
    $form->addInput(new Select('sgPosterMode', $posterModes, 'album', _t('首页宽幅卡'), _t('首页右侧那张大海报显示什么；默认放「光影画廊」相册卡')));
    $form->addInput(new Textarea('sgPoster', null, '', _t('宽幅卡 JSON'), _t('仅「自定义」时生效：{"title":"...","desc":"...","cover":"...","url":"...","kicker":"PHOTO WALL"}')));

    $form->addInput(new Textarea('sgPlaylist', null, '', _t('歌单 JSON'), _t('留空使用 dist/data/playlist.json；字段：title/artist/cover/url/lrc')));
    $form->addInput(new Textarea('sgPlaylistIds', null, '', _t('网易云歌曲 ID'), _t('逗号或换行分隔。歌单 JSON 留空时才启用：服务端拉歌曲名/封面/歌词，播放地址是 outer/url 外链（有 403 风险）')));
    $form->addInput(new Textarea('sgBriefing', null, '', _t('每日选刊 JSON'), _t('格式：{"channels":[{"id","name"}],"issues":[{"date","title","desc","cover","items":[...]}]}；也兼容旧的数组格式，留空使用 dist/data/briefing.json')));
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
    $form->addInput(new Radio('sgSplash', $effectOptions, 'on', _t('开屏动画'), _t('每个会话首次进入时约 0.6 秒的品牌遮罩，站内切页不再重放')));
    $form->addInput(new Text('sgPageSize', null, '200', _t('每页条数上限'), _t('对齐源站「无分页」：模板不输出分页条，列表一次取完。填 0 则沿用「设置 → 阅读」的每页文章数目')));
}

/**
 * 主题初始化钩子。
 * 注意 1.3 的 typed property：不要在首页上下文读取归档缩略名等未初始化属性。
 *
 * 这里同时把列表的每页条数提到「一次取完」：源站 XHBlogs 没有分页组件
 * （app/page.tsx 只 slice(0,5)，app/timeline 全量渲染），因此模板层不再输出
 * pageNav，服务端分页退化为不可见实现细节。
 * 该钩子在 Archive::execute() 组装查询之前调用，见 var/Widget/Archive.php:675，
 * $archive->parameter 由 var/Typecho/Widget.php:556 ___parameter() 暴露，可写。
 */
function themeInit($archive): void
{
    sg_context($archive);

    if (!($archive instanceof \Widget\Archive)) {
        return;
    }

    $size = (int) sg_opt('sgPageSize', '200');
    if ($size > 0 && ($archive->is('index') || $archive->is('archive'))) {
        $archive->parameter->pageSize = $size;
    }
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
