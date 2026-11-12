<?php
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<main class="sg-main sg-main--pad" data-sg-page="404">
  <section class="sg-glass sg-notfound">
    <span class="sg-notfound-code">404</span>
    <h1>这片天空下好像什么都没有</h1>
    <p>链接可能已经失效，或者页面被移到了别处。不如回到首页重新出发。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
      <a class="sg-btn sg-btn--ghost" href="<?php echo sg_e(sg_page_url('archives')); ?>"><?php echo sg_icon('folder'); ?>浏览全部归档</a>
    </div>
    <form class="sg-search sg-search--sm" role="search" method="get" action="<?php echo sg_e($sgHome); ?>" style="margin-top:2rem;max-width:26rem">
      <span class="sg-search-icon"><?php echo sg_icon('search'); ?></span>
      <input class="sg-search-input" type="search" name="s" placeholder="也许你想找的是..." aria-label="站内搜索">
    </form>
  </section>
</main>
<?php $this->need('footer.php'); ?>
