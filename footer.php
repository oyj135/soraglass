<?php
/**
 * 页脚：状态行（运行时长 + 备案号）→ 分隔线 → 两行版权。
 *
 * 2026-11-12 第八轮（用户给参考截图后回滚）：
 * - 恢复「系统已稳定运行：N 天 N 小时」这一行与 `sg_uptime()`；
 * - 恢复 `sgUptimeSince`（建站日期）设置项，留空时自动取最早一篇文章的时间；
 * - 时长由**服务端**算好直接输出，不再引入 dashboard.ts 那套每秒 setInterval；
 * - 时钟区块仍然不恢复（用户上一轮明确要求删除，本轮也没有加回来）；
 * - 布局按截图：黑色状态行（绿点 + 文案 + 靛蓝时长 + 右侧备案号）→ 1px 分隔线 → 居中两行版权。
 */
$sgUptime = sg_uptime();
$sgIcp = trim((string) sg_opt('sgIcp', ''));
$sgName = trim((string) $this->options->title);
if ($sgName === '') {
    $sgName = 'Soraglass';
}
?>
<div class="sg-footer-zone">
  <footer class="sg-footer-wrap">
    <div class="sg-footer">
    <div class="sg-footer-status">
      <span class="sg-footer-run">
        <i class="sg-footer-dot" aria-hidden="true"></i>
        <span class="sg-footer-label">系统已稳定运行：</span>
        <b class="sg-footer-uptime"><?php echo sg_e($sgUptime['text']); ?></b>
      </span>
      <?php if ($sgIcp !== '') : ?>
      <span class="sg-footer-icp"><?php echo sg_e($sgIcp); ?></span>
      <?php endif; ?>
    </div>

    <hr class="sg-footer-rule">

    <p class="sg-footer-copy">&copy; <?php echo date('Y'); ?> <?php echo sg_e($sgName); ?></p>
    <p class="sg-footer-powered">Powered by Typecho · Theme Soraglass 空璃</p>
  </div>
</footer>
</div>

<?php /* 2026-11-21：左下音乐馆圆钮已并入 partials/rightside.php 展开组， toolbox.php 下线。 */ ?>
<?php $this->need('partials/rightside.php'); ?>

<?php /* 右缘墨龙滚动进度（2026-11-22 第五十五轮，小猫换墨龙，交互逻辑不变）：
   竖绳高度 = 滚动进度 × 0.9 × 视口高，龙挂绳尾；点绳/点龙回顶部；≤768px 隐藏。
   素材：vectorizer/龙-卡通挂饰.jpg（AI 重绘的卡通挂饰风彩龙）去底裁边后 128px PNG，
   昼夜同图。原银灰墨龙 SVG 保留在 vectorizer/ 作母版。 */ ?>
<div class="sg-neko-rope" data-sg-neko-rope aria-hidden="true"></div>
<div class="sg-neko" data-sg-neko aria-hidden="true">
  <img src="<?php echo sg_e(sg_theme_url('dist/img/neko.png?v=20261122a')); ?>" alt="" width="64" height="64">
  <span class="sg-neko-msg" data-sg-neko-msg></span>
</div>

<audio data-sg-audio preload="metadata"></audio>
<div class="sg-toast" data-sg-toast role="status" aria-live="polite"></div>
<?php /* 访客统计（2026-11-16 第三轮，对齐 Fomalhaut 的 busuanzi 方案）：
   异步加载计数镜像，回写 #busuanzi_value_site_uv / site_pv / page_pv；
   加载失败时占位「—」保留，不阻塞页面也不伪造数字。
   2026-11-19：加 data-sg-counter 标记 —— PJAX 换页后 stats-cache.ts 会摘掉它重新注入，
   否则文章阅读量永远停在「—」（vercount 只在加载时扫一次 DOM）。 */ ?>
<script async data-sg-counter src="https://events.vercount.one/js"></script>
<?php $this->footer(); ?>
</body>
</html>
