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
    'focus' => 0,
    'sunH' => 88.0,
    'shadowLen' => 96.0,
    'houIndex' => -1,
    'lightSweep' => true, /* P1-15 入场光扫 */
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

/* P1-12：日轨几何 —— 以圆心 (260,260) 为圆心，水平直径为 18% 盘面（r=47）。
   弧高由 --sg-sun-h 决定（60–116，圆心向上为正），弧形为圆弧而非椭圆。
   太阳点：在弧上按当前节气进度（--sg-hou 0→1，日点走 15°）分布。 */
$sgWSunH = (float) ($sgSWheel['sunH'] ?? 88.0);
$sgWShadowLen = (float) ($sgSWheel['shadowLen'] ?? 96.0);
$sgWHouIndex = (int) ($sgSWheel['houIndex'] ?? -1);
$sgWSunR = 47.0; /* 水平半径：18% 盘面 */
$sgWSunCx = $sgWCx;
$sgWSunCy = $sgWCy; /* 圆心同盘心 */

/* 计算日轨圆弧的圆心偏移：弧高 h 对应的圆心在圆心正下方 d = (R^2 - (W/2)^2) / (2h)
   这里简化：用三个定点（两端在 r=150 水平线、最高点在 (0, -h)）拟合圆弧 */
$sgWSunArcR = ($sgWSunR * $sgWSunR + $sgWSunH * $sgWSunH) / (2.0 * $sgWSunH);
$sgWSunArcCy = $sgWSunCy + $sgWSunArcR - $sgWSunH; /* 圆弧圆心在盘心正下 */

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
<div class="sg-wheel" data-sg-wheel>
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
          /* 标签永远正立：不再按屏幕角判断翻转，每个 text 反向抵消 --sg-rot，
             盘面怎么转字都朝着人（岁轮再设计 P0-1）。 */
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
                  style="transform-origin: <?php echo round($sgGLx, 1); ?>px <?php echo round($sgGLy, 1); ?>px"
                  aria-hidden="true"><?php echo sg_e($sgG['name']); ?></text>
          </a>
          <?php endforeach; ?>
          <?php endif; ?>

          <?php /* 宫界分隔刻（第八十一轮 宫环去棋盘）：12 道跨宫环带的细线，
             替代阴阳交替色块承担「分块」读数；静置时是环带的节拍，不抢戏。 */ ?>
          <g class="sg-wheel-gong-ticks" aria-hidden="true">
            <?php for ($sgKT = 0; $sgKT < 12; $sgKT++) : ?>
            <?php [$sgKTx0, $sgKTy0] = $sgWPt($sgWGongIn + 2.0, $sgKT * 30.0); ?>
            <?php [$sgKTx1, $sgKTy1] = $sgWPt($sgWGongOut - 2.0, $sgKT * 30.0); ?>
            <line x1="<?php echo round($sgKTx0, 2); ?>" y1="<?php echo round($sgKTy0, 2); ?>" x2="<?php echo round($sgKTx1, 2); ?>" y2="<?php echo round($sgKTy1, 2); ?>"></line>
            <?php endfor; ?>
          </g>

          <?php /* 节气环：182–238，四时「墨分四浓」，中气外沿朱点 */ ?>
          <?php foreach ($sgWTerms as $sgT) : ?>
          <?php
          $sgA0 = $sgT['i'] * 15 + 0.9;
          $sgA1 = $sgT['i'] * 15 + 14.1;
          $sgMid = $sgT['i'] * 15 + 7.5;
          [$sgLx, $sgLy] = $sgWPt($sgWLonTerm, $sgMid);
          /* 标签永远正立：反向抵消 --sg-rot，不再按屏幕角判断翻转 */
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
                  style="transform-origin: <?php echo round($sgLx, 1); ?>px <?php echo round($sgLy, 1); ?>px"
                  aria-hidden="true"><?php echo sg_e($sgT['name']); ?></text>
            <?php /* P1-14 盘心导线：从节气名向盘心的一条细朱线，只在 hover 时出现。
                   起点在节气名边缘（r 222 内缩 4px），终点在盘心 (260,260)，由 CSS .sg-wheel-term-leader 控制 */ ?>
            <?php
            /* 导线起点：从节气名位置沿半径向内缩 4px（在 r210 名字环的内侧） */
            $sgLd0 = 206.0;
            $sgLd1 = 0.0;
            [$sgLdx0, $sgLdy0] = $sgWPt($sgLd0, $sgMid);
            [$sgLdx1, $sgLdy1] = $sgWPt($sgLd1, $sgMid);
            ?>
            <line class="sg-wheel-term-leader"
                  x1="<?php echo round($sgLdx0, 1); ?>" y1="<?php echo round($sgLdy0, 1); ?>"
                  x2="<?php echo round($sgLdx1, 1); ?>" y2="<?php echo round($sgLdy1, 1); ?>"
                  aria-hidden="true"></line>
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
            <?php /* 第八十一轮：节气环内外沿 hairline（r182/r238），24 段读成一条环带 */ ?>
            <circle cx="260" cy="260" r="182"></circle>
            <circle cx="260" cy="260" r="238"></circle>
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
            <text class="sg-wheel-year-label" x="<?php echo round($sgYx + 12, 1); ?>" y="<?php echo round($sgYy - 12, 1); ?>"
                  text-anchor="middle"
                  style="transform-origin: <?php echo round($sgYx + 12, 1); ?>px <?php echo round($sgYy - 12, 1); ?>px">岁首</text>
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

        <?php /* P1-12：日轨 + 日点 + 晷影 —— 盘心留白的上 55% 区域（r150 内） */ ?>
        <g class="sg-wheel-sun" aria-hidden="true">
          <?php
          /* 日轨弧：水平直径 = 2 * $sgWSunR (94，即 18% 盘面)，左/右端点在 y = $sgWSunCy。
             弧顶在 (cx, cy - $sgWSunH)，即向上 h 单位。三点确定圆弧：
             圆心在 (cx, cy + d)，半径 R = (r^2 + h^2) / (2h)，d = R - h。
             端点角度 ±θ，sin θ = r / R，θ = asin(r/R)。
             这样端点刚好在 (cx ± r, cy)，弧顶在 (cx, cy - h)，完全自洽。 */
          $sgWSunArcR = ($sgWSunR * $sgWSunR + $sgWSunH * $sgWSunH) / (2.0 * $sgWSunH);
          $sgWSunArcCy = $sgWSunCy + $sgWSunArcR - $sgWSunH;
          $sgSunHalfAngle = asin($sgWSunR / $sgWSunArcR); /* rad，端点角度相对于圆心正上方 */
          $sgSunStartDeg = 90.0 - $sgSunHalfAngle * 180.0 / M_PI; /* 从左端点起，顺时针到右端点 */
          $sgSunEndDeg   = 90.0 + $sgSunHalfAngle * 180.0 / M_PI;
          $sgSunStartX = $sgWSunCx + $sgWSunArcR * sin(deg2rad($sgSunStartDeg));
          $sgSunStartY = $sgWSunArcCy - $sgWSunArcR * cos(deg2rad($sgSunStartDeg));
          $sgSunEndX   = $sgWSunCx + $sgWSunArcR * sin(deg2rad($sgSunEndDeg));
          $sgSunEndY   = $sgWSunArcCy - $sgWSunArcR * cos(deg2rad($sgSunEndDeg));
          ?>
          <path class="sg-wheel-sun-track"
                d="M<?php echo round($sgSunStartX, 2); ?> <?php echo round($sgSunStartY, 2); ?>
                   A<?php echo round($sgWSunArcR, 2); ?> <?php echo round($sgWSunArcR, 2); ?> 0 0 1
                   <?php echo round($sgSunEndX, 2); ?> <?php echo round($sgSunEndY, 2); ?>"
                style="--sg-sun-h: <?php echo round($sgWSunH, 1); ?>;"></path>

          <?php /* 日点：落在弧上的位置由 --sg-hou（0→1，season.ts 按滚动写入）驱动。
             PHP 只负责给出「几何」与「无 JS 时的静态落点」：
               · cx / cy  —— 当令候（houIndex）的候中点落点，无 JS 时也对；
               · data-sg-arc-{cx,cy,r,deg0,deg1} —— JS 用它把 --sg-hou 换算成 cx/cy，
                 不依赖 CSS offset-path（SVG 元素对 offset-path 支持不稳）。
             设计稿 §7.2：日点角度 = 节气起点 + 15° × --sg-hou。 */ ?>
          <?php
          $sgSunT0 = $sgWHouIndex >= 0 ? ($sgWHouIndex + 0.5) / 3.0 : 0.5;
          $sgSunDotDeg = $sgSunStartDeg + ($sgSunEndDeg - $sgSunStartDeg) * $sgSunT0;
          $sgSunDotX = $sgWSunCx + $sgWSunArcR * sin(deg2rad($sgSunDotDeg));
          $sgSunDotY = $sgWSunArcCy - $sgWSunArcR * cos(deg2rad($sgSunDotDeg));
          ?>
          <circle class="sg-wheel-sun-dot" r="2.5"
                  cx="<?php echo round($sgSunDotX, 2); ?>"
                  cy="<?php echo round($sgSunDotY, 2); ?>"
                  data-sg-arc-cx="<?php echo round($sgWSunCx, 2); ?>"
                  data-sg-arc-cy="<?php echo round($sgWSunArcCy, 2); ?>"
                  data-sg-arc-r="<?php echo round($sgWSunArcR, 3); ?>"
                  data-sg-arc-deg0="<?php echo round($sgSunStartDeg, 4); ?>"
                  data-sg-arc-deg1="<?php echo round($sgSunEndDeg, 4); ?>"></circle>
        </g>

        <?php /* P1-15 入场光扫：一道极淡朱色 wedge 沿盘面扫一圈然后消失。
                实现：15° 扇形 + 朱色渐变遮罩，动画 rotate(-360deg→0) 3.5s linear。
                只在页面加载时触发一次（由 .is-sweeping 控制）。 */ ?>
        <g class="sg-wheel-sweep" aria-hidden="true">
          <defs>
            <linearGradient id="sg-sweep-grad" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stop-color="var(--sg-warm)" stop-opacity="0"></stop>
              <stop offset="100%" stop-color="var(--sg-warm)" stop-opacity=".08"></stop>
            </linearGradient>
          </defs>
          <path class="sg-wheel-sweep-wedge" d="M260 260 L260 40 L300 48 Z"></path>
          <path d="M260 260 L260 40 L300 48 Z" fill="url(#sg-sweep-grad)"></path>
        </g>

        <?php /* P1-12 晷影：从针尖 (260,32) 垂直落下到 r150 上沿，长度 = --sg-shadow-len。
                方向沿 +Y（向盘心），宽度 6、透明度 12%、blur(2)。 */ ?>
        <g class="sg-wheel-needle-shadow" aria-hidden="true">
          <path class="sg-wheel-needle-shadow-path"
                d="M260 32 L260 <?php echo round(32 + $sgWShadowLen, 1); ?>"></path>
        </g>
      </svg>

      <div class="sg-wheel-center" data-sg-wheel-center>
        <span class="sg-wheel-center-name" data-sg-wheel-name><?php echo sg_e($sgSWheel['centerName']); ?></span>
        <span class="sg-wheel-center-sub" data-sg-wheel-sub><?php echo sg_e($sgSWheel['centerSub']); ?></span>
        <?php /* R2-3 三候点 + caption：点只作指示，「二候 · 麋角解」才说得清含义。 */ ?>
        <?php if ($sgWHouIndex >= 0) : ?>
        <?php
        $sgWHouName = $sgSWheel['houName'] ?? ($sgSTerms[$sgSYear === $sgSThisYear && $sgSNow ? (int)$sgSNow['term'] : $sgST]->hou[$sgWHouIndex] ?? '');
        $sgWHouOrd  = $sgSWheel['houOrd']  ?? ($sgSHouNames[$sgWHouIndex] ?? '一候');
        ?>
        <span class="sg-wheel-hou-dots" aria-hidden="true">
          <span class="sg-wheel-hou-dots-row">
            <?php for ($sgHd = 0; $sgHd < 3; $sgHd++) : ?>
            <i class="<?php echo $sgHd === $sgWHouIndex ? 'is-now' : ''; ?>"></i>
            <?php endfor; ?>
          </span>
          <span class="sg-wheel-hou-caption"><?php echo sg_e($sgWHouOrd . ' · ' . $sgWHouName); ?></span>
        </span>
        <?php endif; ?>
      </div>
      <?php /* R2-4 求签落章：朱印压在盘心读数上（与 .sg-wheel-center 同级，absolute 相对 .sg-wheel-plot） */ ?>
      <span class="sg-seal sg-seal--draw" aria-hidden="true">求签</span>
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
    <button type="button" class="sg-wheel-draw" data-sg-draw aria-disabled="false">
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
