<?php
/**
 * 字海 —— 检字馆（以字为轴组织全站文字）
 *
 * 一部活字典：查一个字，看它何时初现、用在哪里、是否久违。
 * 数据层在 lib/zi.php（运行时纯 PHP 构建，缓存随内容指纹自动失效）。
 *
 * 参数：
 *   无参数    门厅（今日一字 / 高频二十 / 仅一见 / 全站读数）
 *   ?c=墨     该字卡（取参数里第一个汉字，其余忽略）
 *   ?rebuild=1  管理员强制重建字表（非管理员静默忽略）
 *
 * 口径与边界收在页脚折叠 details；canonical 走 header.php 的
 * SG_CANONICAL 机制（?c= 自指，与旧岁候页同一套路）。
 *
 * @package custom
 */
if (!defined('SG_ROOT')) {
    require_once __DIR__ . '/functions.php';
}

/* ---------- 开关：关了就渲染一条安静的关闭态，不发 404 ---------- */
$sgZiOn = sg_zi_enabled();

/* ---------- 管理员重建入口（静默失败：非管理员当没看见） ---------- */
$sgZRebuilt = false;
if ($sgZiOn && isset($_GET['rebuild']) && sg_user_is_admin()) {
    sg_zi_rebuild();
    $sgZRebuilt = true;
}

/* ---------- 检字参数：只取第一个汉字，其余丢弃 ---------- */
$sgZRaw = trim((string) ($_GET['c'] ?? ''));
$sgZChar = '';
if ($sgZRaw !== '') {
    $sgZLen = strlen($sgZRaw);
    for ($sgZi = 0; $sgZi + 2 < $sgZLen; $sgZi++) {
        $sgZb = ord($sgZRaw[$sgZi]);
        if ($sgZb >= 0xe0 && $sgZb <= 0xef) {
            $sgZcp = (($sgZb & 0x0f) << 12) | ((ord($sgZRaw[$sgZi + 1]) & 0x3f) << 6) | (ord($sgZRaw[$sgZi + 2]) & 0x3f);
            if (($sgZcp >= 0x4e00 && $sgZcp <= 0x9fff) || ($sgZcp >= 0x3400 && $sgZcp <= 0x4dbf)) {
                $sgZChar = substr($sgZRaw, $sgZi, 3);
                break;
            }
        }
    }
}

/* ---------- 模式与数据 ---------- */
$sgZEntry = $sgZChar !== '' ? sg_zi_char($sgZChar) : null;
$sgZMode = ($sgZChar !== '' && $sgZEntry !== null) ? 'char' : 'hall';

/* ---------- 秘境态：?gate=1（彩蛋门，无任何导航入口，M5） ----------
   触发在访客本地（认满 3 个仅一见的字）；这里只负责在门后备好一封每日轮换的信。 */
$sgZGate = $sgZiOn && isset($_GET['gate']);
if ($sgZGate) {
    $sgZMode = 'gate';
}

$sgZIndex = $sgZiOn ? sg_zi_index() : ['rank' => [], 'chars' => []];
$sgZStats = $sgZiOn ? sg_zi_stats() : ['total' => 0, 'distinct' => 0, 'newThisYear' => 0, 'posts' => 0, 'notes' => 0];

/* ---------- 文档标题与 canonical（header.php 读取） ---------- */
if ($sgZMode === 'gate') {
    /* 秘境页不进索引：canonical 一律归到门厅 */
    $GLOBALS['SG_DOC_TITLE'] = '深夜来信 · 字海';
    $GLOBALS['SG_CANONICAL'] = sg_zi_url();
} elseif ($sgZMode === 'char') {
    $GLOBALS['SG_DOC_TITLE'] = $sgZChar . ' · 字海';
    $GLOBALS['SG_CANONICAL'] = sg_zi_url(['c' => $sgZChar]);
} else {
    $GLOBALS['SG_DOC_TITLE'] = '字海 · 检字馆';
    $GLOBALS['SG_CANONICAL'] = sg_zi_url();
}
$sgZSub = sg_page_sub($this, '以字为轴的站点档案');

$this->need('header.php');

/* ---------- 门厅数据 ---------- */
$sgZToday = $sgZiOn && $sgZMode === 'hall' ? sg_zi_today() : null;
$sgZTop = array_slice($sgZIndex['rank'] ?? [], 0, 20);
$sgZTopMax = $sgZTop ? (int) $sgZTop[0]['f'] : 0;

