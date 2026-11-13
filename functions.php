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
        'sgNotes',
        'sgPoster',
        'sgBriefing',
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
 * 后台「上传图片」小部件，挂在某个文本域的 description 上。
 *
 * 为什么不用 Typecho 的 Form Element 自己写一个上传控件：
 * 主题设置表单（admin/options-theme.php）没有 enctype="multipart/form-data"，
 * 提交时文件根本到不了 PHP；要支持文件就得接管整个保存流程。
 * 这里走后台自带的 `/action/upload`（Widget\Upload，和写文章页同一个接口）：
 *   选中文件 → fetch 上传 → 接口返回 [url, {...}] → 把 url 写回文本框，
 * 用户再点「保存设置」走原生保存。这样既不碰保存链路，也不改后台表单。
 *
 * 上传地址必须在这里生成：Typecho 的 `_` 令牌是按「生成令牌时的页面地址」
 * 算的，而 protect() 用 Referer 复核 —— 同页面 fetch 天然对得上。
 *
 * @param string $field 目标文本域的 name
 */
function sg_admin_upload_tools(string $field): string
{
    $endpoint = '';
    try {
        $endpoint = (string) \Widget\Security::alloc()->getRootUrl('/action/upload');
    } catch (\Throwable $e) {
        return '';
    }
    if ($endpoint === '') {
        return '';
    }

    $endpointJson = json_encode($endpoint, JSON_UNESCAPED_SLASHES);
    $fieldJson = json_encode($field, JSON_UNESCAPED_SLASHES);

    return <<<HTML
<script>
(function () {
  var endpoint = {$endpointJson};
  var field = {$fieldJson};
  var OK = '#646970';
  var BAD = '#d63638';

  function boot() {
    var input = document.querySelector('input[name="' + field + '"]');
    if (!input || input.getAttribute('data-sg-upload-ready')) { return; }
    input.setAttribute('data-sg-upload-ready', '1');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:.5rem 0 0';

    var pick = document.createElement('input');
    pick.type = 'file';
    pick.accept = 'image/png,image/jpeg,image/webp,image/gif';
    pick.style.display = 'none';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-s';
    btn.textContent = '选择图片并上传';

    var tip = document.createElement('span');
    tip.style.cssText = 'font-size:12px;color:' + OK;

    var prev = document.createElement('img');
    prev.alt = '';
    prev.style.cssText = 'display:none;width:112px;height:63px;object-fit:cover;border-radius:6px;border:1px solid #dcdcde';

    function paint(src) {
      if (!src) {
        prev.style.display = 'none';
        prev.removeAttribute('src');
        return;
      }
      prev.src = src;
      prev.style.display = 'block';
      prev.onerror = function () { prev.style.display = 'none'; };
    }

    paint(input.value.trim());

    btn.addEventListener('click', function () { pick.click(); });
    input.addEventListener('input', function () { paint(input.value.trim()); });

    pick.addEventListener('change', function () {
      var file = pick.files && pick.files[0];
      if (!file) { return; }
      var data = new FormData();
      data.append('file', file);
      tip.style.color = OK;
      tip.textContent = '上传中…';
      btn.disabled = true;
      fetch(endpoint, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          var url = Array.isArray(json) ? json[0] : '';
          if (typeof url !== 'string' || url === '') { throw new Error('服务端没有返回图片地址'); }
          input.value = url;
          paint(url);
          tip.style.color = OK;
          tip.textContent = '已上传，记得点最下方「保存设置」生效';
        })
        .catch(function (err) {
          tip.style.color = BAD;
          tip.textContent = '上传失败：' + (err && err.message ? err.message : '请改用地址手填')
            + '（可上传的类型与大小上限见「设置 → 基本设置 → 附件」）';
        })
        .then(function () { btn.disabled = false; pick.value = ''; });
    });

    wrap.appendChild(btn);
    wrap.appendChild(tip);
    wrap.appendChild(prev);
    wrap.appendChild(pick);
    input.insertAdjacentElement('afterend', wrap);
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

    /* ---------------- 背景图相关（第六轮统一补齐注释） ----------------
       这几项都直接决定「页面哪个位置显示哪张图」，说明里必须写清对应位置，
       否则很容易把「全站壁纸」和「文章封面」混成一件事。 */
    $form->addInput(new Textarea('sgBgImages', null, '', _t('背景壁纸（全站最底层）'), _t(
        '<b>显示位置</b>：全站每个页面的最底层背景 —— 首页、文章页、独立页、归档 / 分类 / 标签页都会铺满这一层。<br>'
        . '<b>填写方式</b>：每行一个地址。支持 <code>https://…</code> 外链、<code>/</code> 开头的站内绝对路径，'
        . '以及 <code>usr/uploads/2026/11/bg.jpg</code> 这种站内相对路径（后台「附件」里复制出来的地址就是这种）。<br>'
        . '<b>多张</b>：填两张以上会按下面的「背景轮播间隔」轮换。<br>'
        . '<b>留空</b>：使用主题内置的 bg-1 / bg-2 渐变壁纸。<br>'
        . '<b>单页覆盖</b>：在文章或独立页的自定义字段里加 <code>sgBg</code> 填一张图，只换这一页的背景。'
    )));
    $veilOptions = ['soft' => _t('淡（透出壁纸）'), 'mid' => _t('中（默认）'), 'strong' => _t('浓（文字最清楚）')];
    $form->addInput(new Select('sgBgVeil', $veilOptions, 'mid', _t('背景遮罩（壁纸与内容之间）'), _t(
        '<b>显示位置</b>：压在壁纸之上、全部内容之下的那层蒙版，全站生效。<br>'
        . '<b>怎么选</b>：照片类壁纸建议「中」或「浓」；深色照片选「浓」能明显提升正文对比度；纯渐变壁纸选「淡」即可。'
    )));
    $blurOptions = ['none' => _t('不模糊'), 'soft' => _t('轻度模糊'), 'strong' => _t('重度模糊')];
    $form->addInput(new Select('sgBgBlur', $blurOptions, 'none', _t('背景模糊（只糊壁纸）'), _t(
        '<b>显示位置</b>：同样作用于全站壁纸层，卡片与文字不受影响。<br>'
        . '<b>怎么选</b>：照片类壁纸建议「轻度模糊」，避免细节抢正文；渐变壁纸不需要模糊。移动端会自动降低模糊强度。'
    )));
    $form->addInput(new Text('sgBgInterval', null, '9', _t('背景轮播间隔（秒）'), _t(
        '<b>作用范围</b>：全站壁纸的切换节奏，只有上面填了两张以上才生效。<br>'
        . '填 <code>0</code> 关闭轮播；默认 9 秒。'
    )));
    $form->addInput(new Text('sgThemeColors', null, '#a18cd1,#fbc2eb,#a1c4fd,#c2e9fb', _t('呼吸渐变色（壁纸之上的光晕）'), _t(
        '<b>显示位置</b>：壁纸最上层缓慢流动的渐变光晕，全站生效（壁纸被遮罩压暗时尤其明显）。<br>'
        . '英文逗号分隔的十六进制色值，至少两个。'
    )));
    $form->addInput(new Text('sgDefaultCover', null, '', _t('默认文章封面'), _t(
        '<b>显示位置</b>：文章封面兜底图 —— 首页封面轮播、文章列表卡、文章详情页顶部大图、知识档案卡片缩略图；'
        . '首页宽幅卡切到「最新文章」模式时也用它。<br>'
        . '<b>优先级</b>：文章自定义字段 <code>cover</code> &gt; 这里的地址 &gt; 内置 dist/img/cover-default.svg。<br>'
        . '<b>建议</b>：填 16:9 左右的横图。'
    )));
    $form->addInput(new Text('sgBriefingBg', null, '', _t('每日选刊背景图（AI 日报卡片）'), _t(
        '<b>显示位置</b>：首页最下方「每日选刊 / AI 日报」卡片顶部的横幅背景，展开目录后仍是这张。<br>'
        . '<b>与每期封面的关系</b>：填了就统一用这张；留空则用「每日选刊 JSON」里每期自带的 <code>cover</code>。<br>'
        . '<b>怎么用</b>：点下面的按钮直接上传（图片存进 usr/uploads，地址会自动填进上面的输入框），'
        . '也可以手填 https 外链或 usr/uploads 相对路径。<br>'
        . '<b>建议</b>：1200×700 左右的横图，深色构图更耐看（横幅上压着白色标题）。'
    ) . sg_admin_upload_tools('sgBriefingBg')));
    $form->addInput(new Textarea('sgDanmaku', null, '', _t('背景弹幕'), _t('每行一条，留空使用 dist/data/danmaku.json')));

    $form->addInput(new Textarea('sgSocial', null, '', _t('社交链接 JSON'), _t('格式：[{"name":"GitHub","url":"...","icon":"github"}]') . sg_admin_json_tools()));
    $form->addInput(new Textarea('sgFriends', null, '', _t('友链 JSON'), _t('留空使用 dist/data/friends.json')));
    $form->addInput(new Textarea('sgProjects', null, '', _t('项目 JSON'), _t('留空使用 dist/data/projects.json')));
    $form->addInput(new Textarea('sgAlbums', null, '', _t('相册 JSON'), _t('留空使用 dist/data/albums.json；<code>coverWide</code> 可选，给首页 4:1 宽幅卡用横构图')));

    $posterModes = ['album' => _t('光影画廊（最新相册）'), 'post' => _t('最新文章'), 'custom' => _t('自定义')];
    $form->addInput(new Select('sgPosterMode', $posterModes, 'album', _t('首页宽幅卡（右侧大海报）'), _t('决定首页右侧那张宽幅海报取什么内容；默认放「光影画廊」最新相册。它的背景图就是相册 cover / 文章 cover / 下一项 JSON 里的 cover。')));
    $form->addInput(new Textarea('sgPoster', null, '', _t('宽幅卡 JSON'), _t('<b>显示位置</b>：仅上面选「自定义」时生效，替换首页右上那张海报。<br>格式：<code>{"title":"…","desc":"…","cover":"…","url":"…","kicker":"PHOTO WALL"}</code><br>其中 <code>cover</code> 就是这张卡的背景图，支持 https 外链与 <code>usr/uploads/…</code> 相对路径。')));

    $form->addInput(new Textarea('sgPlaylist', null, '', _t('歌单'), _t(
        '主题只有这一处歌单配置，三种写法任选其一：<br>'
        . '<b>①留空</b>：用主题自带的 CC-BY 纯音乐（<code>dist/data/playlist.json</code>）。<br>'
        . '<b>②填 JSON 数组</b>：自己挑的曲目，字段 <code>title / artist / cover / url / lrc</code>；'
        . '自带音源放 <code>public/audio/</code> 时 url 写 <code>dist/audio/xxx.mp3</code>。<br>'
        . '<b>③填 Meting 接口地址</b>：整条 URL 原样透传，例如 '
        . '<code>https://api.injahow.cn/meting/?server=netease&amp;type=playlist&amp;id=2619366284</code>，'
        . '主题把返回的 JSON 当歌单用，音频地址与歌词都来自接口。<br>'
        . '<b>生效时机</b>：前台缓存 10 分钟；以管理员身份登录时每次刷新都会重新取，改完即可见。'
        . '音乐页底部会显示「当前数据源 + 条数」，可据此确认命中哪一条。'
    )));
    $form->addInput(new Text('sgPlaylistLimit', null, '50', _t('歌单上限（条）'), _t('只对 Meting 远程歌单生效：一个热门歌单常有几百首，全量塞进页面会让单页体积超过 500 KB。填 0 表示不截断；手填的 JSON 歌单不受此限')));
    $form->addInput(new Textarea('sgNotes', null, '', _t('碎语 JSON'), _t('「碎语」页与首页碎语轮播的数据源，留空使用 dist/data/notes.json。<br>格式：<code>[{"date":"2026.11.13","tag":"日常","title":"…","text":"…","cover":"…"}]</code>')));
    $form->addInput(new Textarea('sgBriefing', null, '', _t('每日选刊 JSON'), _t('格式：{"channels":[{"id","name"}],"issues":[{"date","title","desc","cover","items":[...]}]}；也兼容旧的数组格式，留空使用 dist/data/briefing.json')));
    $form->addInput(new Text('sgIcp', null, '', _t('备案号'), _t('显示位置：页脚第一行「系统已稳定运行」的右侧。填阿里云备案下来的完整号（例：湘ICP备2023030699号）；留空则整段不输出，不留空位')));

    $form->addInput(new Text('sgStatPosts', null, '', _t('统计·文章数'), _t('留空自动统计已发布文章')));
    $form->addInput(new Text('sgStatNotes', null, '', _t('统计·碎语数'), _t('留空自动统计碎语条数（也就是 notes.json 的条目数），可按需手填')));
    $form->addInput(new Text('sgStatPhotos', null, '', _t('统计·照片数'), _t('留空自动统计附件数量')));
    $form->addInput(new Text('sgUptimeSince', null, '', _t('建站日期'), _t('显示位置：页脚第一行「系统已稳定运行：N 天 N 小时」。格式 2024-01-01（也可写 2024/01/01）。不满一年显示天数，满一年显示「1 年 12 天 12 小时」；留空则自动取最早一篇已发布文章的时间')));

    $seasonOptions = ['none' => _t('关闭'), 'sakura' => _t('樱花'), 'snow' => _t('飘雪'), 'fireflies' => _t('萤火虫')];
    $form->addInput(new Select('sgSeason', $seasonOptions, 'none', _t('季节粒子'), _t('移动端与 reduced-motion 下自动关闭')));
    $effectOptions = ['on' => _t('开启'), 'off' => _t('关闭')];
    $form->addInput(new Radio('sgEffects', $effectOptions, 'on', _t('全局粒子特效'), _t('低配设备或移动端自动降级')));
    $form->addInput(new Radio('sgClickEffect', $effectOptions, 'on', _t('点击涟漪'), _t('点击页面时的粒子反馈')));
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
 * 取某个分类下的文章（分类页、按分类聚合的独立页都能用）。
 * 先校验分类是否存在，避免 Widget_Archive 在 execute 阶段抛异常；失败返回 null。
 *
 * 注：碎语已经改成读 notes.json，不再按分类聚合，所以这里目前没有调用方，
 * 保留是因为新加「按分类聚合一页文章」的独立页时还要用它。
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
