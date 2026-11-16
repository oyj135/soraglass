<?php
/**
 * 首页 100vh 全屏图片首屏（参考 Fomalhaut #page-header.full_page + #site-info）。
 *
 * 命名空间一律用 .sg-front* —— 首页主区早已占用 .sg-hero / .sg-hero-cover，
 * 2026-11-16 曾因同名把首页布局改坏，务必隔离，勿改回 .sg-hero。
 * 背景取现有壁纸首图（明暗各一），站名/副标题/社交全部复用后台已有设置项，
 * 不新增设置项、不引入打字机等第三方脚本。
 */
$sgFrontWalls = sg_wallpapers();
$sgFrontLight = $sgFrontWalls['light'][0] ?? sg_theme_url('dist/img/bg-1.svg');
$sgFrontDark  = $sgFrontWalls['dark'][0] ?? sg_theme_url('dist/img/bg-1.svg');
$sgFrontName  = sg_opt('sgNavTitle', (string) $this->options->title);
$sgFrontAfter = sg_opt('sgNavAfter', '空璃');
$sgFrontSub   = (string) $this->options->description;
$sgFrontSocial = sg_json('sgSocial', 'social', []);
?>
<section class="sg-front" data-sg-front aria-label="站点首屏">
  <div class="sg-front-media" aria-hidden="true">
    <img class="sg-front-img sg-front-img--light" src="<?php echo sg_e($sgFrontLight); ?>" alt="" fetchpriority="high">
    <img class="sg-front-img sg-front-img--dark" src="<?php echo sg_e($sgFrontDark); ?>" alt="" fetchpriority="high">
    <span class="sg-front-veil"></span>
  </div>
  <div class="sg-front-inner">
    <h1 class="sg-front-title"><?php echo sg_e($sgFrontName); ?><span class="sg-front-title-mark">· <?php echo sg_e($sgFrontAfter); ?></span></h1>
    <?php if ($sgFrontSub !== '') : ?><p class="sg-front-sub"><?php echo sg_e($sgFrontSub); ?></p><?php endif; ?>
    <?php if ($sgFrontSocial) : ?>
    <div class="sg-front-social">
      <?php foreach ($sgFrontSocial as $sgFrontItem) : ?>
      <?php if (!is_array($sgFrontItem)) { continue; } ?>
      <a class="sg-front-social-link" href="<?php echo sg_e(sg_value($sgFrontItem, 'url', '#')); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e(sg_value($sgFrontItem, 'name', '链接')); ?>" aria-label="<?php echo sg_e(sg_value($sgFrontItem, 'name', '链接')); ?>">
        <?php echo sg_icon(sg_social_icon($sgFrontItem)); ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <button class="sg-front-next" type="button" data-sg-front-next aria-label="向下滚动查看正文">
    <span class="sg-front-next-label">向下探索</span>
    <?php echo sg_icon('chevron-down'); ?>
  </button>
</section>
