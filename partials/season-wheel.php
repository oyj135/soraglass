<?php
/**
 * 岁候 · 双刻度轮盘（服务端渲染 SVG，评审稿 §6.1 / §6.4 / §9.2）
 *
 * 角度约定（写死，不要在别处重算）：
 *   α(i) = i × 15°，屏幕 12 点钟为 0°、顺时针增大
 *   x = cx + r·sin(α)，y = cy − r·cos(α)
 * 盘面本身仍按黄道绝对次序排布（春分 0°），但整盘会被转到「你选的这一段」上：
 *
 * 第七十七轮 · 盘转针定
 *   指针恒在正上方、永远不动；整盘 `<g class="sg-wheel-rotor">` 绕盘心旋转，
 *   静置角 rot = −α(focus)，focus 是当前区间的中心（节气 +7.5°、宫 +15°）。
 *   于是针尖所指 ≡ 中心读数 ≡ 四角读数，三者不会打架。
 *   CSS 变量 --sg-rot 同时驱动 rotor 与刻度带（.sg-wheel-plot::after），JS 只改这一个值，
 *   拖动、求签、吸附动画共用同一条通道，不新增第二套坐标。
 *
 * 配色 · 黑白太极（第七十七轮）
 *   宫环 12 块阳/阴交替（古典阳性/阴性星座本就交替，所以阴阳 = i % 2，零映射表）；
 *   节气环按四时分「墨分四浓」，不再用四时色相 —— 黑白下靠墨的浓淡分级。
 *
 * 无障碍：整环只有一个 Tab 停靠点（roving tabindex），另有视觉隐藏的 24 项列表。
 */
$sgSWheel = $GLOBALS['SG_SEASON']['wheel'] ?? [
    'terms' => [], 'gongs' => [], 'current' => -1, 'selectedTerm' => -1,
    'selectedGong' => -1, 'showGong' => true, 'centerName' => '岁候', 'centerSub' => '',
    'focus' => 0, 'autoDraw' => false,
];
$sgWTerms = $sgSWheel['terms'];

/* 盘面几何（viewBox 520，圆心 260）——三档半径写在这里，别在 CSS 里再算一遍。
   中心留白 r150 / 宫环 148–180（厚 32）/ 节气环 182–238（厚 56）/ 刻度带 239–247 / 朱点 r248。
   节气环比第七十六轮加厚 14 个单位，是字号能放到 14px 而不压线的物理前提。 */
$sgWCx = 260.0;
$sgWCy = 260.0;
$sgWGongIn = 148.0;
$sgWGongOut = 180.0;
$sgWTermIn = 182.0;
$sgWTermOut = 238.0;
$sgWMarkR = 248.0;
$sgWLonGong = 164.0;
$sgWLonTerm = 210.0;

/* 静置角：把 focus 转到正上方。focus 由页面模板按模式给（节气 +7.5 / 宫 +15）。 */
$sgWFocus = (float) ($sgSWheel['focus'] ?? 0.0);
$sgWRot = -$sgWFocus;

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

