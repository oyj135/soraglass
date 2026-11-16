<?php
/**
 * 归档 / 分类 / 标签 / 搜索列表里的文章卡（参考图样式 2026-11-16）。
 * 结构：顶部大图（含「」引号摘要）+ 深色信息区（居中标题 / 日期 / 标签）。
 * V 形折角与配色由 .sg-feature-card 样式负责，本模板只提供内容。
 */
$sgCardTitle = trim((string) $this->title);
$sgCardDesc  = trim(strip_tags((string) $this->excerpt(88, '')));
// date 属性是 Typecho 的时间对象；(string) 转换取到时间戳
$sgCardStamp = 0;
try {
    $sgCardStamp = (int) (string) $this->date;
} catch (\Throwable $e) {
    $sgCardStamp = 0;
}
$sgCardAgo   = sg_time_ago($sgCardStamp);
$sgCardCate  = sg_first_category($this, '');
$sgCardTags  = '';
ob_start();
$this->tags(' · ', false, '');
$sgCardTags = trim((string) ob_get_clean());
?>
<article class="sg-feature-card" data-sg-filter-item>
  <a class="sg-feature-media" href="<?php $this->permalink(); ?>" tabindex="-1" aria-hidden="true">
    <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="" loading="lazy">
    <?php if ($sgCardDesc !== '') : ?>
    <span class="sg-feature-quote">「<?php echo sg_e($sgCardDesc); ?>」</span>
    <?php endif; ?>
  </a>
  <div class="sg-feature-body">
    <h3 class="sg-feature-title"><a href="<?php $this->permalink(); ?>"><?php echo sg_e($sgCardTitle !== '' ? $sgCardTitle : '未命名文章'); ?></a></h3>
    <div class="sg-feature-meta">
      <span class="is-date"><?php echo sg_icon('clock'); ?><?php echo sg_e($sgCardAgo !== '' ? '发表于 ' . $sgCardAgo : '发表于 ' . $this->date('Y.m.d')); ?></span>
      <?php if ($sgCardCate !== '') : ?>
      <span class="is-cate"><?php echo sg_icon('folder'); ?><?php echo sg_e($sgCardCate); ?></span>
      <?php endif; ?>
    </div>
    <?php if ($sgCardTags !== '') : ?>
    <div class="sg-feature-tags">
      <span class="is-tag"><?php echo sg_icon('tag'); ?><?php echo sg_e($sgCardTags); ?></span>
    </div>
    <?php endif; ?>
  </div>
</article>
