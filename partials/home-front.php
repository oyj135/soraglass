<?php
/**
 * 首页 100vh 全屏首屏（参考 Fomalhaut #page-header.full_page，含 background-attachment:fixed）。
 *
 * 命名空间一律用 .sg-front* —— 首页主区早已占用 .sg-hero / .sg-hero-cover，
 * 2026-11-16 曾因同名把首页布局改坏，务必隔离，勿改回 .sg-hero。
 * 2026-11-17 再改：首屏不再自备图片层 —— 身后那层 fixed 的全站壁纸（.sg-bg）
 * 就是它的背景，直接透出：首屏与正文天然共用同一张图，壁纸轮播与昼夜切换
 * 自动同步，不会再出现「两张图」；首屏只保留压暗遮罩与文案。
 * 站名/副标题/社交复用后台已有设置项。
 */
$sgFrontName  = sg_opt('sgNavTitle', (string) $this->options->title);
$sgFrontAfter = sg_opt('sgNavAfter', '空璃');
$sgFrontSub   = (string) $this->options->description;
$sgFrontSocial = sg_json('sgSocial', 'social', []);

/* 首屏标语（2026-11-20）：后台「首屏打字机」填 JSON 时优先，留空/解析失败回落到站点副标题。
   兼容两种写法：纯字符串数组 ["第一句","第二句"]，或带配置的对象
   {"lines":[…],"loop":true,"speed":90,"hold":2200,"erase":40}。
   文案只做纯文本输出，emoji 原样保留（前端按字素切分，不会把 emoji 拆成半个）。 */
$sgTyping = ['lines' => [], 'loop' => true, 'speed' => 90, 'hold' => 2200, 'erase' => 40];
$sgTypingClamp = static fn($value, int $min, int $max, int $fallback): int => is_numeric($value)
    ? max($min, min($max, (int) $value))
    : $fallback;
$sgTypingRaw = trim(sg_opt('sgFrontTyping', ''));
if ($sgTypingRaw !== '') {
    $sgTypingData = json_decode($sgTypingRaw, true);
    if (is_array($sgTypingData)) {
        if (array_is_list($sgTypingData)) {
            $sgTypingData = ['lines' => $sgTypingData];
        }
        foreach ((array) ($sgTypingData['lines'] ?? []) as $sgTypingLine) {
            if (!is_scalar($sgTypingLine)) {
                continue;
            }
            $sgTypingLine = trim((string) $sgTypingLine);
            if ($sgTypingLine === '') {
                continue;
            }
            $sgTyping['lines'][] = mb_substr($sgTypingLine, 0, 120);
            if (count($sgTyping['lines']) >= 6) {
                break;
            }
        }
        if ($sgTyping['lines']) {
            $sgTyping['loop'] = !array_key_exists('loop', $sgTypingData) || (bool) $sgTypingData['loop'];
            $sgTyping['speed'] = $sgTypingClamp($sgTypingData['speed'] ?? null, 30, 400, 90);
            $sgTyping['hold'] = $sgTypingClamp($sgTypingData['hold'] ?? null, 400, 20000, 2200);
            $sgTyping['erase'] = $sgTypingClamp($sgTypingData['erase'] ?? null, 10, 300, 40);
        }
    }
}
if (!$sgTyping['lines'] && $sgFrontSub !== '') {
    foreach (preg_split('/\R/u', $sgFrontSub) ?: [] as $sgTypingLine) {
        $sgTypingLine = trim($sgTypingLine);
        if ($sgTypingLine !== '') {
            $sgTyping['lines'][] = mb_substr($sgTypingLine, 0, 120);
        }
    }
    $sgTyping['loop'] = count($sgTyping['lines']) > 1;
}
?>
<section class="sg-front" data-sg-front aria-label="站点首屏">
  <div class="sg-front-inner">
    <h1 class="sg-front-title"><?php echo sg_e($sgFrontName); ?><span class="sg-front-title-mark">· <?php echo sg_e($sgFrontAfter); ?></span></h1>
    <?php if ($sgTyping['lines']) : ?><p class="sg-front-sub" data-sg-typewriter="<?php echo sg_e((string) json_encode($sgTyping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>"><?php echo sg_e($sgTyping['lines'][0]); ?></p><?php endif; ?>
    <?php if ($sgFrontSocial) : ?>
    <div class="sg-front-social">
      <?php foreach ($sgFrontSocial as $sgFrontItem) : ?>
      <?php if (!is_array($sgFrontItem)) { continue; } ?>
      <a class="sg-front-social-link" href="<?php echo sg_e(sg_value($sgFrontItem, 'url', '#')); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo sg_e(sg_value($sgFrontItem, 'name', '链接')); ?>" aria-label="<?php echo sg_e(sg_value($sgFrontItem, 'name', '链接')); ?>">
        <?php echo sg_social_svg(sg_social_icon($sgFrontItem)); ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <a class="sg-front-read" href="#sg-home-content" data-sg-front-read>浏览最新文章</a>
  </div>
  <button class="sg-front-next" type="button" data-sg-front-next aria-label="向下滚动查看正文">
    <span class="sg-front-next-label">向下探索</span>
    <?php echo sg_icon('chevron-down'); ?>
  </button>
</section>
