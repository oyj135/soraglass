<?php
/** 首页「杂谈」轮播的单张（$sgSlideActive 控制首帧，由 $GLOBALS['SG_SLIDE_SEEN'] 推出） */
$sgSlideActive = empty($GLOBALS['SG_SLIDE_SEEN']);
$GLOBALS['SG_SLIDE_SEEN'] = true;
?>
<article class="sg-chatter-slide<?php echo $sgSlideActive ? ' is-active' : ''; ?>" data-sg-slide>
  <img src="<?php echo sg_e(sg_cover($this)); ?>" alt="" <?php echo $sgSlideActive ? '' : 'loading="lazy"'; ?>>
  <span class="sg-veil"></span>
  <a class="sg-chatter-body" href="<?php $this->permalink(); ?>">
    <span class="sg-chatter-meta">
      <span class="sg-chip--tag"><?php echo sg_e(sg_first_category($this, '杂谈')); ?></span>
      <time><?php $this->date('Y.m.d H:i'); ?></time>
    </span>
    <h3><?php $this->title(); ?></h3>
    <p><?php $this->excerpt(64, '...'); ?></p>
  </a>
</article>
