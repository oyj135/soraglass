<?php
/**
 * 项目（2026-11-22 第六十七轮：正文可直接粘项目数组 JSON，数据源优先级见下）
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}
$this->need('header.php');

/* 数据源优先级（sg_projects_for_page）：① 本页正文 JSON（正文不再当文章渲染）；
   ② 后台「项目 JSON」；③ GitHub 仓库；④ 主题内置示例。正文不是 JSON 时当页面介绍渲染。 */
$sgRaw = sg_page_raw_text($this);
$sgFromBody = false;
$sgProjects = sg_projects_for_page($this, $sgRaw, $sgFromBody);
$sgProjProse = ($sgRaw !== '' && !$sgFromBody) ? $sgRaw : '';
/* 正文给数据时不能再说「项目来自 GitHub」 */
$sgSourceLine = $sgFromBody ? '' : sg_github_source_line(count($sgProjects));

/* 项目卡的头像（第三十一轮新增，第三十七轮收窄用途）：默认用站点作者头像 ——
   GitHub 头像（github.com/<user>.png）在国内机房经常连不上，一屏卡片全是空圆更难看。
   单个项目仍可在 JSON 里用 avatar 覆写（比如收录别人的开源项目）。 */
$sgFallbackAvatar = sg_theme_url('dist/img/avatar.svg');
$sgDefAvatar = sg_opt('sgAvatarUrl', $sgFallbackAvatar);

