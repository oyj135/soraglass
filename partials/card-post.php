<?php
/** 归档 / 分类 / 搜索列表里的文章卡 */
$sgCardTitle = trim((string) $this->title);
?>
<article class="sg-post-card" data-sg-filter-item>
  <a class="sg-thumb" href="<?php $this->permalink(); ?>" tabindex="-1" aria-hidden="true">
    <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="" loading="lazy">
  </a>
  <div class="sg-card-body">
    <div class="sg-card-meta">
      <time class="sg-card-date" datetime="<?php $this->date('c'); ?>"><?php $this->date('Y.m.d'); ?></time>
      <span class="sg-card-category"><?php echo sg_e(sg_first_category($this)); ?></span>
    </div>
    <h3 class="sg-card-title"><a href="<?php $this->permalink(); ?>"><?php echo sg_e($sgCardTitle !== '' ? $sgCardTitle : '未命名文章'); ?></a></h3>
    <p class="sg-card-desc"><?php $this->excerpt(88, '...'); ?></p>
  </div>
</article>
