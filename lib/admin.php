<?php
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Widget\Helper\Form\Element\Select;
use Typecho\Widget\Helper\Form\Element\Radio;

/**
 * 后台「格式化 JSON」按钮的内联脚本。
 *
 * 为什么挂在 description 上而不是 addItem(Layout)：Typecho 1.3 的
 * Element::render() 会把 description 原样输出（允许 HTML），这是唯一
 * 不依赖 Layout 构造签名、也不依赖后台能否加载外部资源的路子。
 * 脚本只为 JSON 文本域插入按钮；Meting 歌单接口不参与格式化。
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
        'sgPoster',
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
 * 2026-11-13 第二十轮修 bug：以前用 `getRootUrl('/action/upload')` 生成
 * `https://站点/action/upload`，但后台实际入口是 `…/index.php/action/upload`
 * （见 admin/file-upload.php:16 用的就是 `$security->index()`）。
 * 宝塔的 `/{slug}.html` 伪静态不会把裸 `/action/upload` 路由到 index.php，
 * 于是返回 404 的 HTML 页面，前端 `res.json()` 直接抛
 * `Unexpected token '<'` —— 这就是「每日选刊背景图上传失败」的真因。
 * 改用 `getIndex()` 后带上 index.php，两种站点配置都能命中。
 *
 * @param string $field 目标文本域的 name
 */