$sgHead = [
    'title'       => (string) $this->title,
    'sub'         => sg_page_sub($this, '写过的代码、跑过的实验与还没做完的想法。'),
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索项目名称、简介或技术栈...',
];
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad" data-sg-page="projects">
  <?php sg_page_head($sgHead); ?>

  <?php if ($sgProjProse !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgSourceLine !== '') : ?>
  <p class="sg-projects-source"><?php echo sg_icon_color('github'); ?><?php echo sg_e($sgSourceLine); ?></p>
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
        /* 头像：单项目可用 avatar 覆写，留空用站点作者头像。
           （第三十七轮：这里原来还渲染一行 @owner 的等宽小字 + 一个 emoji 图标块，
           两行加起来只表达「这是谁的项目」，而卡片的主角本来就是项目名。
           名字提到头像右边、图标块整块去掉，卡片立刻少两层层级。） */
        $sgAvatar = trim((string) sg_value($sgProject, 'avatar', ''));
        $sgAvatar = $sgAvatar !== '' ? $sgAvatar : $sgDefAvatar;

        /* 结构化字段（第三十七轮）：GitHub 数据源直接给，手填 JSON 可能没有，
           缺失时从 meta 的「★ 12 / 更新于 2026.11.14」里兜回来。 */
        /* 封面：单项目 JSON 里填 cover（也认 image / thumb / screenshot），
           没有就查后台「项目封面」那张映射表（GitHub 模式下唯一的补图入口）。
           这里取原值（不先转义）—— 地址带查询串时 `&` 被转义成 `&amp;` 会直接 404，
           输出时统一由 sg_e() 转一次。 */
        $sgCover = '';
        foreach (['cover', 'image', 'thumb', 'screenshot'] as $sgCoverKey) {
            if (isset($sgProject[$sgCoverKey]) && is_scalar($sgProject[$sgCoverKey])) {
                $sgCover = trim((string) $sgProject[$sgCoverKey]);
            }
            if ($sgCover !== '') {
                break;
            }
        }
        if ($sgCover === '') {
            $sgCover = sg_project_cover($sgProject);
        }
        $sgCover = $sgCover !== '' ? sg_bg_url($sgCover) : '';

        $sgLang = trim((string) sg_value($sgProject, 'language', ''));
        $sgStars = (int) sg_value($sgProject, 'stars', 0);
        $sgForks = (int) sg_value($sgProject, 'forks', 0);
        $sgUpdated = trim((string) sg_value($sgProject, 'updated', ''));
        foreach ($sgMeta as $sgMetaItem) {
            $sgMetaItem = (string) $sgMetaItem;
            if ($sgStars === 0 && preg_match('/^★\s*(\d+)$/u', trim($sgMetaItem), $sgHit)) {
                $sgStars = (int) $sgHit[1];
            }
            if ($sgUpdated === '' && $sgMetaItem !== '' && strpos($sgMetaItem, '更新于') !== false) {
                $sgUpdated = trim(str_replace('更新于', '', $sgMetaItem));
            }
        }
        /* 语言不再重复出现在标签里：上面已经有独立的语言点了 */
        if ($sgLang !== '') {
            $sgTags = array_values(array_filter($sgTags, static function ($tag) use ($sgLang) {
                return strcasecmp(trim((string) $tag), $sgLang) !== 0;
            }));
        }
        ?>
    <?php /* 第四十二轮回到源站形态：整卡一个 article，内部只有「16:10 封面 + 内容面板」，
               简介常显两行；不再有壳层、浮层抽屉与整卡长高。 */ ?>
    <article class="sg-project<?php echo $sgCover !== '' ? ' sg-project--cover' : ''; ?>" data-sg-filter-item data-keywords="<?php echo sg_e($sgKeywords); ?>"<?php if ($sgLang !== '') : ?> style="--sg-lang: <?php echo sg_e(sg_lang_color($sgLang)); ?>"<?php endif; ?>>
      <?php if ($sgUrl !== '') : ?>
      <a class="sg-project-link" href="<?php echo sg_e($sgUrl); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo sg_e($sgName); ?>"></a>
      <?php endif; ?>
      <?php /* 封面区：有 cover 就放图，没有就用语言色渐变 + 首字母水印；
               16:10 比例由 CSS 撑住，图加载失败时 onerror 摘掉节点，水印与渐变顶上来。 */ ?>
      <div class="sg-project-shot">
        <?php /* 首字母水印垫在封面图下面：图加载成功被盖住，失败时它顶上来 */ ?>
        <span class="sg-project-mark" aria-hidden="true"><?php echo sg_e(mb_substr($sgName, 0, 1, 'UTF-8')); ?></span>
        <?php if ($sgCover !== '') : ?>
        <span class="sg-project-photo"><img src="<?php echo sg_e($sgCover); ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.remove()"></span>
        <?php endif; ?>
      </div>
      <div class="sg-project-body">
      <div class="sg-project-top">
        <span class="sg-project-avatar">
          <img src="<?php echo sg_e($sgAvatar); ?>" alt="" loading="lazy" decoding="async"
               onerror="this.onerror=null;this.src='<?php echo sg_e($sgFallbackAvatar); ?>'">
        </span>
        <h3 class="sg-project-name"><?php echo sg_e($sgName); ?></h3>
        <?php if ($sgBadge) : ?>
        <span class="sg-project-badge"><?php echo sg_e(implode(' · ', array_map('strval', $sgBadge))); ?></span>
        <?php endif; ?>
      </div>
      <?php if ($sgDesc !== '') : ?>
      <p class="sg-project-desc"><?php echo sg_e($sgDesc); ?></p>
      <?php endif; ?>
      <?php if ($sgLang !== '' || $sgStars > 0 || $sgForks > 0) : ?>
      <div class="sg-project-facts">
        <?php if ($sgLang !== '') : ?>
        <span class="sg-project-lang"><i></i><?php echo sg_e($sgLang); ?></span>
        <?php endif; ?>
        <?php if ($sgStars > 0) : ?>
        <span class="sg-project-star"><?php echo sg_icon_color('star'); ?><?php echo (int) $sgStars; ?></span>
        <?php endif; ?>
        <?php if ($sgForks > 0) : ?>
        <span class="sg-project-fork"><?php echo sg_icon_color('fork'); ?><?php echo (int) $sgForks; ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($sgTags) : ?>
      <div class="sg-tags">
        <?php foreach ($sgTags as $sgTag) : ?><span><?php echo sg_e((string) $sgTag); ?></span><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($sgUpdated !== '' || $sgUrl !== '') : ?>
      <div class="sg-project-foot">
        <span class="sg-project-meta"><?php echo $sgUpdated !== '' ? '更新于 ' . sg_e($sgUpdated) : ''; ?></span>
        <?php if ($sgUrl !== '') : ?>
        <span class="sg-project-src">源码 <?php echo sg_icon_color('chevron-right'); ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      </div><?php /* /.sg-project-body */ ?>
    </article>
    <?php endforeach; ?>
  </div>

  <?php if (!$sgProjects) : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有项目</h1>
    <p>两种填法任选：① 编辑本页，正文直接粘贴 JSON（以 <code>[</code> 开头的项目数组，字段：name / description / githubUrl / cover / tags）；② 后台 → 外观 → 主题设置 →「项目 JSON（兜底）」，或填写 GitHub 账号自动列仓库。</p>
  </div>
  <?php endif; ?>

  <?php if (sg_user_is_admin() && sg_github_error() !== '') : ?>
  <p class="sg-projects-diag"><?php echo sg_e(sg_projects_diag()); ?></p>
  <?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
