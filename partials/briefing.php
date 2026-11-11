<?php
/**
 * 每日选刊：折叠区块 + 封面轮播 + 期号分页。
 * 数据来自后台「每日选刊 JSON」，留空读 dist/data/briefing.json；两者都为空则整块不渲染。
 */
$sgBriefing = sg_json('sgBriefing', 'briefing', []);
$sgBriefing = array_values(array_filter($sgBriefing, static fn($item) => is_array($item)));
?>
<?php if ($sgBriefing) : ?>
<section class="sg-glass sg-briefing" data-sg-briefing>
  <div class="sg-briefing-head">
    <div class="sg-briefing-title">
      <h2>每日选刊</h2>
      <span>共 <?php echo count($sgBriefing); ?> 期</span>
    </div>
    <div class="sg-briefing-actions">
      <button type="button" data-sg-briefing-toggle>
        <span data-sg-briefing-toggle-text>展开</span><?php echo sg_icon('chevron-down'); ?>
      </button>
      <span class="sg-briefing-sep" aria-hidden="true"></span>
      <button type="button" data-sg-briefing-jump><?php echo sg_icon('folder'); ?>往期</button>
    </div>
  </div>
  <div class="sg-briefing-body">
    <?php foreach ($sgBriefing as $sgIndex => $sgItem) : ?>
    <div class="sg-briefing-slide<?php echo $sgIndex === 0 ? ' is-active' : ''; ?>" data-sg-briefing-slide>
      <img src="<?php echo sg_media(sg_value($sgItem, 'cover', ''), sg_theme_url('dist/img/cover-default.svg')); ?>" alt="" loading="lazy">
      <span class="sg-veil"></span>
      <div class="sg-briefing-body-inner">
        <div>
          <div class="sg-briefing-date">
            <span><?php echo sg_value($sgItem, 'badge', 'DAILY SIGNAL'); ?></span>
            <span><?php echo sg_value($sgItem, 'date', ''); ?></span>
          </div>
          <span class="sg-briefing-label"><?php echo sg_value($sgItem, 'label', '每日拾光'); ?></span>
          <span class="sg-briefing-title-main"><?php echo sg_value($sgItem, 'title', ''); ?></span>
          <span class="sg-briefing-desc"><?php echo sg_value($sgItem, 'desc', ''); ?></span>
        </div>
        <div class="sg-briefing-foot">
          <div>
            <?php $sgMeta = $sgItem['meta'] ?? []; ?>
            <?php if (is_array($sgMeta)) : ?>
              <?php foreach ($sgMeta as $sgMetaItem) : ?><span><?php echo sg_e((string) $sgMetaItem); ?></span><?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="sg-briefing-pager">
            <button type="button" data-sg-briefing-prev aria-label="上一期"><?php echo sg_icon('chevron-left'); ?></button>
            <span data-sg-briefing-index><?php echo $sgIndex + 1; ?>/<?php echo count($sgBriefing); ?></span>
            <button type="button" data-sg-briefing-next aria-label="下一期"><?php echo sg_icon('chevron-right'); ?></button>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