/* 仅一见：降序排行里 f=1 集中在尾部，一遍遍历取前 60 个展示位 + 总数 */
$sgZRares = [];
$sgZRCount = 0;
foreach (($sgZIndex['rank'] ?? []) as $sgZRow) {
    if ((int) $sgZRow['f'] === 1) {
        $sgZRCount++;
        if (count($sgZRares) < 60) {
            $sgZRares[] = (string) $sgZRow['c'];
        }
    }
}

/* ---------- 字卡数据 ---------- */
if ($sgZMode === 'char') {
    $sgZFreq = (int) $sgZEntry['freq'];
    $sgZRank = sg_zi_rank_of($sgZChar);
    $sgZIsRare = $sgZFreq === 1;
    $sgZActive = $sgZRank > 0 && $sgZRank <= 300;

    /* 逐年密度：最多取最近 10 个有记录的年份 */
    $sgZYears = (array) ($sgZEntry['years'] ?? []);
    ksort($sgZYears);
    if (count($sgZYears) > 10) {
        $sgZYears = array_slice($sgZYears, -10, null, true);
    }
    $sgZYearMax = $sgZYears ? max($sgZYears) : 0;

    $sgZFirst = (array) ($sgZEntry['first'] ?? []);
    $sgZLastTs = (int) ($sgZEntry['last'] ?? 0);
    $sgZQuotes = (array) ($sgZEntry['quotes'] ?? []);
    $sgZMates = (array) ($sgZEntry['mates'] ?? []);

    /* 例句内该字朱批着色：先整体转义再替换（汉字经 sg_e 不变形） */
    $sgZEm = static function (string $sentence) use ($sgZChar): string {
        $safe = sg_e($sentence);
        return str_replace(sg_e($sgZChar), '<mark class="sg-zi-hl">' . sg_e($sgZChar) . '</mark>', $safe);
    };
}
?>
<main id="sg-main" tabindex="-1" class="sg-main sg-main--pad sg-zi" data-sg-page="zi">
<?php if (!$sgZiOn) : ?>
  <?php /* 关闭态：纸面照铺，一条说明带一个回程 */ ?>
  <div class="sg-zi-sheet">
    <header class="sg-zi-head">
      <p class="sg-zi-kicker">Zi hall · 检字馆 · 已关闭</p>
      <h1 class="sg-zi-title">字海</h1>
      <p class="sg-zi-lead">这一页暂不开放。若你是站长，可在后台「字海 · 检字馆」一栏重新开启。</p>
    </header>
    <div class="sg-zi-empty">
      <p><a href="<?php echo sg_e(sg_page_url('zi')); ?>">刷新本页 →</a></p>
    </div>
  </div>
<?php else : ?>
  <div class="sg-zi-sheet">