function sg_admin_upload_tools(string $field): string
{
    $endpoint = '';
    try {
        $endpoint = (string) \Widget\Security::alloc()->getIndex('/action/upload');
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
    var input = document.querySelector('input[name="' + field + '"],textarea[name="' + field + '"]');
    if (!input || input.getAttribute('data-sg-upload-ready')) { return; }
    input.setAttribute('data-sg-upload-ready', '1');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:.5rem 0 0';

    var pick = document.createElement('input');
    pick.type = 'file';
    pick.multiple = input.tagName === 'TEXTAREA';
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

    function firstImage() { return input.value.trim().split(String.fromCharCode(10))[0].trim(); }
    paint(firstImage());

    btn.addEventListener('click', function () { pick.click(); });
    input.addEventListener('input', function () { paint(firstImage()); });

    pick.addEventListener('change', async function () {
      var selected = Array.from(pick.files || []);
      for (var selectedFile of selected) {
      var file = selectedFile;
      if (!file) { return; }
      var data = new FormData();
      data.append('file', file);
      tip.style.color = OK;
      tip.textContent = '上传中…';
      btn.disabled = true;
      await fetch(endpoint, {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function (res) {
          /* 不能直接 res.json()：路由没命中时服务器返回的是 HTML 404 页，
             json() 只会抛一句「Unexpected token '<'」，看不出真正原因。
             先读文本再尝试解析，失败时把状态码和正文开头一起带出来。 */
          return res.text().then(function (body) {
            var trimmed = body.replace(/^\s+/, '');
            if (trimmed.charAt(0) !== '[' && trimmed.charAt(0) !== '{') {
              throw new Error('接口返回的不是 JSON（HTTP ' + res.status + '）：'
                + trimmed.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 80));
            }
            try {
              return JSON.parse(trimmed);
            } catch (e) {
              throw new Error('接口返回的 JSON 解析失败（HTTP ' + res.status + '）');
            }
          });
        })
        .then(function (json) {
          var url = Array.isArray(json) ? json[0] : '';
          if (typeof url !== 'string' || url === '') { throw new Error('服务端没有返回图片地址'); }
          input.value = input.tagName === 'TEXTAREA' ? [input.value.trim(), url].filter(Boolean).join(String.fromCharCode(10)) : url;
          paint(firstImage());
          tip.style.color = OK;
          tip.textContent = '已上传，记得点最下方「保存设置」生效';
        })
        .catch(function (err) {
          tip.style.color = BAD;
          tip.textContent = '上传失败：' + (err && err.message ? err.message : '请改用地址手填')
            + '（可上传的类型与大小上限见「设置 → 基本设置 → 附件」）';
        })
      }
      btn.disabled = false;
      pick.value = '';
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
 * 后台「从 GitHub 拉取仓库」小部件，挂在「仓库清单」文本域的 description 上。
 *
 * 走的是浏览器直连 api.github.com：该接口带 `Access-Control-Allow-Origin: *`，
 * 所以在后台页面 fetch 一下就够，**不需要**在主题里加一个会暴露 Token 的代理端点。
 *
 * 勾选结果只写回文本域（一行一个仓库名），真正的保存仍然点最下面「保存设置」，
 * 和「上传图片」按钮同一套「不接管保存链路」的做法。
 *
 * @param string $field 目标文本域 name（这里是 sgGithubRepos）
 * @param string $user  账号输入框为空时的兜底账号
 */
function sg_admin_github_picker(string $field, string $user = ''): string
{
    $fieldJson = json_encode($field, JSON_UNESCAPED_SLASHES);
    $userJson = json_encode($user, JSON_UNESCAPED_SLASHES);

    return <<<HTML
<script>
(function () {
  var field = {$fieldJson};
  var defaultUser = {$userJson};
  var OK = '#646970';
  var BAD = '#d63638';

  function boot() {
    var input = document.querySelector('textarea[name="' + field + '"]');
    if (!input || input.getAttribute('data-sg-picker-ready')) { return; }
    input.setAttribute('data-sg-picker-ready', '1');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:.5rem 0 0';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-s';
    btn.textContent = '从 GitHub 拉取仓库';

    var tip = document.createElement('span');
    tip.style.cssText = 'font-size:12px;color:' + OK;

    var panel = document.createElement('div');
    panel.style.cssText = 'display:none;width:100%;max-width:640px;margin-top:.5rem;padding:.5rem;'
      + 'border:1px solid #dcdcde;border-radius:6px;background:#fff;max-height:320px;overflow:auto';

    var foot = document.createElement('div');
    foot.style.cssText = 'display:none;gap:.5rem;margin-top:.4rem';

    var applyBtn = document.createElement('button');
    applyBtn.type = 'button';
    applyBtn.className = 'btn btn-s btn-primary';
    applyBtn.textContent = '写入到「仓库清单」';

    function account() {
      var box = document.querySelector('input[name="sgGithubUser"]');
      var value = box && box.value ? box.value.trim() : '';
      return value || defaultUser;
    }

    function currentSet() {
      var set = {};
      String(input.value || '').split(/\\r?\\n/).forEach(function (line) {
        var name = line.split('|')[0].trim().split('/').pop().trim().toLowerCase();
        if (name) { set[name] = true; }
      });
      return set;
    }

    function render(repos) {
      var chosen = currentSet();
      panel.textContent = '';
      repos.forEach(function (repo) {
        var row = document.createElement('label');
        row.style.cssText = 'display:flex;align-items:center;gap:.5rem;padding:.25rem .2rem;font-size:13px;cursor:pointer';

        var box = document.createElement('input');
        box.type = 'checkbox';
        box.value = repo.name;
        box.checked = !!chosen[String(repo.name).toLowerCase()];

        var main = document.createElement('span');
        main.textContent = repo.name;

        var meta = document.createElement('span');
        meta.style.cssText = 'color:#646970;font-size:12px;margin-left:auto';
        var bits = [];
        if (repo.language) { bits.push(repo.language); }
        if (repo.stargazers_count) { bits.push('★' + repo.stargazers_count); }
        if (repo.fork) { bits.push('fork'); }
        if (repo.archived) { bits.push('已归档'); }
        meta.textContent = bits.join(' · ');

        row.appendChild(box);
        row.appendChild(main);
        row.appendChild(meta);
        panel.appendChild(row);
      });
    }

    btn.addEventListener('click', function () {
      var name = account();
      if (!name) {
        tip.style.color = BAD;
        tip.textContent = '先在上面填 GitHub 账号';
        return;
      }
      tip.style.color = OK;
      tip.textContent = '拉取中…';
      btn.disabled = true;
      fetch('https://api.github.com/users/' + encodeURIComponent(name) + '/repos?per_page=100&sort=pushed', {
        headers: { 'Accept': 'application/vnd.github+json' }
      })
        .then(function (res) {
          if (res.status === 403) { throw new Error('接口限流（未登录每小时 60 次）'); }
          if (res.status === 404) { throw new Error('找不到这个账号'); }
          if (!res.ok) { throw new Error('HTTP ' + res.status); }
          return res.json();
        })
        .then(function (list) {
          if (!Array.isArray(list) || !list.length) { throw new Error('这个账号没有公开仓库'); }
          render(list);
          panel.style.display = 'block';
          foot.style.display = 'flex';
          tip.textContent = '共 ' + list.length + ' 个仓库，勾选后点下面按钮写回';
        })
        .catch(function (err) {
          tip.style.color = BAD;
          tip.textContent = '拉取失败：' + (err && err.message ? err.message : '网络不通') + '（清单原值未改动）';
        })
        .then(function () { btn.disabled = false; });
    });

    applyBtn.addEventListener('click', function () {
      var names = [];
      panel.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
        if (box.checked) { names.push(box.value); }
      });
      if (!names.length) {
        tip.style.color = BAD;
        tip.textContent = '一个都没勾，清单保持原样；想用自动模式请直接清空清单';
        return;
      }
      input.value = names.join('\\n');
      input.dispatchEvent(new Event('input', { bubbles: true }));
      tip.style.color = OK;
      tip.textContent = '已写入 ' + names.length + ' 个仓库名，记得点最下方「保存设置」';
    });

    foot.appendChild(applyBtn);
    wrap.appendChild(btn);
    wrap.appendChild(tip);
    wrap.appendChild(panel);
    wrap.appendChild(foot);
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
    sg_gallery_plugin(); // Upgrade native attachment hooks without resetting cloud settings.
    /* ---------------- 身份与导航 ---------------- */
    $form->addInput(new Text('sgAuthorName', null, '博主', _t('博主昵称'), _t('显示在首页个人卡片')));
    $form->addInput(new Text('sgAuthorStatus', null, '', _t('作者状态气泡'), _t('显示在作者卡头像右下角，可带 emoji；留空不显示')));
    $form->addInput(new Text('sgAvatarUrl', null, '', _t('头像地址'), _t('留空使用主题内置 SVG 头像')));
    $form->addInput(new Text('sgNavTitle', null, '', _t('导航前缀'), _t('留空则使用站点名称')));
    $form->addInput(new Text('sgNavSuffix', null, 'の', _t('导航分隔符'), _t('例：の')));
    $form->addInput(new Text('sgNavAfter', null, '空璃', _t('导航后缀'), _t('例：空璃')));

    /* 背景：日间组、夜间组与可暂停轮播（旧通用壁纸 sgBg 已移除）。 */
    $veilOptions = ['soft' => _t('淡（透出壁纸）'), 'mid' => _t('中（默认）'), 'strong' => _t('浓（文字最清楚）')];
    foreach (['sgBgLight' => '日间', 'sgBgDark' => '夜间'] as $sgBgKey => $sgBgMode) {
        $form->addInput(new Textarea($sgBgKey, null, '', _t('背景壁纸 · ' . $sgBgMode . '（一行一张，可轮播）'), _t('分别设置' . $sgBgMode . '模式的全站壁纸；最多 12 张，一行一个图片地址。留空使用主题内置渐变壁纸。') . sg_admin_upload_tools($sgBgKey)));
    }
    /* 首屏独立图设置已移除（2026-11-17）：首页 100vh 首屏与正文壁纸共用同一张
       （壁纸组第一张），视觉连续、少一次请求；旧 sgFrontImg / sgFrontImgDark
       配置值留在库里不再读取。 */
    $form->addInput(new Text('sgBgInterval', null, '9', _t('背景壁纸 · 轮播间隔（秒）'), _t('默认 9 秒，3–120 秒；0 停止轮播。单张图保持静止，标签页隐藏或系统要求减少动效时暂停。')));
    $form->addInput(new Select('sgBgVeil', $veilOptions, 'mid', _t('背景遮罩（壁纸与内容之间）'), _t(
        '<b>显示位置</b>：压在壁纸之上、全部内容之下的那层蒙版，全站生效。<br>'
        . '<b>怎么选</b>：照片类壁纸建议「中」或「浓」；深色照片选「浓」能明显提升正文对比度；纯渐变壁纸选「淡」即可。'
    )));
    $blurOptions = ['none' => _t('不模糊'), 'soft' => _t('轻度模糊'), 'strong' => _t('重度模糊')];
    $form->addInput(new Select('sgBgBlur', $blurOptions, 'none', _t('背景模糊（只糊壁纸）'), _t(
        '<b>显示位置</b>：同样作用于全站壁纸层，卡片与文字不受影响。<br>'
        . '<b>怎么选</b>：照片类壁纸建议「轻度模糊」，避免细节抢正文；渐变壁纸不需要模糊。移动端会自动降低模糊强度。'
    )));
    $form->addInput(new Text('sgThemeColors', null, '#5f9299,#8ec5c9,#b8d8dc,#dcebed', _t('呼吸渐变色（壁纸之上的光晕）'), _t(
        '<b>显示位置</b>：壁纸最上层缓慢流动的渐变光晕，全站生效（壁纸被遮罩压暗时尤其明显）。<br>'
        . '英文逗号分隔的十六进制色值，至少两个。'
    )));
    $seasonOptions = ['none' => _t('关闭'), 'sakura' => _t('樱花'), 'snow' => _t('飘雪'), 'fireflies' => _t('萤火虫')];
    $form->addInput(new Select('sgSeason', $seasonOptions, 'none', _t('季节粒子'), _t('移动端与 reduced-motion 下自动关闭')));
    $effectOptions = ['on' => _t('开启'), 'off' => _t('关闭')];
    $form->addInput(new Radio('sgEffects', $effectOptions, 'on', _t('全局粒子特效'), _t('低配设备或移动端自动降级')));
    $form->addInput(new Radio('sgClickEffect', $effectOptions, 'on', _t('点击涟漪'), _t('点击页面时的粒子反馈')));
    $form->addInput(new Radio('sgSplash', $effectOptions, 'on', _t('开屏动画'), _t('每个会话首次进入时约 0.6 秒的品牌遮罩，站内切页不再重放')));

    /* ---------------- 文章页与侧栏 ---------------- */
    $form->addInput(new Textarea('sgNotice', null, '', _t('侧栏公告'), _t('显示在文章页与独立页右侧栏的公告卡；留空则不输出该卡。支持换行，按纯文本处理。')));
    $form->addInput(new Text('sgSidebar', null, 'notice', _t('侧栏卡片与顺序'), _t(
        '文章页侧栏固定为「作者卡 / 公告 / 目录」三张。此项仅 <code>notice</code> 生效（控制公告卡是否显示，'
        . '内容取上方「侧栏公告」；留空则不显示公告）；<code>recent</code> / <code>categories</code> / '
        . '<code>tags</code> / <code>webinfo</code> 为历史兼容值，已不再渲染，无需填写。'
    )));

    /* ---------------- 首页三张卡 ---------------- */
    $form->addInput(new Text('sgDefaultCover', null, '', _t('默认文章封面'), _t(
        '<b>显示位置</b>：文章封面兜底图 —— 首页封面轮播、文章列表卡、文章详情页顶部大图、知识档案卡片缩略图；'
        . '首页宽幅卡切到「最新文章」模式时也用它。<br>'
        . '<b>优先级</b>：文章自定义字段 <code>cover</code> &gt; 这里的地址 &gt; 内置 dist/img/cover-default.svg。<br>'
        . '<b>建议</b>：填 16:9 左右的横图。'
    )));
    $posterModes = ['album' => _t('光影画廊（最新相册）'), 'post' => _t('最新文章'), 'custom' => _t('自定义')];
    $form->addInput(new Select('sgPosterMode', $posterModes, 'album', _t('首页宽幅卡（右侧大海报）'), _t('决定首页右侧那张宽幅海报取什么内容；默认放「光影画廊」最新相册（照片按上传年月自动成册）。它的背景图就是相册 cover / 文章 cover / 下一项 JSON 里的 cover。')));
    $form->addInput(new Textarea('sgPoster', null, '', _t('宽幅卡 JSON'), _t('<b>显示位置</b>：仅上面选「自定义」时生效，替换首页右上那张海报。<br>格式：<code>{"title":"…","desc":"…","cover":"…","url":"…","kicker":"PHOTO WALL"}</code><br>其中 <code>cover</code> 就是这张卡的背景图，支持 https 外链与 <code>usr/uploads/…</code> 相对路径。')));
    $form->addInput(new Textarea('sgBriefingBg', null, '', _t('AI 日报 · 背景图（一行一个，可轮播）'), _t(
        '<b>显示位置</b>：首页最下方「AI 日报」卡片顶部的横幅背景，展开目录后仍是这张。<br>'
        . '<b>多张轮播</b>：一行贴一个图片地址。两张及以上自动循环播放 —— 每 7 秒一张，'
        . '0.9 秒交叉淡入 + 缓慢推镜；只填一行就是一张静态图，行为和以前一致。最多 12 张。<br>'
        . '<b>与主题切换</b>：日间/夜间模式共用这一组图；切换主题时轮播进度保持不变，'
        . '不会跳回第 1 张，本张的停留时间重新计 7 秒。<br>'
        . '<b>与模型封面的关系</b>：填了就以这里为准；留空则依次取每期选刊的封面，'
        . '点「‹ 2/3 ›」翻期时背景跟着换，并暂停自动轮播 12 秒。<br>'
        . '<b>怎么填</b>：直接贴图片地址 —— <code>https://…</code> 外链、<code>/</code> 开头的站内绝对路径，'
        . '或 <code>usr/uploads/2026/11/bg.jpg</code> 这种相对路径。<br>'
        . '<b>建议</b>：1200×700 左右的横图，深色构图更耐看（横幅上压着白色标题）。'
    )));

    /* ---------------- AI 日报 ---------------- */
    $aiProviders = [];
    foreach (sg_ai_providers() as $sgAiKey => $sgAiRow) {
        $aiProviders[$sgAiKey] = _t($sgAiRow['label']);
    }
    $form->addInput(new Select('sgAiProvider', $aiProviders, 'openai', _t('AI 日报 · 服务商'), _t(
        '选一个预设即可：<b>接口地址与模型名留空就用该服务商的默认值</b>，要换模型/中转站时在下面两栏覆盖。<br>'
        . 'OpenAI / DeepSeek / 智谱 / Kimi / 通义 / 豆包 / MiniMax 走同一套 <code>chat/completions</code> 协议；'
        . 'Claude 与 Gemini 由主题自动切换成各自的请求格式。<br>'
        . '<b>留空 Key 即关闭 AI 日报</b>；默认由模型每天自动生成 3–5 个主题。'
    )));
    $form->addInput(new Select('sgAiTopicMode', ['auto' => _t('AI 每日自动生成 3–5 个主题'), 'manual' => _t('使用手填主题')], 'auto', _t('AI 日报 · 主题来源'), _t('自动模式每天批量生成一次主题与选刊，首页读取缓存；失败保留最近成功结果。可用宝塔计划任务每日调用主题 ai-daily.php，无访问时也生成。')));
    $form->addInput(new Textarea('sgAiTopics', null, '', _t('AI 日报 · 主题 / 兴趣参考（一行一个）'), _t('自动模式可留空，也可填写兴趣供模型参考；手动模式每行一个主题，最多 6 个。首页箭头切换同一天的主题，“往期查看”按真实日期分组。')));
    $form->addInput(new Text('sgAiEndpoint', null, '', _t('AI 日报 · 接口地址'), _t(
        '留空即用所选服务商的默认地址。<br>'
        . '要覆盖时填完整路径，例如 <code>https://api.deepseek.com/v1/chat/completions</code>；'
        . 'Gemini 的地址里可以写 <code>{model}</code> 占位，主题会替换成模型名。<br>'
        . 'OpenAI 兼容接口会带 <code>messages</code> 与 <code>response_format={"type":"json_object"}</code>，'
        . '接口不支持后者时模型返回可能带代码块，主题会自动剥掉。'
    )));
    $form->addInput(new Text('sgAiKey', null, '', _t('AI 日报 · API Key'), _t('只存在 options 表、只在服务端请求头里用，不会出现在页面里。留空则关闭 AI 日报')));
    $form->addInput(new Text('sgAiModel', null, '', _t('AI 日报 · 模型'), _t('留空用服务商默认模型。要指定就填该服务商的模型名，例如 <code>gpt-4o-mini</code>、<code>deepseek-chat</code>、<code>glm-4-flash</code>、<code>claude-3-5-sonnet-latest</code>、<code>gemini-1.5-flash</code>')));
    $form->addInput(new Text('sgAiCount', null, '6', _t('AI 日报 · 每日条数'), _t('模型最多挑几条，1–12，默认 6')));
    $form->addInput(new Textarea('sgAiFeedsAi', null, '', _t('AI 日报 · 模型与应用源（RSS）'), _t(
        '这一栏喂给日报的<b>「模型与应用」</b>栏目，一行一个 RSS / Atom 地址。<br>'
        . '例：<code>https://www.ruanyifeng.com/blog/atom.xml</code>、<code>https://hnrss.org/frontpage</code>。<br>'
        . '标题与链接都回原文，模型只写摘要与理由，不会编造。'
    )));
    $form->addInput(new Textarea('sgAiFeedsPaper', null, '', _t('AI 日报 · 研究与探索源（RSS）'), _t(
        '这一栏喂给<b>「研究与探索」</b>栏目（论文、评测、研究进展），一行一个地址。<br>'
        . '例：<code>https://export.arxiv.org/rss/cs.AI</code>、<code>https://blog.google/technology/ai/rss/</code>。<br>'
        . '两组都留空就没有候选，首页这一块不会输出；只配一组时另一栏自动隐藏。<br>'
        . '<b>缓存</b>：每个主题一天一份；首页先显示最近成功的缓存，凌晨由 PHP-FPM 在页面响应后逐个更新主题，外部源失败不会阻塞页面。'
    )));
    $form->addInput(new Textarea('sgDanmaku', null, '', _t('背景弹幕'), _t('每行一条，留空使用 dist/data/danmaku.json')));

    $form->addInput(new Textarea('sgSocial', null, '', _t('社交链接 JSON'), _t('格式：[{"name":"GitHub","url":"...","icon":"github"}]') . sg_admin_json_tools()));
    $form->addInput(new Textarea('sgFriends', null, '', _t('友链 JSON'), _t('留空使用 dist/data/friends.json')));
    $form->addInput(new Textarea('sgProjects', null, '', _t('项目 JSON'), _t('留空使用 dist/data/projects.json。<br>填了 GitHub 账号后，这里留空即自动用仓库列表；<b>两项都有时以这里为准</b>（想完全交给 GitHub 就清空本栏）')));
    $form->addInput(new Text('sgGithubUser', null, 'oyj135', _t('GitHub 账号'), _t('「项目」页从这里列仓库。填用户名即可，例如 <code>oyj135</code>；留空则不启用 GitHub 数据源')));
    $form->addInput(new Textarea('sgGithubRepos', null, '', _t('仓库清单'), _t(
        '决定「项目」页显示哪些仓库，<b>一行一个</b>，留空表示自动（按最近推送取前 N 个，跳过 fork 与已归档）：<br>'
        . '<code>soraglass</code>　只写仓库名，归属账号取上面的 GitHub 账号<br>'
        . '<code>oyj135/AiForge</code>　写全名，可混着别的账号<br>'
        . '<code>soraglass | Typecho 主题</code>　竖线后面覆盖简介<br>'
        . '<code># 开头</code>的行是注释，不参与显示<br>'
        . '顺序就是展示顺序；已经删除或改名的仓库会被静默跳过。'
    ) . sg_admin_github_picker('sgGithubRepos', 'oyj135')));
    $form->addInput(new Text('sgGithubLimit', null, '12', _t('仓库上限（个）'), _t('只在「仓库清单」留空（自动模式）时生效，默认 12；想全量显示填 0。手填的清单不受此限')));
    $form->addInput(new Text('sgPlaylist', null, '', _t('Meting 歌单接口地址'), _t(
        '填写一条完整的 Meting 接口地址，例如 '
        . '<code>https://api.injahow.cn/meting/?server=netease&amp;type=playlist&amp;id=2619366284</code>，'
        . '歌曲、封面和歌词由接口提供；留空不展示歌单。<br>'
        . '保存新地址后使用新接口。相同地址缓存 1 小时；管理员需要重新拉取时，在音乐页地址后加 <code>?sg-refresh=1</code>。'
    )));
    $form->addInput(new Text('sgPlaylistLimit', null, '50', _t('歌单上限（条）'), _t('最多展示的曲目数量，默认 50；填 0 不限制。较大的歌单会增加页面加载时间。')));
    $form->addInput(new Text('sgIcp', null, '', _t('备案号'), _t('显示位置：页脚第一行「系统已稳定运行」的右侧。填阿里云备案下来的完整号（例：湘ICP备2023030699号）；留空则整段不输出，不留空位')));
    $form->addInput(new Text('sgUptimeSince', null, '', _t('建站日期'), _t('显示位置：页脚第一行「系统已稳定运行：N 天 N 小时」。格式 2024-01-01（也可写 2024/01/01）。不满一年显示天数，满一年显示「1 年 12 天 12 小时」；留空则自动取最早一篇已发布文章的时间')));
    $form->addInput(new Text('sgPageSize', null, '200', _t('每页条数上限'), _t('列表一次取数上限，默认 200；填 0 则沿用「设置 → 阅读」的每页文章数目。模板不输出分页条')));
}
