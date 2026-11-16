<?php
/**
 * 文章页 / 独立页右侧栏卡片。
 *
 * 顺序与开关由后台「侧栏卡片与顺序」控制（逗号分隔）：
 *   notice     公告（sgNotice 为空时自动隐藏）
 *   recent     最近文章（碎语不进这里）
 *   categories 分类
 *   tags       标签
 *   archives   归档
 *   webinfo    站点信息
 *
 * 作者卡与目录不在这里：它们在 post.php / page.php 的 aside 里单独输出，
 * 避免同一张卡在文章页出现两次。
 */
$sgCardKeys = array_values(array_unique(array_filter(array_map(
    'trim',
    explode(',', sg_opt('sgSidebar', 'notice,recent,categories,tags,webinfo'))
))));
$sgKnownCards = ['notice', 'recent', 'categories', 'tags', 'webinfo'];
$sgCardKeys = array_values(array_intersect($sgCardKeys, $sgKnownCards));

if (!$sgCardKeys) {
    return;
}

$sgNotice = trim(sg_opt('sgNotice', ''));
?>
<?php foreach ($sgCardKeys as $sgCard) : ?>
<?php if ($sgCard === 'notice' && $sgNotice !== '') : ?>
<section class="sg-glass sg-side-card sg-side-notice">
  <h3 class="sg-side-title">公告</h3>
  <p><?php echo nl2br(sg_e($sgNotice)); ?></p>
</section>

<?php elseif ($sgCard === 'recent') : ?>
<?php
$sgNoteTotal = count(sg_note_cids());
$sgRecent = null;
try {
    $this->widget('Widget_Contents_Post_Recent', 'pageSize=' . (5 + $sgNoteTotal))->to($sgRecent);
} catch (\Throwable $e) {
    $sgRecent = null;
}
$sgSideRecent = [];
if ($sgRecent) {
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
}
?>
<?php if ($sgSideRecent) : ?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">最近文章</h3>
  <ul class="sg-side-list">
    <?php foreach ($sgSideRecent as $sgItem) : ?>
    <li>
      <a href="<?php echo sg_e($sgItem['url']); ?>"><?php echo sg_e($sgItem['title'] !== '' ? $sgItem['title'] : '未命名文章'); ?></a>
      <time><?php echo sg_e($sgItem['date']); ?></time>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php elseif ($sgCard === 'categories') : ?>
<?php
$sgCats = null;
try {
    $this->widget('Widget_Metas_Category_List')->to($sgCats);
} catch (\Throwable $e) {
    $sgCats = null;
}
$sgCatRows = [];
if ($sgCats) {
    while ($sgCats->next()) {
        if ((int) $sgCats->count < 1) {
            continue;
        }
        $sgCatRows[] = [
            'name'  => (string) $sgCats->name,
            'count' => (int) $sgCats->count,
            'url'   => (string) $sgCats->permalink,
        ];
        if (count($sgCatRows) >= 8) {
            break;
        }
    }
}
?>
<?php if ($sgCatRows) : ?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">分类</h3>
  <ul class="sg-side-terms">
    <?php foreach ($sgCatRows as $sgRow) : ?>
    <li>
      <a href="<?php echo sg_e($sgRow['url']); ?>">
        <span><?php echo sg_e($sgRow['name']); ?></span>
        <b><?php echo (int) $sgRow['count']; ?></b>
      </a>
    </li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php elseif ($sgCard === 'tags') : ?>
<?php
$sgCloud = null;
try {
    $this->widget('Widget_Metas_Tag_Cloud', 'sort=count&ignoreZeroCount=1&desc=1&limit=14')->to($sgCloud);
} catch (\Throwable $e) {
    $sgCloud = null;
}
$sgTagRows = [];
if ($sgCloud) {
    while ($sgCloud->next()) {
        $sgTagRows[] = [
            'name'  => (string) $sgCloud->name,
            'count' => (int) $sgCloud->count,
            'url'   => (string) $sgCloud->permalink,
        ];
    }
}
?>
<?php if ($sgTagRows) : ?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">标签</h3>
  <div class="sg-side-tags">
    <?php foreach ($sgTagRows as $sgRow) : ?>
    <a href="<?php echo sg_e($sgRow['url']); ?>"><?php echo sg_icon('tag'); ?><?php echo sg_e($sgRow['name']); ?><b><?php echo (int) $sgRow['count']; ?></b></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php elseif ($sgCard === 'webinfo') : ?>
<?php
$sgCounts = sg_stats();
$sgUptime = sg_uptime();
?>
<section class="sg-glass sg-side-card">
  <h3 class="sg-side-title">站点信息</h3>
  <div class="sg-side-webinfo">
    <div>
      <b><?php echo sg_e($sgCounts['posts']); ?></b>
      <span>文章</span>
    </div>
    <div>
      <b><?php echo sg_e($sgCounts['notes']); ?></b>
      <span>碎语</span>
    </div>
    <div>
      <b><?php echo sg_e($sgCounts['photos']); ?></b>
      <span>照片</span>
    </div>
    <div class="is-wide">
      <b><?php echo sg_e((string) $sgUptime['text']); ?></b>
      <span>已稳定运行</span>
    </div>
  </div>
</section>
<?php endif; ?>
<?php endforeach; ?>
