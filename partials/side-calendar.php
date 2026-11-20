<?php
/**
 * 首页日期卡首帧。结构对齐源站 aside-calendar.js 的 renderToday()：
 *   左栏 44%（周次 星期 → 大日期 → 年月 第N天 → 农历）+ 右栏当月月历
 *   → 底部一条虚线附加行（节气 · 日出日落）→ 可选天气块。
 * sidebar-data.ts 负责农历/节气、跨天刷新及天气。
 * 卡片标题行是 Soraglass 自有语言（源站两卡隐藏 item-headline），已与用户确认保留。
 */
$sgSideCfg = sg_sidebar_cfg();
$sgCalNow = sg_sidebar_date()['now'];
$sgCalYear = (int) $sgCalNow->format('Y');
$sgCalMonth = (int) $sgCalNow->format('n');
$sgCalToday = (int) $sgCalNow->format('j');
$sgCalDaysInMonth = (int) $sgCalNow->format('t');
$sgCalFirstWeekday = (int) $sgCalNow->setDate($sgCalYear, $sgCalMonth, 1)->format('w');
$sgCalWeekNames = ['日', '一', '二', '三', '四', '五', '六'];
$sgCalWeekday = (int) $sgCalNow->format('w');
$sgCalTitle = $sgCalYear . '年' . $sgCalMonth . '月' . $sgCalToday . '日 星期' . $sgCalWeekNames[$sgCalWeekday];
?>
<section class="sg-hcard sg-hcal" aria-label="本月日历" data-sg-calendar title="<?php echo sg_e($sgCalTitle); ?>">
  <noscript><style>[data-sg-calendar-lunar], [data-sg-calendar-term], [data-sg-weather] { display: none; }</style></noscript>
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('calendar'); ?>日历</span>
  </div>
  <div class="sg-hcal-main">
    <div class="sg-hcal-left<?php echo $sgSideCfg['detail'] ? '' : ' is-slim'; ?>">
      <?php if ($sgSideCfg['detail']) : ?>
      <span class="sg-hcal-week" data-sg-calendar-week>第<?php echo $sgCalNow->format('W'); ?>周 周<?php echo $sgCalWeekNames[$sgCalWeekday]; ?></span>
      <span class="sg-hcal-day" data-sg-calendar-day><?php echo sprintf('%02d', $sgCalToday); ?></span>
      <?php endif; ?>
      <span class="sg-hcal-solar"><span data-sg-calendar-ym><?php echo $sgCalYear; ?>年<?php echo $sgCalMonth; ?>月</span><?php if ($sgSideCfg['detail']) : ?> <span data-sg-calendar-doy>第<?php echo (int) $sgCalNow->format('z') + 1; ?>天</span><?php endif; ?></span>
      <?php if ($sgSideCfg['detail']) : ?><span class="sg-hcal-lunar" data-sg-calendar-lunar>农历加载中</span><?php endif; ?>
    </div>
    <table class="sg-hcal-grid" aria-label="<?php echo $sgCalYear; ?>年<?php echo $sgCalMonth; ?>月">
      <thead><tr>
      <?php foreach ($sgCalWeekNames as $sgCalW) : ?>
      <th class="sg-hcal-wd" scope="col"><?php echo $sgCalW; ?></th>
      <?php endforeach; ?>
      </tr></thead>
      <tbody>
      <?php for ($sgCalRow = 0; $sgCalRow < (int) ceil(($sgCalFirstWeekday + $sgCalDaysInMonth) / 7); $sgCalRow++) : ?>
      <tr>
        <?php for ($sgCalCol = 0; $sgCalCol < 7; $sgCalCol++) : ?>
        <?php $sgCalD = $sgCalRow * 7 + $sgCalCol - $sgCalFirstWeekday + 1; ?>
        <?php if ($sgCalD < 1 || $sgCalD > $sgCalDaysInMonth) : ?>
        <td class="sg-hcal-cell is-empty"></td>
        <?php else : ?>
        <td class="sg-hcal-cell<?php echo $sgCalD === $sgCalToday ? ' is-today' : ''; ?>"<?php echo $sgCalD === $sgCalToday ? ' aria-current="date"' : ''; ?>><span><?php echo $sgCalD; ?></span></td>
        <?php endif; ?>
        <?php endfor; ?>
      </tr>
      <?php endfor; ?>
      </tbody>
    </table>
  </div>
  <div class="sg-hcal-extra" data-sg-calendar-extra hidden>
    <?php if ($sgSideCfg['detail']) : ?><span class="sg-hcal-term" data-sg-calendar-term></span><?php endif; ?>
    <span class="sg-hcal-sun" data-sg-sun hidden><span data-sg-sun-rise></span><span data-sg-sun-set></span></span>
  </div>
  <?php if ($sgSideCfg['weather']) : ?>
  <div class="sg-hweather" data-sg-weather data-state="loading">
    <div class="sg-hweather-main">
      <span class="sg-hweather-icon" aria-hidden="true">
        <svg data-sg-weather-icon="sun" hidden viewBox="0 0 24 24"><circle cx="12" cy="12" r="4" fill="var(--sg-icon-star,#dda844)"/><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M19 5l-1.5 1.5m-11 11L5 19" fill="none" stroke="var(--sg-icon-star,#dda844)" stroke-width="2"/></svg>
        <svg data-sg-weather-icon="cloud" hidden viewBox="0 0 24 24"><path d="M6 18a4 4 0 0 1-1-7.9A6.5 6.5 0 0 1 17.7 9a4.5 4.5 0 0 1 .3 9H6Z" fill="var(--sg-accent-soft)"/></svg>
        <svg data-sg-weather-icon="rain" hidden viewBox="0 0 24 24"><path d="M5 14a4 4 0 0 1 0-8 6 6 0 0 1 11 1 4 4 0 0 1 2 7Z" fill="var(--sg-accent-soft)"/><path d="m7 17-1 3m6-3-1 3m6-3-1 3" stroke="var(--sg-accent)" stroke-width="2" stroke-linecap="round"/></svg>
        <svg data-sg-weather-icon="snow" hidden viewBox="0 0 24 24"><path d="M12 2v20M3 7l18 10M3 17 21 7m-12-3 3 3 3-3M9 20l3-3 3 3" fill="none" stroke="var(--sg-accent-soft)" stroke-width="2" stroke-linecap="round"/></svg>
        <svg data-sg-weather-icon="fog" hidden viewBox="0 0 24 24"><path d="M4 7h16M2 12h20M5 17h14" stroke="var(--sg-muted)" stroke-width="3" stroke-linecap="round"/></svg>
      </span>
      <span class="sg-hweather-temp" data-sg-weather-temp>--</span>
      <span class="sg-hweather-place">
        <b class="sg-hweather-city" data-sg-weather-main>天气加载中</b>
        <span class="sg-hweather-cond" data-sg-weather-cond></span>
      </span>
    </div>
    <p class="sg-hweather-meta" data-sg-weather-meta>正在获取当地天气</p>
    <div class="sg-hweather-source"><a href="https://www.qweather.com/" target="_blank" rel="noopener noreferrer">和风天气</a><span data-sg-weather-attributions></span></div>
  </div>
  <?php endif; ?>
</section>
