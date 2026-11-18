<?php
/**
 * 首页侧栏 · 轻量日历卡（参考 Fomalhaut card-calendar 的形态，不引入农历/节气算法）。
 * 服务端生成当月公历月历，今天高亮；周日起始，与参考站一致。
 * 纯静态输出，无 JS、无外部脚本，PJAX 换页时随 sg-main 一起刷新。
 */
$sgCalNow = new DateTimeImmutable('now');
$sgCalYear = (int) $sgCalNow->format('Y');
$sgCalMonth = (int) $sgCalNow->format('n');
$sgCalToday = (int) $sgCalNow->format('j');
$sgCalDaysInMonth = (int) $sgCalNow->format('t');
$sgCalFirstWeekday = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $sgCalYear, $sgCalMonth)))->format('w'); // 0=周日
$sgCalWeekNames = ['日', '一', '二', '三', '四', '五', '六'];
?>
<section class="sg-hcard sg-hcal" aria-label="本月日历">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('calendar'); ?>日历</span>
    <span class="sg-hcal-ym"><?php echo $sgCalYear; ?> 年 <?php echo $sgCalMonth; ?> 月</span>
  </div>
  <div class="sg-hcal-grid" role="grid" aria-label="<?php echo $sgCalYear; ?>年<?php echo $sgCalMonth; ?>月">
    <?php foreach ($sgCalWeekNames as $sgCalW) : ?>
    <span class="sg-hcal-wd" role="columnheader"><?php echo $sgCalW; ?></span>
    <?php endforeach; ?>
    <?php for ($sgCalI = 0; $sgCalI < $sgCalFirstWeekday; $sgCalI++) : ?>
    <span class="sg-hcal-cell is-empty" aria-hidden="true"></span>
    <?php endfor; ?>
    <?php for ($sgCalD = 1; $sgCalD <= $sgCalDaysInMonth; $sgCalD++) : ?>
    <span class="sg-hcal-cell<?php echo $sgCalD === $sgCalToday ? ' is-today' : ''; ?>"<?php echo $sgCalD === $sgCalToday ? ' aria-current="date"' : ''; ?>><?php echo $sgCalD; ?></span>
    <?php endfor; ?>
  </div>
  <div class="sg-hcal-foot"><span class="sg-hcal-today">今天 · <?php echo $sgCalMonth; ?> 月 <?php echo $sgCalToday; ?> 日</span><span class="sg-hcal-week">星期<?php echo $sgCalWeekNames[(int) $sgCalNow->format('w')]; ?></span></div>
</section>
