<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="404">
  <section class="sg-glass sg-notfound">
    <span class="sg-notfound-code">404</span>
    <h1>这片天空下好像什么都没有</h1>
    <p>链接可能已经失效，或者页面被移到了别处。不如回到首页重新出发。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
      <a class="sg-btn sg-btn--ghost" href="<?php echo sg_e(sg_page_url('archives')); ?>"><?php echo sg_icon('folder'); ?>浏览全部归档</a>
    </div>
  </section>
</main>
<?php $this->need('footer.php'); ?>
