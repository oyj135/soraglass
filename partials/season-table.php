<?php
/**
 * 岁候 · 对照长表（评审稿 §3.2 第 5 段 / §8.2）
 *
 * 默认 12 行（按宫聚合），可展开为 24 行节气表。两种形态都是纯 HTML 表格：
 * 无 JS 可读、可被搜索与复制，也是这个页面给 SEO 的唯一依据。
 * 「起于」写当年中气实日（宫界随中气浮动，不写固定月日）。
 */
$sgSTable = $GLOBALS['SG_SEASON']['table'] ?? ['gongs' => [], 'terms' => []];
?>
<section class="sg-season-table" aria-label="二十四节气与黄道十二宫对照表">
  <header class="sg-table-head">
    <h2 class="sg-table-title">对照表</h2>
    <p class="sg-table-note">宫界落在中气实日上，随年份浮动；篇数按发表日期统计。</p>
  </header>

  <table class="sg-table sg-table-gong">
    <caption class="sg-sr-only">十二宫及其覆盖的节气</caption>
    <thead>
      <tr><th scope="col">宫</th><th scope="col">起于</th><th scope="col">覆盖</th><th scope="col">篇数</th><th scope="col"><span class="sg-sr-only">打开</span></th></tr>
    </thead>
    <tbody>
      <?php foreach ($sgSTable['gongs'] as $sgRow) : ?>
      <tr<?php echo $sgRow['selected'] ? ' class="is-sel"' : ((!empty($sgRow['now'])) ? ' class="is-now"' : ''); ?>>
        <th scope="row"><?php echo sg_e($sgRow['name'] . '宫'); ?></th>
        <td><?php echo sg_e($sgRow['startName'] . ' ' . $sgRow['startDate']); ?></td>
        <td><?php echo sg_e($sgRow['span']); ?></td>
        <td class="is-num"><?php echo (int) $sgRow['count']; ?></td>
        <td class="is-link"><a href="<?php echo sg_e($sgRow['url']); ?>" aria-label="<?php echo sg_e('查看' . $sgRow['name'] . '宫的 30 天'); ?>">→</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <details class="sg-table-more">
    <summary>展开 24 节气</summary>
    <table class="sg-table sg-table-term">
      <caption class="sg-sr-only">二十四节气及其所属宫</caption>
      <thead>
        <tr><th scope="col">节气</th><th scope="col">日期</th><th scope="col">黄经</th><th scope="col">宫</th><th scope="col">篇数</th><th scope="col"><span class="sg-sr-only">打开</span></th></tr>
      </thead>
      <tbody>
        <?php foreach ($sgSTable['terms'] as $sgRow) : ?>
        <tr<?php echo $sgRow['selected'] ? ' class="is-sel"' : ''; ?>>
          <th scope="row"><?php echo sg_e($sgRow['name']); ?></th>
          <td><?php echo sg_e($sgRow['startDate']); ?></td>
          <td class="is-num"><?php echo (int) $sgRow['lon']; ?>°</td>
          <td><?php echo sg_e($sgRow['gongName'] . '宫'); ?></td>
          <td class="is-num"><?php echo (int) $sgRow['count']; ?></td>
          <td class="is-link"><a href="<?php echo sg_e($sgRow['url']); ?>" aria-label="<?php echo sg_e('查看' . $sgRow['name'] . '的 15 天'); ?>">→</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </details>
</section>
