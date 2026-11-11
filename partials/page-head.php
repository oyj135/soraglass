<?php
/**
 * 独立页统一页头：返回胶囊 + 大标题 + 副标题 +（可选）搜索框。
 * 用法：
 *   $sgHead = ['title' => ..., 'sub' => ..., 'search' => true, 'center' => false, 'placeholder' => '...'];
 *   $this->need('partials/page-head.php');
 */
$sgHead = isset($sgHead) && is_array($sgHead) ? $sgHead : [];
$sgHeadTitle = (string) ($sgHead['title'] ?? '');
$sgHeadSub = (string) ($sgHead['sub'] ?? '');
$sgHeadBack = (bool) ($sgHead['back'] ?? true);
$sgHeadSearch = (bool) ($sgHead['search'] ?? false);
$sgHeadCenter = (bool) ($sgHead['center'] ?? false);
$sgHeadPlaceholder = (string) ($sgHead['placeholder'] ?? '搜索标题、描述或标签…');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<div class="sg-page-head">
  <?php if ($sgHeadBack) : ?>
  <a class="sg-back" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>返回上一级</a>
  <?php endif; ?>
  <div class="sg-page-head-row<?php echo $sgHeadCenter ? ' is-centered' : ''; ?>">
    <div>
      <h1 class="sg-page-title"><?php echo sg_e($sgHeadTitle); ?></h1>
      <?php if ($sgHeadSub !== '') : ?><p class="sg-page-sub"><?php echo sg_e($sgHeadSub); ?></p><?php endif; ?>
    </div>
    <?php if ($sgHeadSearch) : ?>
    <form class="sg-search sg-search--sm" role="search" method="get" action="<?php echo sg_e($sgHome); ?>">
      <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
      <input class="sg-search-input" type="search" name="s" placeholder="<?php echo sg_e($sgHeadPlaceholder); ?>" aria-label="站内搜索">
    </form>
    <?php endif; ?>
  </div>
</div>
