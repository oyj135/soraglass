<?php
/** 首页封面轮播的单张（由 index.php 在循环中 include，$sgSlideActive 控制首屏显示） */
$sgSlideActive = !empty($sgSlideActive);
?>
<article class="sg-post-slide<?php echo $sgSlideActive ? ' is-active' : ''; ?>" data-sg-slide>
  <img class="sg-slide-cover" src="<?php echo sg_e(sg_cover($this)); ?>" alt="" <?php echo $sgSlideActive ? '' : 'loading="lazy"'; ?>>
  <span class="sg-slide-veil"></span>
  <a class="sg-slide-body" href="<?php $this->permalink(); ?>">
    <span class="sg-slide-chips">
      <span class="sg-chip--tag"><?php echo sg_e(sg_first_category($this)); ?></span>
      <span class="sg-chip--mono"><?php $this->date('Y.m.d'); ?></span>
    </span>
    <h2 class="sg-slide-title"><?php $this->title(); ?></h2>
    <span class="sg-slide-desc"><?php $this->excerpt(72, '...'); ?></span>
  </a>
</article>
