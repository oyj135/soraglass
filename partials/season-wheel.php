<?php
/**
 * 岁候 · 双刻度轮盘（服务端渲染 SVG，评审稿 §6.1 / §6.4 / §9.2）
 *
 * 角度约定（写死，不要在别处重算）：
 *   α(i) = i × 15°，屏幕 12 点钟为 0°、顺时针增大
 *   x = cx + r·sin(α)，y = cy − r·cos(α)
 * 所以春分在正上、夏至在正右（3 点）、秋分在正下、冬至在正左（9 点），
 * 1 月的节气（小寒 285° / 大寒 300° / 立春 315°）必然落在表盘左上。
 *
 * 外环 24 节气（四时色 + 线型），内环 12 宫（中性，不参与夜间轮询），
 * 中气位置外沿一枚朱点 —— 朱点即宫界，一眼可验两套刻度是否对齐。
 * 无障碍：整环只有一个 Tab 停靠点（roving tabindex），另有视觉隐藏的 24 项列表。
 */
$sgSWheel = $GLOBALS['SG_SEASON']['wheel'] ?? [
    'terms' => [], 'gongs' => [], 'current' => -1, 'selectedTerm' => -1,
    'selectedGong' => -1, 'showGong' => true, 'centerName' => '岁候', 'centerSub' => '',
];
$sgWTerms = $sgSWheel['terms'];
$sgWCx = 260.0;
$sgWCy = 260.0;
$sgWTermIn = 198.0;
$sgWTermOut = 240.0;
$sgWGongIn = 166.0;
$sgWGongOut = 196.0;

/** 极坐标 → 屏幕坐标（角度制，0° 在正上，顺时针）。 */
$sgWPt = static function (float $radius, float $deg) use ($sgWCx, $sgWCy): array {
    $a = deg2rad($deg);
    return [$sgWCx + $radius * sin($a), $sgWCy - $radius * cos($a)];
};

/** 圆环扇段：外弧顺时针、内弧逆时针，首尾闭合。 */
$sgWArc = static function (float $rIn, float $rOut, float $a0, float $a1) use ($sgWPt): string {
    [$x0, $y0] = $sgWPt($rOut, $a0);
    [$x1, $y1] = $sgWPt($rOut, $a1);
    [$x2, $y2] = $sgWPt($rIn, $a1);
    [$x3, $y3] = $sgWPt($rIn, $a0);
    return sprintf(
        'M%.2f %.2f A%.2f %.2f 0 0 1 %.2f %.2f L%.2f %.2f A%.2f %.2f 0 0 0 %.2f %.2f Z',
        $x0, $y0, $rOut, $rOut, $x1, $y1, $x2, $y2, $rIn, $rIn, $x3, $y3
    );
};

/** 四时：立夏 i=3、立秋 i=9、立冬 i=15、立春 i=21 为界。 */
$sgWSeason = static function (int $i): string {
    if ($i >= 3 && $i < 9) {
        return 'summer';
    }
    if ($i >= 9 && $i < 15) {
        return 'autumn';
    }
    if ($i >= 15 && $i < 21) {
        return 'winter';
    }
    return 'spring';
};

/* 当令所属宫界（中气）在 marks 里的序号：宫界永远落在偶数序节气上。
   第七十六轮：给它一枚会呼吸的脉冲圆，轮盘「此刻在哪」才看得见，不只是一个高亮扇区。 */
