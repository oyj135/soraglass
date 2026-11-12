<?php
/**
 * 页脚：仪表盘模块已下线，原有内容整块迁移到这里。
 *
 * 2026-11-12 第六轮：此前是「need(partials/dashboard.php) 塞进 .sg-footer-panel，
 * 再往下接版权」——视觉上始终像「玻璃卡里又套了一张小卡」。现在仪表盘作为
 * 独立模块整体移除，时钟 / 运行时长 / 备案号以页脚行的形式直接写在本文件里，
 * 与下面的版权之间用一条分隔线隔开（是迁移内容，不是把两段文案揉进同一段）。
 *
 * 数据挂点保持原样（data-sg-clock / data-sg-uptime），
 * 仍由 src/scripts/modules/dashboard.ts 驱动，不需要改前端逻辑。
 */
$sgUp = sg_uptime();
$sgIcp = (string) sg_opt('sgIcp', '');
?>
<footer class="sg-footer-wrap">
  <div class="sg-glass sg-footer">
    <!-- ① 时钟：仪表盘原样迁移，仍是页脚的第一行 -->
    <div class="sg-footer-clock" data-sg-clock>00:00:00</div>

    <!-- ② 运行时长 + 备案号：原来在仪表盘右侧，现在居中排在时钟下方 -->
    <div class="sg-footer-info">
      <span class="sg-footer-status">
        <i class="sg-footer-dot" aria-hidden="true"></i>
        <span>系统已稳定运行：<b data-sg-uptime data-since="<?php echo (int) $sgUp['since']; ?>"><?php echo (int) $sgUp['days']; ?> 天 <?php echo (int) $sgUp['hours']; ?> 小时</b></span>
      </span>
      <?php if ($sgIcp !== '') : ?>
      <span class="sg-footer-icp"><?php echo sg_e($sgIcp); ?></span>
      <?php endif; ?>
    </div>

    <hr class="sg-footer-rule">

    <!-- ③ 版权：与上面迁移来的内容分属两块，不再共用同一张子卡片 -->
    <div class="sg-footer-legal">
      <p>&copy; <?php echo date('Y'); ?> <?php echo sg_e((string) $this->options->title); ?> · 保留所有权利</p>
      <p>Powered by Typecho · Theme Soraglass 空璃</p>
    </div>
  </div>
</footer>

<?php $this->need('partials/toolbox.php'); ?>

<audio data-sg-audio preload="metadata"></audio>
<div class="sg-toast" data-sg-toast role="status" aria-live="polite"></div>
<?php $this->footer(); ?>
</body>
</html>
