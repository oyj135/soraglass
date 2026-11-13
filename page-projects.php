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
$sgProjects = sg_projects();
$sgSourceLine = sg_github_source_line(count($sgProjects));

/* 项目卡的身份行（第三十一轮新增）：默认用站点作者的头像与名字——
   GitHub 头像（github.com/<user>.png）在国内机房经常连不上，一屏卡片全是空圆更难看。
   单个项目仍可在 JSON 里用 owner / avatar 覆写（比如收录别人的开源项目）。 */
$sgGhUser = trim((string) (sg_github_cfg()['user'] ?? ''));
$sgFallbackAvatar = sg_theme_url('dist/img/avatar.svg');
$sgDefOwner = $sgGhUser !== '' ? '@' . $sgGhUser : sg_opt('sgAuthorName', (string) $this->options->title);
$sgDefAvatar = sg_opt('sgAvatarUrl', $sgFallbackAvatar);

$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '写过的代码、跑过的实验与还没做完的想法。'),
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索项目名称、简介或技术栈...',
];
?>
<main id="sg-main" class="sg-main sg-main--pad" data-sg-page="projects">
  <?php sg_page_head($sgHead); ?>

  <?php if ($sgSourceLine !== '') : ?>
  <p class="sg-projects-source"><?php echo sg_icon('github'); ?><?php echo sg_e($sgSourceLine); ?></p>
  <?php endif; ?>

  <div class="sg-project-grid" data-sg-filter-grid>
    <?php foreach ($sgProjects as $sgProject) : ?>
    <?php if (!is_array($sgProject)) { continue; } ?>
    <?php
        $sgName = (string) sg_value($sgProject, 'name', '');
        $sgDesc = (string) sg_value($sgProject, 'description', '');
        $sgUrl = (string) sg_value($sgProject, 'githubUrl', '');
        $sgTags = (array) ($sgProject['tags'] ?? []);
        $sgMeta = (array) ($sgProject['meta'] ?? []);
        /* GitHub 层给的是字符串（'' / Fork / 已归档），手填 JSON 可能给数组，两种都收 */
        $sgBadgeRaw = $sgProject['badge'] ?? [];
        if (is_array($sgBadgeRaw)) {
            $sgBadge = array_values(array_filter(array_map('strval', $sgBadgeRaw), 'strlen'));
        } else {
            $sgBadge = trim((string) $sgBadgeRaw) === '' ? [] : [(string) $sgBadgeRaw];
        }
        $sgKeywords = trim((string) sg_value($sgProject, 'keywords', ''));
        if ($sgKeywords === '') {
            $sgKeywords = $sgName . ' ' . $sgDesc . ' ' . implode(' ', array_map('strval', $sgTags));
        }
        /* 身份行：单项目可覆写；留空就用上面算好的站点默认值 */
        $sgOwner = trim((string) sg_value($sgProject, 'owner', ''));
        $sgAvatar = trim((string) sg_value($sgProject, 'avatar', ''));
        $sgOwner = $sgOwner !== '' ? $sgOwner : $sgDefOwner;
        $sgAvatar = $sgAvatar !== '' ? $sgAvatar : $sgDefAvatar;
        $sgIcon = trim((string) sg_value($sgProject, 'icon', ''));
        $sgIcon = $sgIcon !== '' ? $sgIcon : '✦';
        ?>
    <article class="sg-glass sg-project" data-sg-filter-item data-sg-spotlight data-keywords="<?php echo sg_e($sgKeywords); ?>">
      <?php if ($sgUrl !== '') : ?>
      <a class="sg-project-link" href="<?php echo sg_e($sgUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sg_e($sgName); ?>"></a>
      <?php endif; ?>
      <div class="sg-project-top">
        <span class="sg-project-avatar">
          <img src="<?php echo sg_e($sgAvatar); ?>" alt="" loading="lazy" decoding="async"
               onerror="this.onerror=null;this.src='<?php echo sg_e($sgFallbackAvatar); ?>'">
        </span>
        <span class="sg-project-owner"><?php echo sg_e($sgOwner); ?></span>
        <?php if ($sgBadge) : ?>
        <span class="sg-project-badge"><?php echo sg_e(implode(' · ', array_map('strval', $sgBadge))); ?></span>
        <?php endif; ?>
      </div>
      <div class="sg-project-head">
        <div class="sg-project-icon"><?php echo sg_e($sgIcon); ?></div>
        <h3><?php echo sg_e($sgName); ?></h3>
      </div>
      <p><?php echo sg_e($sgDesc); ?></p>
      <?php if ($sgTags) : ?>
      <div class="sg-tags">
        <?php foreach ($sgTags as $sgTag) : ?><span><?php echo sg_e((string) $sgTag); ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($sgMeta || $sgUrl !== '') : ?>
      <div class="sg-project-foot">
        <?php if ($sgMeta) : ?>
        <span class="sg-project-meta"><?php echo sg_e(implode(' · ', array_map('strval', $sgMeta))); ?></span>
        <?php endif; ?>
        <?php if ($sgUrl !== '') : ?>
        <span class="sg-project-src">源码 <?php echo sg_icon('chevron-right'); ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if (!$sgProjects) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有项目</h1>
    <p>到后台「设置外观 → 项目」填 GitHub 账号，或直接填「项目 JSON」，字段：name / description / icon / tags / githubUrl。</p>
  </div>
  <?php endif; ?>

  <?php if (sg_user_is_admin() && sg_github_error() !== '') : ?>
  <p class="sg-projects-diag"><?php echo sg_e(sg_projects_diag()); ?></p>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