$sgWNow = (int) ($sgSWheel['current'] ?? -1);
$sgMNow = $sgWNow >= 0 ? (int) floor($sgWNow / 2) * 2 : -1;
?>
<nav class="sg-wheel" aria-label="二十四节气与黄道十二宫">
  <div class="sg-wheel-plot">
    <svg class="sg-wheel-svg" viewBox="0 0 520 520" role="group" aria-label="双刻度轮盘">
      <?php /* 宫环：半径 166–196，中性灰，朱点标界 */ ?>
      <?php if ($sgSWheel['showGong']) : ?>
      <?php foreach ($sgSWheel['gongs'] as $sgG) : ?>
      <?php
      $sgGA0 = $sgG['i'] * 30 + 0.7;
      $sgGA1 = $sgG['i'] * 30 + 29.3;
      $sgGMid = $sgG['i'] * 30 + 15;
      [$sgGLx, $sgGLy] = $sgWPt(181.0, $sgGMid);
      $sgGRot = $sgGMid > 90 && $sgGMid < 270 ? $sgGMid + 180 : $sgGMid;
      ?>
      <a class="sg-wheel-gong<?php echo $sgG['selected'] ? ' is-sel' : ''; ?><?php echo $sgG['count'] > 0 ? '' : ' is-empty'; ?>"
         href="<?php echo sg_e($sgG['url']); ?>"
         data-sg-gong-i="<?php echo (int) $sgG['i']; ?>"
         data-sg-label="<?php echo sg_e($sgG['name'] . '宫 · ' . $sgG['span']); ?>"
         tabindex="-1"<?php echo $sgG['selected'] ? ' aria-current="true"' : ''; ?>>
        <title><?php echo sg_e($sgG['name'] . '宫 · ' . $sgG['span'] . ' · ' . $sgG['count'] . ' 篇'); ?></title>
        <path class="sg-wheel-gong-arc" d="<?php echo $sgWArc($sgWGongIn, $sgWGongOut, $sgGA0, $sgGA1); ?>" aria-hidden="true"></path>
        <text class="sg-wheel-gong-label" x="<?php echo round($sgGLx, 1); ?>" y="<?php echo round($sgGLy, 1); ?>"
              text-anchor="middle" dominant-baseline="middle"
              transform="rotate(<?php echo round($sgGRot, 1); ?> <?php echo round($sgGLx, 1); ?> <?php echo round($sgGLy, 1); ?>)"
              aria-hidden="true"><?php echo sg_e($sgG['name']); ?></text>
      </a>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php /* 节气环：半径 198–240，四时色 + 线型，中气外沿朱点 */ ?>
      <?php foreach ($sgWTerms as $sgT) : ?>
      <?php
      $sgA0 = $sgT['i'] * 15 + 0.9;
      $sgA1 = $sgT['i'] * 15 + 14.1;
      $sgMid = $sgT['i'] * 15 + 7.5;
      [$sgLx, $sgLy] = $sgWPt(219.0, $sgMid);
      $sgRot = $sgMid > 90 && $sgMid < 270 ? $sgMid + 180 : $sgMid;
      $sgCls = 'sg-wheel-term is-' . $sgWSeason($sgT['i']);
      if ($sgT['current']) {
          $sgCls .= ' is-current';
      }
      if ($sgT['selected']) {
          $sgCls .= ' is-sel';
      }
      if ($sgT['count'] === 0) {
          $sgCls .= ' is-empty';
      }
      ?>
      <a class="<?php echo $sgCls; ?>"
         href="<?php echo sg_e($sgT['url']); ?>"
         data-sg-term-i="<?php echo (int) $sgT['i']; ?>"
         data-sg-gong="<?php echo (int) $sgT['gong']; ?>"
         data-sg-label="<?php echo sg_e($sgT['name'] . ' · ' . $sgT['gongName'] . '宫'); ?>"
         data-sg-sub="<?php echo sg_e($sgT['lon'] . '° · ' . $sgT['sub']); ?>"
         tabindex="<?php echo $sgT['selected'] ? '0' : '-1'; ?>"<?php echo $sgT['selected'] ? ' aria-current="true"' : ''; ?>>
        <title><?php echo sg_e($sgT['name'] . ' · ' . $sgT['date'] . ' · ' . $sgT['count'] . ' 篇'); ?></title>
        <path class="sg-wheel-term-arc" d="<?php echo $sgWArc($sgWTermIn, $sgWTermOut, $sgA0, $sgA1); ?>" aria-hidden="true"></path>
        <text class="sg-wheel-term-label" x="<?php echo round($sgLx, 1); ?>" y="<?php echo round($sgLy, 1); ?>"
              text-anchor="middle" dominant-baseline="middle"
              transform="rotate(<?php echo round($sgRot, 1); ?> <?php echo round($sgLx, 1); ?> <?php echo round($sgLy, 1); ?>)"
              aria-hidden="true"><?php echo sg_e($sgT['name']); ?></text>
      </a>
      <?php endforeach; ?>

      <?php /* 中气朱点：12 枚，落在宫界上（i 为偶数的节气起点） */ ?>
      <g class="sg-wheel-marks" aria-hidden="true">
        <?php for ($sgM = 0; $sgM < 24; $sgM += 2) : ?>
        <?php [$sgMx, $sgMy] = $sgWPt(247.0, $sgM * 15); ?>
        <circle class="sg-wheel-mark<?php echo $sgM === $sgMNow ? ' is-now' : ''; ?>" cx="<?php echo round($sgMx, 2); ?>" cy="<?php echo round($sgMy, 2); ?>" r="<?php echo $sgM === 0 ? 3.4 : 2.1; ?>"></circle>
        <?php endfor; ?>
        <?php /* 当令宫界的脉冲：一圈从朱点散开，2.8s 一次，与天文馆的 ripple 同节奏 */ ?>
        <?php if ($sgMNow >= 0) : ?>
        <?php [$sgPx, $sgPy] = $sgWPt(247.0, $sgMNow * 15); ?>
        <circle class="sg-wheel-pulse" cx="<?php echo round($sgPx, 2); ?>" cy="<?php echo round($sgPy, 2); ?>" r="3"></circle>
        <?php endif; ?>
        <?php /* 岁首：立春 i=21 起点（315°），细朱刻 + 二字 */ ?>
        <?php [$sgYx, $sgYy] = $sgWPt(247.0, 315.0); ?>
        <path class="sg-wheel-year" d="M<?php echo round($sgYx, 1); ?> <?php echo round($sgYy, 1); ?> L<?php echo round($sgYx + 5, 1); ?> <?php echo round($sgYy - 9, 1); ?>" aria-hidden="true"></path>
        <text class="sg-wheel-year-label" x="<?php echo round($sgYx + 12, 1); ?>" y="<?php echo round($sgYy - 12, 1); ?>" text-anchor="middle">岁首</text>
      </g>
    </svg>

    <div class="sg-wheel-center" data-sg-wheel-center>
      <span class="sg-wheel-center-name" data-sg-wheel-name><?php echo sg_e($sgSWheel['centerName']); ?></span>
      <span class="sg-wheel-center-sub" data-sg-wheel-sub><?php echo sg_e($sgSWheel['centerSub']); ?></span>
    </div>
  </div>

  <?php /* 读屏用：24 项文字列表，比听环形 SVG 的 aria-label 可靠 */ ?>
  <ul class="sg-sr-only">
    <?php foreach ($sgWTerms as $sgT) : ?>
    <li><a href="<?php echo sg_e($sgT['url']); ?>"><?php echo sg_e($sgT['name'] . '，' . $sgT['date'] . '，' . $sgT['count'] . ' 篇'); ?></a></li>
    <?php endforeach; ?>
  </ul>