<?php if ($sgZMode === 'hall') : ?>
  <?php /* ============ 门厅 ============ */ ?>
  <header class="sg-zi-head">
    <p class="sg-zi-kicker">Zi hall · 检字馆 · <?php echo sg_e($sgZSub); ?></p>
    <h1 class="sg-zi-title">字海</h1>
    <p class="sg-zi-lead">把整站文字当作一部字典。查一个字，看它何时初现、用在哪里、是否久违。</p>

    <form class="sg-zi-form" method="get" action="<?php echo sg_e(sg_zi_url()); ?>" role="search">
      <input class="sg-zi-field" type="text" name="c" maxlength="4"
        placeholder="输入一个字" aria-label="检字：输入一个汉字">
      <button class="sg-zi-go" type="submit">检字</button>
    </form>
    <p class="sg-zi-quick">
      <?php foreach (array_slice($sgZTop, 0, 5) as $sgZRow) : ?>
      <a class="sg-zi-quick-a" href="<?php echo sg_e(sg_zi_url(['c' => $sgZRow['c']])); ?>"><?php echo sg_e($sgZRow['c']); ?></a>
      <?php endforeach; ?>
      <?php foreach (array_slice($sgZRares, 0, 3) as $sgZRareCh) : ?>
      <a class="sg-zi-quick-a is-rare" href="<?php echo sg_e(sg_zi_url(['c' => $sgZRareCh])); ?>"><?php echo sg_e($sgZRareCh); ?></a>
      <?php endforeach; ?>
    </p>
    <?php if ($sgZRebuilt) : ?>
    <p class="sg-zi-rebuilt">字表已重建。</p>
    <?php endif; ?>
  </header>

  <?php /* 今日一字：确定性选取，无 JS 直出 */ ?>
  <?php if ($sgZToday && $sgZToday['quotes']) : ?>
  <section class="sg-zi-today" aria-label="今日一字">
    <a class="sg-zi-today-big" href="<?php echo sg_e(sg_zi_url(['c' => $sgZToday['char']])); ?>"
      aria-label="查看「<?php echo sg_e($sgZToday['char']); ?>」的字卡"><?php echo sg_e($sgZToday['char']); ?><?php if ((int) $sgZToday['freq'] >= 10) : ?><span class="sg-zi-dot" aria-hidden="true"></span><?php endif; ?></a>
    <div class="sg-zi-today-body">
      <p class="sg-zi-cap">今 日 一 字</p>
      <p class="sg-zi-today-quote"><?php
        /* 门厅例句同样朱批着色 */
        $sgZTq = (string) ($sgZToday['quotes'][0]['text'] ?? '');
        echo str_replace(sg_e($sgZToday['char']), '<mark class="sg-zi-hl">' . sg_e($sgZToday['char']) . '</mark>', sg_e($sgZTq)); ?></p>
      <p class="sg-zi-src"><?php echo sg_e(date('Y', sg_date_ts((int) ($sgZToday['first']['ts'] ?? 0))) . ' 年首见 · 共 ' . (int) $sgZToday['freq'] . ' 次'); ?></p>
      <a class="sg-zi-more" href="<?php echo sg_e(sg_zi_url(['c' => $sgZToday['char']])); ?>">进字卡 →</a>
    </div>
  </section>
  <?php endif; ?>

  <?php /* 高频二十：格内墨条高度随频次走 */ ?>
  <?php if ($sgZTop) : ?>
  <section class="sg-zi-rank" aria-label="高频二十字">
    <p class="sg-zi-cap">高 频 二 十</p>
    <div class="sg-zi-rank-grid">
      <?php foreach ($sgZTop as $sgZRow) : ?>
      <a class="sg-zi-rank-cell" href="<?php echo sg_e(sg_zi_url(['c' => $sgZRow['c']])); ?>">
        <span class="sg-zi-rank-ch"><?php echo sg_e($sgZRow['c']); ?></span>
        <span class="sg-zi-rank-bar"><i<?php echo $sgZTopMax > 0 && (int) $sgZRow['f'] >= $sgZTopMax * 0.45 ? ' class="is-hot"' : ''; ?> style="height:<?php echo $sgZTopMax > 0 ? max(6, (int) round((int) $sgZRow['f'] / $sgZTopMax * 100)) : 6; ?>%"></i></span>
        <span class="sg-zi-rank-n"><?php echo number_format((int) $sgZRow['f']); ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php /* 仅一见：冷字墙 */ ?>
  <?php if ($sgZRares) : ?>
  <section class="sg-zi-rare" aria-label="仅一见的字">
    <p class="sg-zi-cap">仅 一 见 · 共 <?php echo number_format($sgZRCount); ?> 字</p>
    <p class="sg-zi-rare-cloud">
      <?php foreach ($sgZRares as $sgZRareCh) : ?>
      <a href="<?php echo sg_e(sg_zi_url(['c' => $sgZRareCh])); ?>"><?php echo sg_e($sgZRareCh); ?></a>
      <?php endforeach; ?>
    </p>
  </section>
  <?php endif; ?>

  <?php /* 全站读数 */ ?>
  <section class="sg-zi-counts" aria-label="全站字量">
    <div><b class="sg-zi-count-v"><?php echo number_format($sgZStats['total']); ?></b><span>总字数</span></div>
    <div><b class="sg-zi-count-v"><?php echo number_format($sgZStats['distinct']); ?></b><span>不同字数</span></div>
    <div><b class="sg-zi-count-v sg-zi-count-warm"><?php echo number_format($sgZStats['newThisYear']); ?></b><span>今年新见字</span></div>
  </section>

