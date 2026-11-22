<?php
/**
 * 网址导航 —— 分组书签盒（2026-11-22 第六十五轮新增 / 第六十六轮改造数据源，方案 docs/proposals/box-nav.md）：
 * 数据源优先级（见 lib/fields.php sg_nav_box_for()）：
 *   ① 页面正文直接粘 JSON（以 [ 开头的分组数组，在编辑器里填，正文不再当文章渲染）；
 *   ② 后台「导航书签盒 · JSON」（sgNavBox，多页共用 / 兜底）；
 *   ③ 都没有 → 空态。
 * 正文不是 JSON 时当页面介绍文字渲染（sg-prose），数据回落全局设置。
 * 结构 = 全站 sg_page_head 页头 + 搜索框（即时过滤在 src/scripts/modules/nav-box.ts）
 *   + 分组卡片网格 + 右侧「通讯录」分组锚点栏（≥1280 粘性侧栏 + scrollspy，窄屏吸顶胶囊条）；
 * 图标三级策略：手填 icon → favicon.im 按域名在线取 → 失败回退站名首字圆徽（sg_nav_icon_url()）。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '收藏的网站与工具，一页打尽。'),
    'center' => true,
];

/* 正文原文：页面数据可以直接粘在正文编辑器里，模板按内容形态分流；
    第六十七轮起与项目页/友链页共用 sg_page_raw_text()（含剥 ```json 围栏）。 */
$sgRaw = sg_page_raw_text($this);
$sgFromBody = false;
$sgNavGroups = sg_nav_box_for($this, $sgRaw, $sgFromBody);
/* 正文不是被采用的数据时，把正文当介绍文字渲染（坏 JSON 也显示原文，便于修正） */
$sgNavProse = ($sgRaw !== '' && !$sgFromBody) ? $sgRaw : '';
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="nav">
  <?php sg_page_head($sgHead); ?>

  <?php if ($sgNavProse !== '') : ?>
  <div class="sg-prose sg-navbox-prose"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if (!$sgNavGroups) : ?>
  <div class="sg-glass sg-notfound" style="margin-top:2.5rem">
    <span class="sg-notfound-code">空</span>
    <h1>导航清单还没有配置</h1>
    <p>两种填法任选：① 编辑本页，正文直接粘贴 JSON（以 <code>[</code> 开头的分组数组，格式见后台示例）；② 后台 → 外观 → 主题设置 → 「导航书签盒 · JSON」。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e(rtrim((string) $this->options->siteUrl, '/') . '/'); ?>"><?php echo sg_icon_color('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php else : ?>

  <form class="sg-search sg-search--sm sg-navbox-search" role="search" method="get" action="#">
    <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
    <input class="sg-search-input" type="search" name="s" placeholder="搜索站名、简介或域名..." aria-label="搜索导航站点" autocomplete="off">
  </form>

  <div class="sg-navbox-wrap">
    <aside class="sg-navbox-side" data-sg-nav-side aria-label="分组锚点">
      <?php foreach ($sgNavGroups as $sgGi => $sgGroup) : ?>
      <a class="sg-navbox-side-link" href="#sg-nav-g<?php echo (int) $sgGi; ?>" data-sg-nav-anchor="sg-nav-g<?php echo (int) $sgGi; ?>">
        <span><?php echo sg_e($sgGroup['name']); ?></span><i><?php echo count($sgGroup['sites']); ?></i>
      </a>
      <?php endforeach; ?>
    </aside>

    <div class="sg-navbox-main" data-sg-nav-filter>
      <?php foreach ($sgNavGroups as $sgGi => $sgGroup) : ?>
      <section class="sg-navbox-group" id="sg-nav-g<?php echo (int) $sgGi; ?>" data-sg-nav-group>
        <h2 class="sg-navbox-group-title">
          <i><?php echo sg_e(str_pad((string) ($sgGi + 1), 2, '0', STR_PAD_LEFT)); ?></i>
          <?php echo sg_e($sgGroup['name']); ?>
          <span><?php echo count($sgGroup['sites']); ?> 站</span>
        </h2>
        <div class="sg-navbox-grid">
          <?php foreach ($sgGroup['sites'] as $sgSite) :
              $sgKeywords = implode(' ', array_filter([$sgSite['name'], $sgSite['desc'], $sgSite['url']]));
              $sgIcon = sg_nav_icon_url($sgSite['url'], $sgSite['icon']);
              $sgInitial = function_exists('mb_substr') ? mb_substr($sgSite['name'], 0, 1, 'UTF-8') : substr($sgSite['name'], 0, 1);
          ?>
          <a class="sg-navbox-card" data-keywords="<?php echo sg_e($sgKeywords); ?>" href="<?php echo sg_e($sgSite['url']); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e($sgSite['name']); ?>">
            <span class="sg-navbox-icowrap">
              <span class="sg-navbox-fallback" aria-hidden="true"><?php echo sg_e($sgInitial); ?></span>
              <?php if ($sgIcon !== '') : ?>
              <img class="sg-navbox-ico" src="<?php echo sg_e($sgIcon); ?>" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.classList.add('is-broken')">
              <?php endif; ?>
            </span>
            <span class="sg-navbox-text">
              <b><?php echo sg_e($sgSite['name']); ?></b>
              <?php if ($sgSite['desc'] !== '') : ?><span><?php echo sg_e($sgSite['desc']); ?></span><?php endif; ?>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endforeach; ?>
      <?php /* 空态与即时过滤由 src/scripts/modules/nav-box.ts 在容器后插入/驱动 */ ?>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php
$this->need('footer.php');
