<?php
/**
 * 项目
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');
$sgProjects = sg_json('sgProjects', 'projects', []);
$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '写过的代码、跑过的实验与还没做完的想法。'),
    'search'      => true,
    'back'        => true,
    'center'      => true,
    'placeholder' => '搜索项目名称或标签...',
];
?>
<main class="sg-main sg-main--pad" data-sg-page="projects">
  <?php $this->need('partials/page-head.php'); ?>

  <div class="sg-card-grid" data-sg-filter-grid>
    <?php foreach ($sgProjects as $sgProject) : ?>
    <?php if (!is_array($sgProject)) { continue; } ?>
    <article class="sg-glass sg-project" data-sg-filter-item data-keywords="<?php echo sg_value($sgProject, 'name', '') . ' ' . implode(' ', array_map('strval', (array) ($sgProject['tags'] ?? []))); ?>">
      <div class="sg-project-icon"><?php echo sg_value($sgProject, 'icon', '✦'); ?></div>
      <h3><?php echo sg_value($sgProject, 'name', ''); ?></h3>
      <p><?php echo sg_value($sgProject, 'description', ''); ?></p>
      <div class="sg-tags">
        <?php foreach ((array) ($sgProject['tags'] ?? []) as $sgTag) : ?><span><?php echo sg_e((string) $sgTag); ?></span><?php endforeach; ?>
      </div>
      <?php if (sg_value($sgProject, 'githubUrl', '') !== '') : ?>
      <a class="sg-back" href="<?php echo sg_value($sgProject, 'githubUrl', '#'); ?>" target="_blank" rel="noopener noreferrer">查看源码 <?php echo sg_icon('chevron-right'); ?></a>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if (!$sgProjects) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有项目</h1>
    <p>到后台「设置外观 → 项目 JSON」添加，字段：name / description / icon / tags / githubUrl。</p>
  </div>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
