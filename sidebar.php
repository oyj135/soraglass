<?php
/** 文章页右侧栏：最近文章（碎语不进这里：它有自己的碎语页，2026-11-13） */
$sgNoteTotal = count(sg_note_cids());
$this->widget('Widget_Contents_Post_Recent', 'pageSize=' . (5 + $sgNoteTotal))->to($sgRecent);

$sgSideRecent = [];
while ($sgRecent->next()) {
    if (sg_is_note((int) $sgRecent->cid)) {
        continue;
    }
    $sgSideRecent[] = [
        'url'   => (string) $sgRecent->permalink,
        'title' => (string) $sgRecent->title,
        'date'  => $sgRecent->date->format('Y.m.d'),
    ];
    if (count($sgSideRecent) >= 5) {
        break;
    }
}

if (!$sgSideRecent) {
    return;
}
?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">最近文章</h3>
  <ul class="sg-side-list">
    <?php foreach ($sgSideRecent as $sgItem) : ?>
    <li>
      <a href="<?php echo sg_e($sgItem['url']); ?>"><?php echo sg_e($sgItem['title']); ?></a>
      <time><?php echo sg_e($sgItem['date']); ?></time>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