</nav>

<?php /* 移动端：横向 24 节气滑条 + 12 格宫条（宫在手机上不消失，评审稿 §6.5） */ ?>
<div class="sg-season-strip" data-sg-strip>
  <ol class="sg-strip-terms" aria-label="节气滑条">
    <?php foreach ($sgWTerms as $sgT) : ?>
    <li>
      <a class="sg-strip-term<?php echo $sgT['current'] ? ' is-current' : ''; ?><?php echo $sgT['selected'] ? ' is-sel' : ''; ?><?php echo $sgT['count'] === 0 ? ' is-empty' : ''; ?>"
         href="<?php echo sg_e($sgT['url']); ?>"<?php echo $sgT['selected'] ? ' aria-current="true"' : ''; ?>>
        <span><?php echo sg_e($sgT['name']); ?></span>
        <em><?php echo (int) $sgT['count']; ?></em>
      </a>
    </li>
    <?php endforeach; ?>
  </ol>
  <ol class="sg-strip-gongs" aria-label="十二宫">
    <?php foreach ($sgSWheel['gongs'] as $sgG) : ?>
    <li>
      <a class="sg-strip-gong<?php echo $sgG['selected'] ? ' is-sel' : ''; ?><?php echo $sgG['count'] > 0 ? ' has-content' : ''; ?>"
         href="<?php echo sg_e($sgG['url']); ?>" title="<?php echo sg_e($sgG['name'] . '宫 · ' . $sgG['count'] . ' 篇'); ?>"
         aria-label="<?php echo sg_e($sgG['name'] . '宫，' . $sgG['count'] . ' 篇'); ?>">
        <?php echo ($sgG['i'] === 0 || $sgG['i'] === 11) ? '<span>' . sg_e($sgG['name']) . '</span>' : '<i></i>'; ?>
      </a>
    </li>
    <?php endforeach; ?>
  </ol>
</div>
