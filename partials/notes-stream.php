<?php
/**
 * 「碎语」流 —— page-notes.php 与两个维护旧地址的兼容页（chatter / moments）共用。
 *
 * 为什么放进 partial：三个模板只需各自准备标题与占位文案，正文结构只有一份，
 * 改版时不会出现「notes 变了、旧链接没变」的分叉。
 * 变量通过 $GLOBALS 传（Typecho 的 need() 是独立作用域，普通变量进不来）。
 */
$sgNotes = sg_notes();
$sgHead = $GLOBALS['SG_NOTES_HEAD'] ?? [];
$sgHead = array_merge([
    'title'       => '碎语',
    'sub'         => '短句与随想 · 想到什么写什么',
    'search'      => true,
    'center'      => true,
    'placeholder' => '搜索碎语...',
], is_array($sgHead) ? $sgHead : []);

$sgHome = rtrim((string) $this->options->siteUrl, '/') . '/';
?>
<main class="sg-main sg-main--pad" data-sg-page="notes">
  <?php sg_page_head($sgHead); ?>

  <?php if (trim((string) $this->content) !== '') : ?>
  <div class="sg-prose" style="margin-bottom:2rem"><?php $this->content(); ?></div>
  <?php endif; ?>

  <?php if ($sgNotes) : ?>
  <div class="sg-note-stream" data-sg-filter-grid>
    <?php foreach ($sgNotes as $sgNote) : ?>
    <?php
    $sgText = (string) ($sgNote['text'] ?? '');
    $sgTitle = (string) ($sgNote['title'] ?? '');
    $sgTag = (string) ($sgNote['tag'] ?? '碎语');
    $sgDate = (string) ($sgNote['date'] ?? '');
    $sgCover = sg_media((string) ($sgNote['cover'] ?? ''));
    $sgLink = (string) ($sgNote['url'] ?? '');
    ?>
    <article class="sg-glass sg-note sg-note--stream" data-sg-filter-item data-keywords="<?php echo sg_e($sgTitle . ' ' . $sgText . ' ' . $sgTag); ?>">
      <span class="sg-note-meta">
        <span class="sg-chip--tag"><?php echo sg_e($sgTag); ?></span>
        <?php if ($sgDate !== '') : ?><time><?php echo sg_e($sgDate); ?></time><?php endif; ?>
      </span>
      <?php if ($sgTitle !== '') : ?>
      <h3>
        <?php if ($sgLink !== '') : ?><a href="<?php echo sg_e($sgLink); ?>"><?php echo sg_e($sgTitle); ?></a><?php else : ?><?php echo sg_e($sgTitle); ?><?php endif; ?>
      </h3>
      <?php endif; ?>
      <?php if ($sgText !== '') : ?><p><?php echo nl2br(sg_e($sgText)); ?></p><?php endif; ?>
      <?php if ($sgCover !== '') : ?>
      <a class="sg-note-thumb" href="<?php echo sg_e($sgCover); ?>" target="_blank" rel="noopener noreferrer" tabindex="-1" aria-hidden="true">
        <img src="<?php echo sg_e($sgCover); ?>" alt="" loading="lazy">
      </a>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
  <?php else : ?>
  <div class="sg-glass sg-notfound">
    <span class="sg-notfound-code">空</span>
    <h1>还没有碎语</h1>
    <p>后台「碎语 JSON」里写两条，这里就会活起来。</p>
    <div class="sg-notfound-actions">
      <a class="sg-btn" href="<?php echo sg_e($sgHome); ?>"><?php echo sg_icon('arrow-left'); ?>回到首页</a>
    </div>
  </div>
  <?php endif; ?>
</main>
