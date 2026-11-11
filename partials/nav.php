<?php
/**
 * 顶部导航 + 移动端半圆盘 + 抽屉。
 * 高亮规则：首页 → index；独立页 → 缩略名；归档/搜索 → 归档页（timeline）。
 */
$sgActive = sg_current_slug($this);
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
$sgBrandTitle = sg_opt('sgNavTitle', (string) $this->options->title);
$sgBrandSuffix = sg_opt('sgNavSuffix', 'の');
$sgBrandAfter = sg_opt('sgNavAfter', '空璃');
?>
<header class="sg-nav" data-sg-nav>
  <div class="sg-nav-inner">
    <a class="sg-brand" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_e($sgBrandTitle); ?><span class="sg-brand-accent"><?php echo sg_e($sgBrandSuffix); ?></span><?php echo sg_e($sgBrandAfter); ?></a>
    <nav class="sg-nav-links" aria-label="主导航">
      <a class="sg-nav-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>" href="<?php echo sg_e($sgHome); ?>">首页<?php if ($sgActive === 'index') : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php $this->widget('Widget_Contents_Page_List')->to($sgNavPages); ?>
      <?php while ($sgNavPages->next()): ?>
      <?php $sgNavSlug = (string) $sgNavPages->slug; ?>
      <a class="sg-nav-link<?php echo $sgActive === $sgNavSlug ? ' is-active' : ''; ?>" href="<?php $sgNavPages->permalink(); ?>"><?php $sgNavPages->title(); ?><?php if ($sgActive === $sgNavSlug) : ?><span class="sg-nav-dot"></span><?php endif; ?></a>
      <?php endwhile; ?>
    </nav>
  </div>
</header>

<button class="sg-disc" type="button" data-sg-sheet-open aria-label="打开导航">
  <span class="sg-disc-dots"><i></i><i></i><i></i></span>
</button>

<div class="sg-sheet" data-sg-sheet aria-hidden="true">
  <div class="sg-sheet-panel">
    <a class="sg-sheet-link<?php echo $sgActive === 'index' ? ' is-active' : ''; ?>" href="<?php echo sg_e($sgHome); ?>">首页</a>
    <?php $this->widget('Widget_Contents_Page_List')->to($sgSheetPages); ?>
    <?php while ($sgSheetPages->next()): ?>
    <?php $sgSheetSlug = (string) $sgSheetPages->slug; ?>
    <a class="sg-sheet-link<?php echo $sgActive === $sgSheetSlug ? ' is-active' : ''; ?>" href="<?php $sgSheetPages->permalink(); ?>"><?php $sgSheetPages->title(); ?></a>
    <?php endwhile; ?>
    <hr class="sg-divider">
    <button class="sg-sheet-link" type="button" data-sg-theme-toggle><?php echo sg_icon('sun', 'sg-i-sun'); ?><?php echo sg_icon('moon', 'sg-i-moon'); ?> 切换昼夜</button>
  </div>
</div>
