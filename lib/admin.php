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
 * 后台「按城市名查询坐标」按钮（2026-11-20），挂在「站长城市」输入框的 description 上。
 *
 * 为什么要服务端代理：和风 GeoAPI 要用专属 Host + 凭据（API KEY 或 JWT 私钥签名），
 * 私钥不能出现在浏览器里；所以后台只把城市名发到 `/?sg-geo=杭州`，由 PHP 取坐标回传。
 * 该端点只读（返回 lat/lon/tz），不写配置，保存仍然走最下面「保存设置」。
 */
function sg_admin_geo_lookup(): string
{
    $base = '';
    try {
        $base = rtrim((string) \Widget\Options::alloc()->siteUrl, '/');
    } catch (\Throwable $e) {
        return '';
    }
    if ($base === '') {
        return '';
    }

    $endpointJson = json_encode($base . '/?sg-geo=', JSON_UNESCAPED_SLASHES);

    return <<<HTML
<script>
(function () {
  var endpoint = {$endpointJson};
  var OK = '#646970';
  var BAD = '#d63638';
  var ERRORS = {
    'host-missing': '先把上面的「和风 API Host」填好（形如 abc1234xyz.re.qweatherapi.com）',
    'credential-missing': '先把和风 API KEY 或 JWT 三项 + 私钥填好，GeoAPI 用的是同一套凭据',
    'lookup-failed': '和风没识别这个城市，换个写法试试（如 杭州 / Hangzhou / 杭州,CN）',
    'invalid-city': '城市名不能为空，长度不超过 40 个字符',
    'forbidden': '登录状态已失效，请重新登录后台'
  };

  function fieldOf(name) {
    return document.querySelector('input[name="' + name + '"],textarea[name="' + name + '"]');
  }

  function boot() {
    var city = fieldOf('sgSiteCity');
    if (!city || city.getAttribute('data-sg-geo-ready')) { return; }
    city.setAttribute('data-sg-geo-ready', '1');

    var wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;margin:.5rem 0 0';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-s';
    btn.textContent = '按城市名查询坐标';

    var tip = document.createElement('span');
    tip.style.cssText = 'font-size:12px;color:' + OK;

    btn.addEventListener('click', function () {
      var name = city.value.trim();
      if (name === '') {
        tip.style.color = BAD;
        tip.textContent = '先在上面填城市名，例如 杭州';
        city.focus();
        return;
      }
      btn.disabled = true;
      tip.style.color = OK;
      tip.textContent = '查询中…';
      fetch(endpoint + encodeURIComponent(name), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (res) {
          return res.text().then(function (body) {
            var text = body.replace(/^\s+/, '');
            if (text.charAt(0) !== '{') {
              throw new Error('接口返回的不是 JSON（HTTP ' + res.status + '），检查站点伪静态是否把 /index.php 也拦掉了');
            }
            return JSON.parse(text);
          });
        })
        .then(function (json) {
          if (!json.ok) {
            tip.style.color = BAD;
            tip.textContent = '查询失败：' + (ERRORS[json.error] || ('和风返回 ' + (json.error || '未知错误')));
            return;
          }
          var lat = fieldOf('sgSiteLatitude');
          var lon = fieldOf('sgSiteLongitude');
          var tz = fieldOf('sgSiteTimezone');
          if (lat) { lat.value = json.latitude; }
          if (lon) { lon.value = json.longitude; }
          if (tz && json.timezone) { tz.value = json.timezone; }
          tip.style.color = OK;
          tip.textContent = '已回填 ' + json.name + (json.adm ? '（' + json.adm + '）' : '')
            + ' → ' + json.latitude + ', ' + json.longitude + ' · ' + json.timezone + '；点最下方「保存设置」生效';
        })
        .catch(function (err) {
          tip.style.color = BAD;
          tip.textContent = '查询失败：' + (err && err.message ? err.message : '网络不可达');
        })
        .then(function () { btn.disabled = false; });
    });

    wrap.appendChild(btn);
    wrap.appendChild(tip);
    city.insertAdjacentElement('afterend', wrap);
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
    /* 构建产物自检（P0-3）：dist 在 .gitignore 中、发布后被清，主题目录误传源码时
       前台会静默裸奔。这里在设置页顶部直接给出可见状态，省得站长靠猜。 */
    $sgBuildState = sg_asset_ready()
        ? '<span style="color:#1a7f37">✓ 构建产物就绪（dist/.vite/manifest.json）</span>'
        : '<span style="color:#d63638">✗ 缺少 dist 构建产物：请本地 npm run release 后上传 soraglass.zip（含 dist），勿只传源码目录</span>';
    echo '<p class="description">' . $sgBuildState . '</p>';
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
    $form->addInput(new Radio('sgCalendarDetail', $effectOptions, 'on', _t('首页详细日期'), _t('显示大日期、农历、生肖、节气与年内第几天。公历月历和春节倒计时始终保留。')));
    $form->addInput(new Text('sgCalendarTimezone', null, 'Asia/Shanghai', _t('日历时区'), _t('公历、农历、春节与进度条共同使用的时区，例如 Asia/Shanghai；无效值回退上海时区。')));
    $form->addInput(new Select('sgSidebarLocation', ['off' => _t('关闭欢迎卡'), 'site' => _t('站长城市'), 'visitor' => _t('访客 IP 归属地')], 'site', _t('首页欢迎信息'), _t('站长城市模式不查询访客位置；访客模式通过服务端定位，默认不显示 IP 地址。定位失败时显示通用欢迎，天气回退站长城市。')));
    $form->addInput(new Text('sgSiteCity', null, '', _t('站长城市'), _t('用于欢迎、天气回退和距离基点。留空不输出虚构城市，例如填写自己的城市名称。填好后可点下面按钮用和风 GeoAPI 反查坐标与时区。') . sg_admin_geo_lookup()));
    $form->addInput(new Text('sgSiteLatitude', null, '', _t('站长城市纬度'), _t('十进制度数，范围 -90 到 90；使用城市代表位置的 WGS84 坐标，不需要精确住址。留空时按「站长城市」用和风 GeoAPI 自动解析（需配好 API Host 与凭据）。')));
    $form->addInput(new Text('sgSiteLongitude', null, '', _t('站长城市经度'), _t('十进制度数，范围 -180 到 180；与纬度共同填写，用于估算距离及日出日落。留空时同纬度一样自动解析。')));
    $form->addInput(new Text('sgSiteTimezone', null, 'Asia/Shanghai', _t('站长城市时区'), _t('例如 Asia/Shanghai。日出日落按对应城市时区显示，独立于日历时区。清空后采用 GeoAPI 返回的时区。')));
    $sgGeoDescription = '腾讯位置服务 WebService Key，仅用于访客模式，密钥留服务端。可在腾讯控制台限制到服务器出口 IP；代理部署须由 Nginx real_ip 还原 REMOTE_ADDR，主题不信任客户端转发头。';
    if (sg_opt('sgSidebarLocation', 'site') === 'visitor' && sg_sidebar_private_dir() === '') $sgGeoDescription .= ' 当前无法使用站点之外的私有临时缓存目录，访客定位将停用；请放行 PHP 临时目录写入，不使用公开 dist 目录。';
    $form->addInput(new Text('sgGeoKey', null, '', _t('访客定位密钥'), _t($sgGeoDescription)));
    $form->addInput(new Radio('sgWeather', $effectOptions, 'off', _t('首页天气'), _t('开启后在日历底部显示城市天气、温度、湿度与更新时间；需填写下面的和风 Host 和密钥。未配置或失败时显示「天气暂不可用」。')));
    $form->addInput(new Text('sgWeatherHost', null, '', _t('和风 API Host'), _t('和风控制台「设置 → API Host」里那串专属域名，形如 abc1234xyz.re.qweatherapi.com（可含 .re 等子段）；只填域名，不含 https:// 或路径，不要填 console.qweather.com。使用当前实时天气产品和 GeoAPI 城市查询。')));
    $form->addInput(new Text('sgWeatherKey', null, '', _t('和风 API Key'), _t('可选。控制台「项目 → 凭据 → 添加凭据（API KEY）」生成，只显示一次，服务端用 X-QW-Api-Key 请求头认证。与下面的 JWT 四项目二选一：JWT 填齐时优先 JWT。和风将自 2027-01-01 起限制 API KEY 的每日请求量。')));
    $sgJwtHint = sg_sidebar_jwt_supported()
        ? '当前服务器已支持 Ed25519 签名（sodium）。'
        : '当前 PHP 未启用 sodium 扩展，无法生成 Ed25519 签名；请在 PHP 扩展里启用 sodium（或升级到 PHP 8.4）后再用 JWT。';
    $form->addInput(new Text('sgWeatherJwtKid', null, '', _t('和风 JWT 凭据 ID'), _t('控制台「项目 → 凭据」里 JWT 凭据的 ID，即 JWT header 的 kid。与下面三项填齐后自动改用 JWT，API KEY 可留空。')));
    $form->addInput(new Text('sgWeatherDevId', null, '', _t('和风开发者 ID'), _t('控制台「设置」里的开发者 ID，Q 开头 10 位，即 JWT payload 的 iss。')));
    $form->addInput(new Text('sgWeatherProjectId', null, '', _t('和风项目 ID'), _t('控制台「项目管理」里该凭据所属项目的 ID，即 JWT payload 的 sub（不是项目名称）。')));
    $form->addInput(new Textarea('sgWeatherJwtKey', null, '', _t('和风 JWT 私钥'), _t('Ed25519 私钥全文，含 -----BEGIN PRIVATE KEY----- 与 -----END PRIVATE KEY-----；只存服务端，不下发浏览器。令牌由服务端每次签发（有效期 15 分钟，和风上限 24 小时）。' . $sgJwtHint)));
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
