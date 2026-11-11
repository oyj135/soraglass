<?php
/** 文章页右侧栏：最近文章 */
$this->widget('Widget_Contents_Post_Recent', 'pageSize=5')->to($sgRecent);
?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">最近文章</h3>
  <ul class="sg-side-list">
    <?php while ($sgRecent->next()) : ?>
    <li>
      <a href="<?php $sgRecent->permalink(); ?>"><?php $sgRecent->title(); ?></a>
      <time><?php $sgRecent->date('Y.m.d'); ?></time>
    </li>
    <?php endwhile; ?>
  </ul>
</section>
