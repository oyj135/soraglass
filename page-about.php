<?php
/**
 * 关于
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgHead = [
    'title'  => (string) $this->title,
    'sub'    => sg_page_sub($this, '关于博主与这个站点'),
    'search' => false,
    'back'   => true,
];
?>
<main class="sg-main sg-main--narrow" data-sg-page="about">
  <?php $this->need('partials/page-head.php'); ?>
  <article class="sg-glass sg-article">
    <div class="sg-article-body">
      <div class="sg-prose" data-sg-article><?php $this->content(); ?></div>
    </div>
  </article>
</main>
<?php $this->need('footer.php'); ?>