<?php elseif ($sgZMode === 'gate') : ?>
  <?php /* ============ 秘境 · 深夜来信（恒夜色，与昼夜主题无关） ============ */ ?>
  <?php $sgZLetter = sg_zi_gate_letter(); ?>
  <section class="sg-zi-gate-letter" data-sg-zi-gate-letter aria-label="深夜来信">
    <p class="sg-zi-gate-cap">秘 境 · 深 夜 来 信</p>
    <h1 class="sg-zi-gate-title">致走到这里的人</h1>
    <?php if ($sgZLetter) : ?>
    <div class="sg-zi-gate-body">
      <p class="sg-zi-gate-line">你在字海里认出了三个只出现一次的字。作为回礼，这里有一个连导航都不会收录的字：</p>
      <p class="sg-zi-gate-char"><a href="<?php echo sg_e($sgZLetter['charUrl']); ?>"><?php echo sg_e($sgZLetter['ch']); ?></a></p>
      <p class="sg-zi-gate-meta">全站仅此一见 · <?php echo sg_e($sgZLetter['year']); ?> 年《<?php echo sg_e($sgZLetter['title']); ?>》落笔</p>
      <blockquote class="sg-zi-gate-quote"><?php echo sg_e($sgZLetter['quote']); ?></blockquote>
      <p class="sg-zi-gate-line">出门之后它就会变回灰青色。此页导航里永远不会有，朋友问起时，请让他自己去冷字墙里认字。</p>
      <a class="sg-zi-gate-src" href="<?php echo sg_e($sgZLetter['url'] . (str_contains($sgZLetter['url'], '?') ? '&' : '?') . 'hl=' . rawurlencode($sgZLetter['ch'])); ?>">去读它的孤证原文 →</a>
    </div>
    <?php else : ?>
    <p class="sg-zi-gate-closed">门开了，但字海里暂时没有「仅一见」的字，信无处落笔。</p>
    <?php endif; ?>
    <div class="sg-zi-gate-sign">
      <span class="sg-zi-gate-name">空璃 手书</span>
      <span class="sg-zi-gate-seal" aria-hidden="true">密</span>
    </div>
    <p class="sg-zi-gate-locked">这扇门还没有为你打开：去门厅的冷字墙，认得三个只出现一次的字再来。</p>
    <p class="sg-zi-gate-backrow"><a class="sg-zi-gate-back" href="<?php echo sg_e(sg_zi_url()); ?>">← 回门厅</a></p>
  </section>

