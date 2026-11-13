<?php
/**
 * 关于
 *
 * @package custom
 *
 * 结构与源站 components/AboutClient.tsx 一致：
 * 横幅图（底部渐变）→ 上提头像 → 「关于我」+ 英文副标题 → 分段控件 → 细线 → 内容。
 *
 * 「研究动态」面板 = 近一年贡献热力图 + 动态时间线，数据全部来自本站数据库：
 *   sg_post_heatmap()  按 table.contents.created 自己分桶（不依赖 DB 日期函数）
 *   sg_activity_feed() 最近文章 + 分类缩略名 → 文章 / 杂谈 / 说说 三种动作
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgAuthor = sg_opt('sgAuthorName', '博主');
$sgAvatar = sg_opt('sgAvatarUrl', sg_theme_url('dist/img/avatar.svg'));
$sgBanner = sg_field($this, 'cover', sg_opt('sgDefaultCover', sg_placeholder(1015, 1600, 600)));
$sgHome   = rtrim((string) $this->options->siteUrl, '/') . '/';

$sgHeat = sg_post_heatmap();
$sgFeed = sg_activity_feed(12);
$sgContrib = array_sum($sgHeat);

// 一年热力图按「周」切片：从 52 周前的周日开始，每 7 天一周，DOM 顺序即列顺序
$sgToday = strtotime(date('Y-m-d'));
$sgStart = strtotime('-' . (364 + (int) date('w')) . ' days', $sgToday);
$sgWeeks = [];
$sgWeek = [];
for ($sgDay = $sgStart; $sgDay <= $sgToday; $sgDay += 86400) {
    $sgWeek[] = $sgDay;
    if (count($sgWeek) === 7) {
        $sgWeeks[] = $sgWeek;
        $sgWeek = [];
    }
}
if ($sgWeek) {
    $sgWeeks[] = array_pad($sgWeek, 7, null);
}
?>
<main id="sg-main" class="sg-main sg-main--narrow" data-sg-page="about" data-sg-grid>
  <article class="sg-glass sg-about" data-sg-spotlight>

    <div class="sg-about-banner">
      <img src="<?php echo sg_e($sgBanner); ?>" alt="<?php echo sg_e((string) $this->title); ?>">
      <div class="sg-about-veil"></div>
    </div>

    <div class="sg-about-inner">
      <div class="sg-about-avatar">
        <img src="<?php echo sg_e($sgAvatar); ?>" alt="<?php echo sg_e($sgAuthor); ?>">
      </div>

      <div class="sg-about-head">
        <div class="sg-about-title">
          <h1><?php echo sg_e((string) $this->title); ?></h1>
          <p class="sg-about-eyebrow">Hello World, I'm <?php echo sg_e($sgAuthor); ?></p>
        </div>

        <div class="sg-seg" data-sg-seg="tab" role="tablist" aria-label="关于页分区">
          <button class="sg-seg-btn is-active" type="button" role="tab" data-sg-seg-btn="intro" aria-selected="true">自我介绍</button>
          <button class="sg-seg-btn" type="button" role="tab" data-sg-seg-btn="activity" aria-selected="false">研究动态</button>
        </div>
      </div>

      <hr class="sg-about-rule">

      <div class="sg-about-panel" data-sg-seg-panel="tab:intro">
        <div class="sg-prose" data-sg-article><?php $this->content(); ?></div>
        <?php if (trim((string) $this->content) === '') : ?>
        <p class="sg-about-empty">这个页面还没有正文。到后台「管理 → 独立页面」编辑它，内容会原样渲染在这里。</p>
        <?php endif; ?>
      </div>

      <div class="sg-about-panel" data-sg-seg-panel="tab:activity" hidden>
        <section class="sg-heat">
          <h3 class="sg-heat-title">
            <?php echo sg_icon('activity'); ?>
            <span class="sg-heat-count"><?php echo (int) $sgContrib; ?></span> contributions in the last year
          </h3>

          <div class="sg-heat-scroll" data-sg-heat>
            <div class="sg-heat-inner">
              <div class="sg-heat-months" aria-hidden="true">
                <?php foreach ($sgWeeks as $sgWeekDays) : ?>
                <?php $sgFirst = $sgWeekDays[0]; ?>
                <span><?php echo ($sgFirst !== null && (int) date('j', $sgFirst) <= 7) ? date('M', $sgFirst) : ''; ?></span>
                <?php endforeach; ?>
              </div>

              <div class="sg-heat-body">
                <div class="sg-heat-weekdays" aria-hidden="true">
                  <span></span><span>Mon</span><span></span><span>Wed</span><span></span><span>Fri</span><span></span>
                </div>

                <div class="sg-heat-grid" role="img" aria-label="近一年发文热力图，共 <?php echo (int) $sgContrib; ?> 篇">
                  <?php foreach ($sgWeeks as $sgWeekDays) : ?>
                    <?php foreach ($sgWeekDays as $sgTs) : ?>
                      <?php if ($sgTs === null) : ?>
                        <i class="sg-heat-cell is-blank" aria-hidden="true"></i>
                      <?php else : ?>
                        <?php
                          $sgKey = date('Y-m-d', $sgTs);
                          $sgNum = (int) ($sgHeat[$sgKey] ?? 0);
                          $sgLevel = $sgNum === 0 ? 0 : ($sgNum === 1 ? 1 : ($sgNum === 2 ? 2 : ($sgNum === 3 ? 3 : 4)));
                        ?>
                        <i class="sg-heat-cell" data-level="<?php echo $sgLevel; ?>" title="<?php echo sg_e($sgKey . '：' . $sgNum . ' 篇'); ?>"></i>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <div class="sg-heat-legend">
            <span>Less</span>
            <i class="sg-heat-cell" data-level="0"></i>
            <i class="sg-heat-cell" data-level="1"></i>
            <i class="sg-heat-cell" data-level="2"></i>
            <i class="sg-heat-cell" data-level="3"></i>
            <i class="sg-heat-cell" data-level="4"></i>
            <span>More</span>
          </div>
        </section>

        <?php if ($sgFeed) : ?>
        <div class="sg-timeline">
          <?php foreach ($sgFeed as $sgItem) : ?>
          <?php
            $sgKind = $sgItem['kind'];
            $sgIsMoment = $sgKind === '说说';
            $sgTone = $sgIsMoment ? 'is-pink' : ($sgKind === '杂谈' ? 'is-purple' : 'is-indigo');
          ?>
          <a class="sg-timeline-node" href="<?php echo sg_e($sgItem['url']); ?>">
            <span class="sg-timeline-dot" aria-hidden="true"></span>
            <span class="sg-timeline-card">
              <span class="sg-timeline-who">
                <img class="sg-timeline-avatar" src="<?php echo sg_e($sgAvatar); ?>" alt="">
                <span class="sg-timeline-names">
                  <b><?php echo sg_e($sgAuthor); ?></b>
                  <em class="<?php echo $sgTone; ?>"><?php echo sg_e($sgItem['action'] . ' ' . $sgKind); ?></em>
                </span>
              </span>

              <?php if (!$sgIsMoment && $sgItem['title'] !== '') : ?>
              <span class="sg-timeline-sep" aria-hidden="true"></span>
              <span class="sg-timeline-title">《<?php echo sg_e($sgItem['title']); ?>》</span>
              <?php else : ?>
              <span class="sg-timeline-title is-empty"></span>
              <?php endif; ?>

              <time class="sg-timeline-time" datetime="<?php echo sg_e($sgItem['date']); ?>"><?php echo sg_e($sgItem['date']); ?></time>
            </span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else : ?>
        <p class="sg-about-empty">还没有发布过文章，这里会显示最近 12 条动态。</p>
        <?php endif; ?>
      </div>
    </div>
  </article>
</main>
<?php $this->need('footer.php'); ?>
