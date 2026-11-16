<?php
/**
 * 右侧悬浮按钮组 + 顶部胶囊加载条（对齐 Fomalhaut #rightside 与 pace 效果，AI 助手不加）。
 * DOM 挂点：[data-sg-pace] [data-sg-rightside] [data-sg-rightside-config]
 *           [data-sg-go-up] [data-sg-go-up-percent] [data-sg-go-down]
 * 昼夜 / 阅读模式按钮复用 core.ts 的 data-sg-theme-toggle / data-sg-readmode-toggle 挂点。
 */
?>
<div class="sg-pace" data-sg-pace aria-hidden="true"><span class="sg-pace-progress"></span></div>
<div class="sg-rightside" data-sg-rightside>
  <div class="sg-rightside-hidden" data-sg-rightside-hidden>
    <button class="sg-rightside-btn" type="button" data-sg-theme-toggle aria-label="切换昼夜模式">
      <?php echo sg_icon('sun', 'sg-i-sun'); ?>
      <?php echo sg_icon('moon', 'sg-i-moon'); ?>
    </button>
    <?php if ($this->is('post') || $this->is('page')) : ?>
    <button class="sg-rightside-btn" type="button" data-sg-readmode-toggle aria-label="切换阅读模式" aria-pressed="false">
      <?php echo sg_icon('book-open'); ?>
    </button>
    <?php endif; ?>
  </div>
  <button class="sg-rightside-btn" type="button" data-sg-rightside-config aria-label="展开设置" aria-expanded="false">
    <?php echo sg_icon('settings'); ?>
  </button>
  <button class="sg-rightside-btn sg-go-up" type="button" data-sg-go-up aria-label="回到顶部">
    <?php echo sg_icon('arrow-up'); ?>
    <span class="sg-go-up-percent" data-sg-go-up-percent>0<span>%</span></span>
  </button>
  <button class="sg-rightside-btn" type="button" data-sg-go-down aria-label="直达底部">
    <?php echo sg_icon('arrow-down'); ?>
  </button>
</div>