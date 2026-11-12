<?php
$sgPet = sg_opt('sgPet', 'none');
$sgFloating = sg_opt('sgFloatingPlayer', 'on') === 'on';
?>
<footer class="sg-footer-wrap">
  <?php $this->need('partials/dashboard.php'); ?>
  <div class="sg-footer">
    <p>&copy; <?php echo date('Y'); ?> <?php echo sg_e((string) $this->options->title); ?> · 保留所有权利</p>
    <p>Powered by Typecho · Theme Soraglass 空璃</p>
  </div>
</footer>

<?php $this->need('partials/toolbox.php'); ?>

<button class="sg-to-top" type="button" data-sg-totop aria-label="回到顶部"><?php echo sg_icon('arrow-up'); ?></button>

<?php if ($sgFloating) : ?>
<button class="sg-floating-player" type="button" data-sg-floating aria-label="播放/暂停">
  <img data-sg-float-cover src="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>" alt="" data-sg-fallback="<?php echo sg_e(sg_theme_url('dist/img/cover-default.svg')); ?>">
  <span data-sg-float-title>空璃音乐盒</span>
</button>
<?php endif; ?>

<?php if ($sgPet !== 'none') : ?>
<div class="sg-pet" aria-hidden="true">
  <img src="<?php echo sg_e(sg_theme_url('dist/img/' . ($sgPet === 'fish' ? 'cover-default.svg' : 'avatar.svg'))); ?>" alt="">
</div>
<?php endif; ?>

<audio data-sg-audio preload="metadata"></audio>
<div class="sg-toast" data-sg-toast role="status" aria-live="polite"></div>
<?php $this->footer(); ?>
</body>
</html>
