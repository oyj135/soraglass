<?php
/** 归档 / 分类 / 搜索列表里的文章卡 */
?>
<article class="sg-glass sg-post-card" data-sg-filter-item>
  <a class="sg-thumb" href="<?php $this->permalink(); ?>" tabindex="-1" aria-hidden="true">
    <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="" loading="lazy">
  </a>
  <div class="sg-card-body">
    <time class="sg-card-date"><?php $this->date('Y.m.d'); ?></time>
    <h3 class="sg-card-title"><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h3>
    <p class="sg-card-desc"><?php $this->excerpt(88, '...'); ?></p>
    <div class="sg-tags"><span><?php echo sg_e(sg_first_category($this)); ?></span></div>
  </div>
</article>
