<?php
/**
 * 首页侧栏 · 小站资讯卡（对齐 Fomalhaut card_webinfo 新版）：
 * KPI 总字数 / 篇均字 / 友链 + 时间条（已运行 · 距上次发文 · 更新）+ 年度产出柱状图 + 访客位。
 * 数据全部来自 lib/config.php 的 sg_webinfo()；访客数 / 总访问量由页脚 busuanzi 镜像异步回写。
 */
$sgWi = sg_webinfo();
$sgWiMax = 1;
foreach ($sgWi['bars'] as $sgWiBar) {
    if ($sgWiBar['count'] > $sgWiMax) {
        $sgWiMax = $sgWiBar['count'];
    }
}
$sgWiFriendsLink = sg_page_exists('friends') ? sg_page_url('friends') : '';
?>
<section class="sg-hcard sg-hinfo" aria-label="小站资讯">
  <div class="sg-hcard-head">
    <span class="sg-hcard-title"><?php echo sg_icon_color('chart'); ?>小站资讯</span>
  </div>
  <div class="sg-hinfo-kpi">
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo sg_e($sgWi['words']); ?></span><span class="sg-hinfo-label">总字数</span></div>
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo sg_e($sgWi['avg']); ?></span><span class="sg-hinfo-label">篇均字</span></div>
    <?php if ($sgWiFriendsLink !== '') : ?>
    <a class="sg-hinfo-kpi-item" href="<?php echo sg_e($sgWiFriendsLink); ?>"><span class="sg-hinfo-num"><?php echo (int) $sgWi['friends']; ?></span><span class="sg-hinfo-label">友链</span></a>
    <?php else : ?>
    <div class="sg-hinfo-kpi-item"><span class="sg-hinfo-num"><?php echo (int) $sgWi['friends']; ?></span><span class="sg-hinfo-label">友链</span></div>
    <?php endif; ?>
  </div>
  <div class="sg-hinfo-time">
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">已运行</span><b><?php echo (int) $sgWi['runDays']; ?> 天</b></span>
    <span class="sg-hinfo-time-sep" aria-hidden="true">·</span>
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">距上次发文</span><b><?php echo (int) $sgWi['sincePost']; ?> 天</b></span>
    <span class="sg-hinfo-time-sep" aria-hidden="true">·</span>
    <span class="sg-hinfo-time-item"><span class="sg-hinfo-time-k">更新</span><b><?php echo sg_e($sgWi['update']); ?></b></span>
  </div>
  <div class="sg-hinfo-chart">
    <div class="sg-hinfo-chart-head">
      <span>年度产出</span>
      <span class="sg-hinfo-chart-sub">共 <?php echo (int) $sgWi['writeDays']; ?> 个写作日</span>
    </div>
    <div class="sg-hinfo-bars">
      <?php foreach ($sgWi['bars'] as $sgWiBar) : ?>
      <?php $sgWiH = $sgWiBar['count'] > 0 ? max(12, (int) round($sgWiBar['count'] / $sgWiMax * 68)) : 4; ?>
      <div class="sg-hinfo-bar">
        <span class="sg-hinfo-bar-n"><?php echo (int) $sgWiBar['count']; ?></span>
        <span class="sg-hinfo-bar-track"><i style="height:<?php echo $sgWiH; ?>%<?php echo $sgWiBar['count'] > 0 ? '' : ';opacity:.25'; ?>"></i></span>
        <span class="sg-hinfo-bar-y"><?php echo (int) $sgWiBar['year']; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php /* 访客位（2026-11-16 第三轮，对齐 Fomalhaut .wi-visit）：数字由 busuanzi 镜像异步回写 */ ?>
  <div class="sg-hinfo-visit">
    <div class="sg-hinfo-visit-item">
      <span class="sg-hinfo-visit-num" id="busuanzi_value_site_uv">—</span>
      <span class="sg-hinfo-visit-label">本站访客数</span>
    </div>
    <div class="sg-hinfo-visit-item">
      <span class="sg-hinfo-visit-num" id="busuanzi_value_site_pv">—</span>
      <span class="sg-hinfo-visit-label">本站总访问量</span>
    </div>
  </div>
</section>