<?php else : ?>
  <?php /* ============ 字卡 ============ */ ?>
  <header class="sg-zi-head sg-zi-head--card">
    <a class="sg-zi-back" href="<?php echo sg_e(sg_zi_url()); ?>">← 回门厅</a>
    <p class="sg-zi-kicker">Zi hall · <?php echo sg_e($sgZSub); ?></p>
  </header>

  <section class="sg-zi-card<?php echo $sgZIsRare ? ' is-rare' : ''; ?>" aria-label="字卡"<?php echo $sgZIsRare ? ' data-sg-zi-rare-char="' . sg_e($sgZChar) . '"' : ''; ?>>
    <div class="sg-zi-big" aria-hidden="true"><?php echo sg_e($sgZChar); ?><?php if ($sgZActive) : ?><span class="sg-zi-dot"></span><?php endif; ?></div>
    <span class="sg-zi-big-sr"><?php echo sg_e($sgZChar); ?></span>

    <div class="sg-zi-meta">
      <dl>
        <?php if ($sgZFirst) : ?>
        <div class="sg-zi-row"><dt>初现</dt><dd><a class="sg-zi-src-ln" href="<?php echo sg_e($sgZFirst['url'] . (str_contains($sgZFirst['url'] ?? '', '?') ? '&' : '?') . 'hl=' . rawurlencode($sgZChar)); ?>"><?php echo sg_e(date('Y 年 n 月', sg_date_ts((int) $sgZFirst['ts'])) . ' · ' . (string) $sgZFirst['title']); ?></a></dd></div>
        <?php endif; ?>
        <div class="sg-zi-row"><dt>总频次</dt><dd><?php if ($sgZIsRare) : ?><b class="sg-zi-warm">仅一见</b><?php else : ?><b class="sg-zi-warm"><?php echo number_format($sgZFreq); ?></b> 次 · 全站第 <?php echo number_format($sgZRank); ?> 位<?php endif; ?></dd></div>
        <?php if (!$sgZIsRare && $sgZYears) : ?>
        <div class="sg-zi-row"><dt>逐年密度</dt><dd><span class="sg-zi-freq" aria-label="逐年频次">
          <?php $sgZYearKeys = array_keys($sgZYears); foreach ($sgZYears as $sgZY => $sgZN) : ?>
          <i<?php echo $sgZYearMax > 0 && (int) $sgZN >= $sgZYearMax * 0.7 ? ' class="is-hot"' : ''; ?> title="<?php echo (int) $sgZY; ?> 年 <?php echo (int) $sgZN; ?> 次" style="height:<?php echo $sgZYearMax > 0 ? max(8, (int) round((int) $sgZN / $sgZYearMax * 100)) : 8; ?>%"></i>
          <?php endforeach; ?>
        </span><span class="sg-zi-freq-span"><?php echo (int) $sgZYearKeys[0]; ?>–<?php echo (int) end($sgZYearKeys); ?></span></dd></div>
        <?php endif; ?>
        <?php if (!$sgZIsRare && $sgZLastTs > 0 && $sgZLastTs !== (int) ($sgZFirst['ts'] ?? 0)) : ?>
        <div class="sg-zi-row"><dt>最近出现</dt><dd><?php echo sg_e(date('Y 年 n 月', sg_date_ts($sgZLastTs))); ?></dd></div>
        <?php endif; ?>
      </dl>
    </div>
  </section>

  <?php /* 出处例句 */ ?>
  <?php if ($sgZQuotes) : ?>
  <section class="sg-zi-quotes" aria-label="出处">
    <p class="sg-zi-cap"><?php echo $sgZIsRare ? '孤 证' : '出 处'; ?><?php echo count($sgZQuotes) < 12 ? '' : ' · 前 ' . count($sgZQuotes) . ' 条'; ?></p>
    <?php foreach ($sgZQuotes as $sgZQ) : ?>
    <blockquote>
      <?php echo $sgZEm((string) $sgZQ['text']); ?>
      <cite><a class="sg-zi-src-ln" href="<?php echo sg_e((string) $sgZQ['url'] . (str_contains((string) $sgZQ['url'], '?') ? '&' : '?') . 'hl=' . rawurlencode($sgZChar)); ?>"><?php echo sg_e('《' . (string) $sgZQ['title'] . '》' . date(' · Y', sg_date_ts((int) $sgZQ['ts']))); ?></a></cite>
    </blockquote>
    <?php endforeach; ?>
    <?php if ($sgZIsRare) : ?>
    <p class="sg-zi-verdict">全站 <b>仅此一见</b> · <?php echo sg_e(date('Y 年', sg_date_ts((int) ($sgZFirst['ts'] ?? 0)))); ?>落笔</p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php /* 共现字 */ ?>
  <?php if ($sgZMates && !$sgZIsRare) : ?>
  <section class="sg-zi-mates" aria-label="常与同句的字">
    <p class="sg-zi-cap">常 与 同 句</p>
    <p class="sg-zi-mate-chips">
      <?php foreach (array_keys($sgZMates) as $sgZMate) : ?>
      <a class="sg-zi-chip" href="<?php echo sg_e(sg_zi_url(['c' => $sgZMate])); ?>"><?php echo sg_e($sgZMate); ?></a>
      <?php endforeach; ?>
    </p>
  </section>
  <?php endif; ?>

<?php endif; ?>
<?php if ($sgZMode !== 'gate') : ?>
  <footer class="sg-zi-colophon">
    <button type="button" class="sg-zi-gate" data-sg-zi-gate data-sg-zi-gate-url="<?php echo sg_e(sg_zi_url(['gate' => 1])); ?>" hidden aria-label="一枚不起眼的印">密</button>
    <details class="sg-zi-note">
      <summary>口径与边界</summary>
      <div class="sg-zi-note-body">
        <p>统计范围：已发布文章 <?php echo (int) $sgZStats['posts']; ?> 篇与碎语 <?php echo (int) $sgZStats['notes']; ?> 条；独立页面、相册说明与代码块不计。</p>
        <p>只统计汉字（含扩展 A 区）；例句按句读切分，每篇每字取一句、每字至多十二条，最早优先。</p>
        <p>「常与同句」只对全站频次前八百的字计算；「仅一见」即十年只出现过一次的字。</p>
        <p>字表缓存于服务器，发文或改文后首个访问自动重建，无需手动刷新。</p>
      </div>
    </details>
  </footer>
<?php endif; /* gate 不渲染跋 */ ?>
  </div><?php /* /.sg-zi-sheet 纸面到此合上（跋已收入纸面内） */ ?>
<?php endif; ?>
</main>
<?php $this->need('footer.php'); ?>
