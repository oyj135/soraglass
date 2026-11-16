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

    <p class="sg-footer-copy">&copy; <?php echo date('Y'); ?> <?php echo sg_e($sgName); ?> · 保留所有权利</p>
    <p class="sg-footer-powered">Powered by Typecho · Theme Soraglass 空璃</p>
  </div>
</footer>

<?php $this->need('partials/toolbox.php'); ?>
<?php $this->need('partials/rightside.php'); ?>

<audio data-sg-audio preload="metadata"></audio>
<div class="sg-toast" data-sg-toast role="status" aria-live="polite"></div>
<?php $this->footer(); ?>
</body>
</html>