/* 当令所属宫界（中气）在 marks 里的序号：宫界永远落在偶数序节气上。 */
$sgWNow = (int) ($sgSWheel['current'] ?? -1);
$sgMNow = $sgWNow >= 0 ? (int) floor($sgWNow / 2) * 2 : -1;
?>
<div class="sg-wheel" data-sg-wheel<?php echo !empty($sgSWheel['autoDraw']) ? ' data-sg-auto-draw="1"' : ''; ?>>
  <nav class="sg-wheel-disc" aria-label="二十四节气与黄道十二宫">
    <div class="sg-wheel-plot" data-sg-wheel-plot style="--sg-rot: <?php echo round($sgWRot, 2); ?>deg">
      <svg class="sg-wheel-svg" viewBox="0 0 520 520" role="group" aria-label="双刻度轮盘">
        <?php /* 会转的部分全在这一个 g 里；CSS 用 --sg-rot 驱动 transform，不吃 SVG 属性 */ ?>
        <g class="sg-wheel-rotor">

          <?php /* 宫环：148–180，十二块阳/阴交替 —— 太极的色块层 */ ?>
          <?php if ($sgSWheel['showGong']) : ?>
          <?php foreach ($sgSWheel['gongs'] as $sgG) : ?>
          <?php
          $sgGA0 = $sgG['i'] * 30 + 0.8;
          $sgGA1 = $sgG['i'] * 30 + 29.2;
          $sgGMid = $sgG['i'] * 30 + 15;
          [$sgGLx, $sgGLy] = $sgWPt($sgWLonGong, $sgGMid);
          /* 翻转要按「转到屏幕上的角度」判断，不能按盘面角：整盘被旋转后，
             盘面角 90–270 的那半圈可能正好朝上，按盘面角翻会翻反（第七十七轮修正）。 */
          $sgGScreen = fmod($sgGMid + $sgWRot + 720.0, 360.0);
          $sgGRot = $sgGScreen > 90 && $sgGScreen < 270 ? $sgGMid + 180 : $sgGMid;
          $sgGTone = $sgG['i'] % 2 === 0 ? 'yang' : 'yin';
          ?>
          <a class="sg-wheel-gong is-<?php echo $sgGTone; ?><?php echo $sgG['selected'] ? ' is-sel' : ''; ?><?php echo $sgG['count'] > 0 ? '' : ' is-empty'; ?>"
             href="<?php echo sg_e($sgG['url']); ?>"
             data-sg-gong-i="<?php echo (int) $sgG['i']; ?>"
             data-sg-label="<?php echo sg_e($sgG['name'] . '宫 · ' . $sgG['span']); ?>"
             tabindex="-1"<?php echo $sgG['selected'] ? ' aria-current="true"' : ''; ?>>
            <title><?php echo sg_e($sgG['name'] . '宫 · ' . $sgG['span'] . ' · ' . ($sgG['count'] === 0 ? '未落笔' : $sgG['count'] . ' 篇')); ?></title>
            <path class="sg-wheel-gong-arc" d="<?php echo $sgWArc($sgWGongIn, $sgWGongOut, $sgGA0, $sgGA1); ?>" aria-hidden="true"></path>
            <text class="sg-wheel-gong-label" x="<?php echo round($sgGLx, 1); ?>" y="<?php echo round($sgGLy, 1); ?>"
                  text-anchor="middle" dominant-baseline="middle"
                  transform="rotate(<?php echo round($sgGRot, 1); ?> <?php echo round($sgGLx, 1); ?> <?php echo round($sgGLy, 1); ?>)"
                  aria-hidden="true"><?php echo sg_e($sgG['name']); ?></text>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>

          <?php /* 节气环：182–238，四时「墨分四浓」，中气外沿朱点 */ ?>
          <?php foreach ($sgWTerms as $sgT) : ?>
          <?php
          $sgA0 = $sgT['i'] * 15 + 0.9;
          $sgA1 = $sgT['i'] * 15 + 14.1;
          $sgMid = $sgT['i'] * 15 + 7.5;
          [$sgLx, $sgLy] = $sgWPt($sgWLonTerm, $sgMid);
          /* 同上：按屏幕角判断，盘转之后字才都朝着人 */
          $sgScreen = fmod($sgMid + $sgWRot + 720.0, 360.0);
          $sgRot = $sgScreen > 90 && $sgScreen < 270 ? $sgMid + 180 : $sgMid;
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
            <title><?php echo sg_e($sgT['name'] . ' · ' . $sgT['date'] . ' · ' . ($sgT['count'] === 0 ? '未落笔' : $sgT['count'] . ' 篇')); ?></title>
            <path class="sg-wheel-term-arc" d="<?php echo $sgWArc($sgWTermIn, $sgWTermOut, $sgA0, $sgA1); ?>" aria-hidden="true"></path>
            <text class="sg-wheel-term-label" x="<?php echo round($sgLx, 1); ?>" y="<?php echo round($sgLy, 1); ?>"
                  text-anchor="middle" dominant-baseline="middle"
                  transform="rotate(<?php echo round($sgRot, 1); ?> <?php echo round($sgLx, 1); ?> <?php echo round($sgLy, 1); ?>)"
                  aria-hidden="true"><?php echo sg_e($sgT['name']); ?></text>
          </a>
          <?php endforeach; ?>

          <?php /* 七十二候刻度环（升级）：每节气内 +5/+10 两道细刻（r 240-246），
             共 48 道；与中气朱点（每 15 度）合成「一年 72 候」的完整仪器读数。 */ ?>
          <g class="sg-wheel-hou" aria-hidden="true">
            <?php for ($sgT2 = 0; $sgT2 < 24; $sgT2++) : ?>
            <?php foreach ([5.0, 10.0] as $sgHou) : ?>
            <?php [$sgHx0, $sgHy0] = $sgWPt(240.0, $sgT2 * 15 + $sgHou); ?>
            <?php [$sgHx1, $sgHy1] = $sgWPt(246.0, $sgT2 * 15 + $sgHou); ?>
            <line x1="<?php echo round($sgHx0, 2); ?>" y1="<?php echo round($sgHy0, 2); ?>" x2="<?php echo round($sgHx1, 2); ?>" y2="<?php echo round($sgHy1, 2); ?>"></line>
            <?php endforeach; ?>
            <?php endfor; ?>
          </g>

          <?php /* 蚀刻内环（升级）：盘心留白里一圈发丝细线 + 第二圈虚线，
             像文物图录里日晷盘面的凹印，不抢任何读数。 */ ?>
          <g class="sg-wheel-etch" aria-hidden="true">
            <circle cx="260" cy="260" r="141"></circle>
            <circle cx="260" cy="260" r="136" stroke-dasharray="1 5"></circle>
          </g>

          <?php /* 中气朱点：12 枚，落在宫界上（i 为偶数的节气起点） */ ?>
          <g class="sg-wheel-marks" aria-hidden="true">
            <?php for ($sgM = 0; $sgM < 24; $sgM += 2) : ?>
            <?php [$sgMx, $sgMy] = $sgWPt($sgWMarkR, $sgM * 15); ?>
            <circle class="sg-wheel-mark<?php echo $sgM === $sgMNow ? ' is-now' : ''; ?>" cx="<?php echo round($sgMx, 2); ?>" cy="<?php echo round($sgMy, 2); ?>" r="<?php echo $sgM === 0 ? 3.4 : 2.1; ?>"></circle>
            <?php endfor; ?>
            <?php /* 当令宫界的脉冲：一圈从朱点散开，2.8s 一次，与天文馆的 ripple 同节奏 */ ?>
            <?php if ($sgMNow >= 0) : ?>
            <?php [$sgPx, $sgPy] = $sgWPt($sgWMarkR, $sgMNow * 15); ?>
            <circle class="sg-wheel-pulse" cx="<?php echo round($sgPx, 2); ?>" cy="<?php echo round($sgPy, 2); ?>" r="3"></circle>
            <?php endif; ?>
            <?php /* 岁首：立春 i=21 起点（315°），细朱刻 + 二字 */ ?>
            <?php [$sgYx, $sgYy] = $sgWPt($sgWMarkR, 315.0); ?>
            <path class="sg-wheel-year" d="M<?php echo round($sgYx, 1); ?> <?php echo round($sgYy, 1); ?> L<?php echo round($sgYx + 5, 1); ?> <?php echo round($sgYy - 9, 1); ?>" aria-hidden="true"></path>
            <text class="sg-wheel-year-label" x="<?php echo round($sgYx + 12, 1); ?>" y="<?php echo round($sgYy - 12, 1); ?>" text-anchor="middle">岁首</text>
          </g>
        </g>

        <?php /* 指针：盘转针定，全盘唯一不动的东西。墨针一枚细长的风筝形，
               针尖停在节气环外半圈（r≈226），压不到名字 —— 名字在 r210，仍留 10 个单位的空。
               朱锋只点在最尖端，是全盘第三处朱色（另两处：朱印、中气朱点）。 */ ?>
        <g class="sg-wheel-needle" aria-hidden="true">
          <line class="sg-wheel-needle-tail" x1="260" y1="36" x2="260" y2="47"></line>
          <path class="sg-wheel-needle-body" d="M260 34 L265.5 12 L260 2 L254.5 12 Z"></path>
          <circle class="sg-wheel-needle-tip" cx="260" cy="32" r="2.2"></circle>
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

  <?php /* 求签：摇一次，落一节气，然后去看它。自动旋转与手动求签走同一条链路（season.ts 的 spinTo） */ ?>
  <div class="sg-wheel-foot">
    <button type="button" class="sg-wheel-draw" data-sg-draw>
      <span class="sg-wheel-draw-cn">求签</span>
      <span class="sg-wheel-draw-en">draw a lot</span>
    </button>
  </div>
</div>

<?php /* 移动端：横向 24 节气滑条 + 12 格宫条（宫在手机上不消失，评审稿 §6.5） */ ?>
<div class="sg-season-strip" data-sg-strip>
  <ol class="sg-strip-terms" aria-label="节气滑条">
    <?php foreach ($sgWTerms as $sgT) : ?>
    <li>
      <a class="sg-strip-term<?php echo $sgT['current'] ? ' is-current' : ''; ?><?php echo $sgT['selected'] ? ' is-sel' : ''; ?><?php echo $sgT['count'] === 0 ? ' is-empty' : ''; ?>"
         href="<?php echo sg_e($sgT['url']); ?>"<?php echo $sgT['selected'] ? ' aria-current="true"' : ''; ?>>
        <span><?php echo sg_e($sgT['name']); ?></span>
        <em><?php echo $sgT['count'] === 0 ? '–' : (int) $sgT['count']; ?></em>
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
