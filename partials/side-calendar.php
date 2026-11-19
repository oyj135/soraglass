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
      <td class="sg-hcal-cell<?php echo $sgCalD === $sgCalToday ? ' is-today' : ''; ?>"<?php echo $sgCalD === $sgCalToday ? ' aria-current="date"' : ''; ?>><?php echo $sgCalD; ?></td>
      <?php endif; ?>
      <?php endfor; ?>
    </tr>
    <?php endfor; ?>
    </tbody>
  </table>
  <div class="sg-hcal-foot"><span class="sg-hcal-today">今天 · <?php echo $sgCalMonth; ?> 月 <?php echo $sgCalToday; ?> 日</span><span class="sg-hcal-week">星期<?php echo $sgCalWeekNames[(int) $sgCalNow->format('w')]; ?></span></div>
</section>
