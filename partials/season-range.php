<?php
/**
 * 岁候 · 区间展陈（评审稿 §3.2 第 4 段）
 *
 * ?t=（15 天）、?g=（30 天）、?d=（5 天）共用这一份结构：区间由调用方算好，
 * 这里只负责「按候分组 + 首屏 6 篇 + 其余进 <details> + 照片 2 + 碎语 2 + 空态」。
 *
 * 空态不写「暂无内容」：写「这一段还没有落笔。」并挂最近的非空区间链接。
 */
$sgSRange = $GLOBALS['SG_SEASON']['range'] ?? ['posts' => [], 'notes' => [], 'photos' => [], 'empty' => true, 'nearPrev' => [], 'nearNext' => []];
$sgSGroups = $GLOBALS['SG_SEASON']['groups'] ?? [];
$sgSRangeMeta = $GLOBALS['SG_SEASON']['rangeMeta'] ?? ['eyebrow' => '这一段', 'title' => '', 'sub' => ''];
$sgSRangeEyebrow = $sgSRangeMeta['eyebrow'];
$sgSRangeTitle = $sgSRangeMeta['title'];
$sgSRangeSub = $sgSRangeMeta['sub'];
$sgSYear = (int) ($GLOBALS['SG_SEASON']['year'] ?? 0);
$sgRangePosts = $sgSRange['posts'];
$sgRangeNotes = array_values(array_filter($sgSRange['notes'], static fn(array $note): bool => trim((string) $note['title']) !== ''));
$sgRangePhotos = $sgSRange['photos'];
$sgRangeTotal = count($sgRangePosts);

/** 单张文章卡：封面铺底压图，与全站卡片同一材质，但不依赖分类/标签数据。 */
$sgRangeCard = static function (array $post): string {
    $cover = trim((string) $post['cover']);
    $title = trim((string) $post['title']);
    if ($title === '') {
        $title = '未命名文章';
    }
    $date = date('m.d', sg_date_ts((int) $post['created']));
    $html = '<a class="sg-range-card' . ($cover === '' ? ' is-plain' : '') . '" href="' . sg_e((string) $post['url']) . '">';
    if ($cover !== '') {
        $html .= '<img class="sg-range-card-img" src="' . sg_e(sg_media($cover)) . '" alt="" loading="lazy" decoding="async">';
    }
    $html .= '<span class="sg-range-card-body">';
    $html .= '<span class="sg-range-card-title">' . sg_e($title) . '</span>';
    $html .= '<span class="sg-range-card-date">' . sg_e($date) . '</span>';
    $html .= '</span></a>';
    return $html;
};
?>
<section class="sg-season-range" aria-label="这一段时间的内容" data-sg-range>
  <header class="sg-range-head">
    <span class="sg-range-eyebrow"><?php echo sg_e($sgSRangeEyebrow); ?></span>
    <h2 class="sg-range-title"><?php echo sg_e($sgSRangeTitle); ?></h2>
    <p class="sg-range-sub"><?php echo sg_e($sgSRangeSub); ?></p>
    <span class="sg-range-count"><?php echo (int) $sgRangeTotal; ?> 篇</span>
  </header>

  <?php if ($sgRangeTotal === 0 && $sgRangeNotes === [] && $sgRangePhotos === []) : ?>
  <div class="sg-range-empty">
    <p>这一段还没有落笔。</p>
    <p class="sg-range-near">
      <?php if ($sgSRange['nearPrev']) : ?>
      <a href="<?php echo sg_e(sg_season_url(['t' => $sgSRange['nearPrev']['slug'], 'y' => $sgSYear])); ?>">上一段《<?php echo sg_e($sgSRange['nearPrev']['name']); ?>》有 <?php echo (int) $sgSRange['nearPrev']['count']; ?> 篇 →</a>
      <?php endif; ?>
      <?php if ($sgSRange['nearNext']) : ?>
      <a href="<?php echo sg_e(sg_season_url(['t' => $sgSRange['nearNext']['slug'], 'y' => $sgSYear])); ?>">下一段《<?php echo sg_e($sgSRange['nearNext']['name']); ?>》有 <?php echo (int) $sgSRange['nearNext']['count']; ?> 篇 →</a>
      <?php endif; ?>
      <?php if (!$sgSRange['nearPrev'] && !$sgSRange['nearNext']) : ?>
      <a href="<?php echo sg_e(sg_season_url(['y' => $sgSYear])); ?>">回到当前这一段 →</a>
      <?php endif; ?>
    </p>
  </div>
  <?php else : ?>
  <?php $sgRangeShown = 0; ?>
  <?php $sgRangeRest = []; ?>
  <?php foreach ($sgSGroups as $sgGroup) : ?>
  <?php if ($sgGroup['posts'] === []) {
      continue;
  } ?>
  <section class="sg-range-hou">
    <h3 class="sg-range-hou-title"><?php echo sg_e($sgGroup['label']); ?></h3>
    <div class="sg-range-grid">
      <?php foreach ($sgGroup['posts'] as $sgPost) : ?>
      <?php if ($sgRangeShown < 6) : ?>
      <?php echo $sgRangeCard($sgPost); ?>
      <?php $sgRangeShown++; ?>
      <?php else : ?>
      <?php $sgRangeRest[] = $sgPost; ?>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($sgRangeRest !== []) : ?>
  <details class="sg-range-all">
    <summary>全部 <?php echo $sgRangeTotal; ?> 篇</summary>
    <div class="sg-range-grid">
      <?php foreach ($sgRangeRest as $sgPost) : ?>
      <?php echo $sgRangeCard($sgPost); ?>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>

  <?php if ($sgRangePhotos !== []) : ?>
  <div class="sg-range-photos" aria-label="这一段的照片">
    <?php foreach ($sgRangePhotos as $sgPhoto) : ?>
    <figure class="sg-range-photo">
      <img src="<?php echo sg_e(sg_media((string) ($sgPhoto['thumb'] ?: $sgPhoto['url']))); ?>" alt="<?php echo sg_e((string) $sgPhoto['caption']); ?>" loading="lazy" decoding="async">
      <?php if ((string) $sgPhoto['caption'] !== '') : ?>
      <figcaption><?php echo sg_e((string) $sgPhoto['caption']); ?></figcaption>
      <?php endif; ?>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($sgRangeNotes !== []) : ?>
  <div class="sg-range-notes" aria-label="这一段写下的碎语">
    <?php foreach (array_slice($sgRangeNotes, 0, 2) as $sgNote) : ?>
    <p class="sg-range-note"><?php echo sg_e((string) $sgNote['title']); ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</section>
