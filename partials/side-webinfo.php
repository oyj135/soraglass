<?php
/**
 * 首页侧栏 · 小站资讯卡（参考 Fomalhaut card_webinfo 的 KPI + 时间形态）。
 * 数据全部复用现有 PHP 函数，不新增查询逻辑、不引入访问量统计脚本：
 *   sg_total_words() 总字数 / sg_stats() 文章·碎语·照片 / sg_uptime() 运行天数 / sg_year_posts() 本年产出。
 */
$sgWiWords = sg_words_format(sg_total_words());
$sgWiStats = sg_stats();
$sgWiUptime = sg_uptime();
$sgWiYear = sg_year_posts();
?>
<section class="sg-hcard sg-hinfo" aria-label="小站资讯">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon('activity'); ?>小站资讯</span>
  </div>
  <div class="sg-hinfo-kpi">
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo sg_e($sgWiWords); ?></span><span class="sg-hinfo-label">总字数</span></div>
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo sg_e($sgWiStats['posts']); ?></span><span class="sg-hinfo-label">文章</span></div>
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo sg_e($sgWiStats['photos']); ?></span><span class="sg-hinfo-label">照片</span></div>
  </div>
  <div class="sg-hinfo-time">
    <div class="sg-hinfo-time-item"><span>已运行</span><b><?php echo (int) $sgWiUptime['days']; ?> 天</b></div>
    <div class="sg-hinfo-time-item"><span>本年产出</span><b><?php echo (int) $sgWiYear; ?> 篇</b></div>
    <div class="sg-hinfo-time-item"><span>碎语</span><b><?php echo sg_e($sgWiStats['notes']); ?> 条</b></div>
  </div>
</section>
