<?php
/**
 * 灵境 —— three.js 3D 舞台，按需动态加载，仅本页下载 three 分包。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '在空璃的天空下，漂浮着一座会呼吸的星之树'),
    'back'   => true,
    'center' => true,
];
?>
<main class="sg-main sg-main--pad" data-sg-page="tree">
  <?php $this->need('partials/page-head.php'); ?>

  <section class="sg-glass sg-tree-stage">
    <div class="sg-tree-canvas" data-sg-tree></div>
    <div class="sg-tree-overlay">
      <span class="sg-tree-chip">WEBGL · 实时渲染</span>
      <p class="sg-hint">场景由 three.js 动态加载，仅在本页请求；低配设备与「减少动态效果」下自动跳过，不影响其它内容。</p>
    </div>
  </section>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-top:2.5rem"><?php $this->content(); ?></div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
