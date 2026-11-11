<?php
/** 说说 / 杂谈 / 归档列表里的轻卡片 */
?>
<article class="sg-glass sg-note">
  <time><?php $this->date('Y.m.d H:i'); ?></time>
  <h3><a href="<?php $this->permalink(); ?>"><?php $this->title(); ?></a></h3>
  <p><?php $this->excerpt(110, '...'); ?></p>
  <a class="sg-note-more" href="<?php $this->permalink(); ?>">阅读全文 →</a>
</article>
