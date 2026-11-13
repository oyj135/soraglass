<?php
/**
 * 「AI 日报」—— 让模型每天从候选条目里挑一份选刊。
 *
 * 设计取向（很重要，改动前先读）：
 *  1. **只让模型做「挑选 + 改写」，不让它编造事实。** 候选条目由 RSS 抓取提供，
 *     模型返回的必须是候选的**序号**，标题与链接一律用我们自己的原文，
 *     模型只补一句摘要与推荐理由。这样即使模型胡言乱语，页面上也不会出现一个不存在的链接。
 *     第二十五轮起**不再支持「让模型自己联网搜」**：那需要按次计费的服务商搜索能力，
 *     且链接可信度还得再套一层白名单；只保留候选源模式，简单、免费、完全可控。
 *  2. **不阻塞页面。** 没有 Key / 超时 / 返回不是 JSON，一律不输出这块内容，
 *     只在管理员视角给一行诊断。
 *  3. **一天一份，落盘缓存。** 缓存键含主题、模型、日期，凌晨自然失效；
 *     管理员访问时带 `?sg_ai=refresh` 可强制重生成。
 *
 * 依赖：lib/fields.php 的 sg_http_get()、lib/config.php 的 sg_opt()/sg_user_is_admin()。
 */

/**
 * 主流服务商预设：选一个就能用，接口地址与模型名都可以再手动覆盖。
 *
 * format 决定请求体与响应解析：
 *   openai    —— /chat/completions + messages[]（OpenAI、DeepSeek、智谱、Kimi、通义、豆包、MiniMax 等兼容）
 *   anthropic —— /v1/messages + system/messages（Claude）
 *   gemini    —— /v1beta/models/{model}:generateContent（Google）
 *
 * 这些默认值只用于「留空时兜底」，服务商改名/改路径时在后台直接覆盖即可。
 */
function sg_ai_providers(): array
{
    return [
        'openai'     => ['label' => 'OpenAI（兼容通用）', 'endpoint' => 'https://api.openai.com/v1/chat/completions', 'model' => 'gpt-4o-mini', 'format' => 'openai'],
        'deepseek'   => ['label' => 'DeepSeek', 'endpoint' => 'https://api.deepseek.com/v1/chat/completions', 'model' => 'deepseek-chat', 'format' => 'openai'],
        'zhipu'      => ['label' => '智谱 GLM', 'endpoint' => 'https://open.bigmodel.cn/api/paas/v4/chat/completions', 'model' => 'glm-4-flash', 'format' => 'openai'],
        'moonshot'   => ['label' => 'Moonshot Kimi', 'endpoint' => 'https://api.moonshot.cn/v1/chat/completions', 'model' => 'moonshot-v1-8k', 'format' => 'openai'],
        'qwen'       => ['label' => '通义千问', 'endpoint' => 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions', 'model' => 'qwen-plus', 'format' => 'openai'],
        'doubao'     => ['label' => '火山方舟（豆包）', 'endpoint' => 'https://ark.cn-beijing.volces.com/api/v3/chat/completions', 'model' => 'doubao-pro-32k', 'format' => 'openai'],
        'minimax'    => ['label' => 'MiniMax', 'endpoint' => 'https://api.minimax.chat/v1/text/chatcompletion_v2', 'model' => 'abab6.5s-chat', 'format' => 'openai'],
        'claude'     => ['label' => 'Anthropic Claude', 'endpoint' => 'https://api.anthropic.com/v1/messages', 'model' => 'claude-3-5-sonnet-latest', 'format' => 'anthropic'],
        'gemini'     => ['label' => 'Google Gemini', 'endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent', 'model' => 'gemini-1.5-flash', 'format' => 'gemini'],
        'perplexity' => ['label' => 'Perplexity', 'endpoint' => 'https://api.perplexity.ai/chat/completions', 'model' => 'sonar', 'format' => 'openai'],
        'custom'     => ['label' => '自定义（OpenAI 兼容）', 'endpoint' => '', 'model' => '', 'format' => 'openai'],
    ];
}

/**
 * 修正常见误填的接口地址。
 *
 * 三种情况：①只有域名（`https://example.com` / `https://example.com/`）→ 补该服务商默认路径；
 * ②Gemini 少了 `:generateContent` → 补上；③其它原样返回（中转站的自定义路径不能被改）。
 */
function sg_ai_fix_endpoint(string $endpoint, string $preset): string
{
    $endpoint = trim($endpoint);
    if (preg_match('#^https?://#i', $endpoint) !== 1) {
        return $endpoint;
    }

    $path = (string) parse_url($endpoint, PHP_URL_PATH);
    if ($path === '' || $path === '/') {
        // 只填了域名：换回预设的完整地址（域名叫用户填的那个）
        $presetHost = (string) parse_url($preset, PHP_URL_HOST);
        $presetPath = (string) parse_url($preset, PHP_URL_PATH);
        $userHost = (string) parse_url($endpoint, PHP_URL_HOST);
        if ($presetPath !== '' && $userHost !== '') {
            $scheme = (string) parse_url($endpoint, PHP_URL_SCHEME) ?: 'https';
            $port = parse_url($endpoint, PHP_URL_PORT);
            return $scheme . '://' . $userHost . ($port ? ':' . $port : '') . $presetPath;
        }
        return $endpoint;
    }

    // Gemini：地址里必须有 :generateContent 才算完整
    if (strpos($preset, ':generateContent') !== false && strpos($endpoint, ':generateContent') === false) {
        $endpoint = rtrim($endpoint, '/') . ':generateContent';
    }
    return $endpoint;
}

/** 配置：服务商、接口、Key、模型、主题、条数、候选 RSS */
function sg_ai_cfg(): array
{
    $providers = sg_ai_providers();
    $provider = trim((string) sg_opt('sgAiProvider', 'openai'));
    if (!isset($providers[$provider])) {
        $provider = 'openai';
    }
    $preset = $providers[$provider];

    $endpoint = trim((string) sg_opt('sgAiEndpoint', ''));
    if ($endpoint === '') {
        $endpoint = (string) $preset['endpoint'];
    } else {
        /* 常见误填：只贴了域名（`https://generativelanguage.googleapis.com`）。
           POST 到站点根只会拿到 404 HTML，看起来就是「填了没反应」——
           这里把缺失的路径补成该服务商的默认路径，域名仍以站长填的为准。 */
        $endpoint = sg_ai_fix_endpoint($endpoint, (string) $preset['endpoint']);
    }
    $model = trim((string) sg_opt('sgAiModel', ''));
    if ($model === '') {
        $model = (string) $preset['model'];
    }

    return [
        'provider' => $provider,
        'format'   => (string) $preset['format'],
        'endpoint' => $endpoint,
        'key'      => trim((string) sg_opt('sgAiKey', '')),
        'model'    => $model,
        'topic'    => trim((string) sg_opt('sgAiTopic', '')),
        'count'    => max(1, min(12, (int) sg_opt('sgAiCount', '6'))),
        'feeds'    => array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) sg_opt('sgAiFeeds', '')) ?: []))),
    ];
}

/** 是否具备生成条件（只有填了 Key 与主题才算开启） */
function sg_ai_enabled(): bool
{
    $cfg = sg_ai_cfg();
    return $cfg['key'] !== '' && $cfg['topic'] !== '' && $cfg['endpoint'] !== '';
}

/** 失败原因（管理员诊断行用） */
function sg_ai_error(): string
{
    return (string) ($GLOBALS['SG_AI_ERROR'] ?? '');
}

/** 记录失败原因，只留第一条（后面的多半是同一原因） */
function sg_ai_fail(string $message): void
{
    if (sg_ai_error() === '') {
        $GLOBALS['SG_AI_ERROR'] = $message;
        // 失败也落盘 5 分钟：否则每个访客都要先等一次超时
        @file_put_contents(sg_ai_cache_file() . '.fail', $message);
    }
}

/** 同一套配置的固定前缀（不含日期），用来把「往期」串起来 */
function sg_ai_cache_prefix(): string
{
    $cfg = sg_ai_cfg();
    return substr(md5(implode('|', [
        $cfg['provider'],
        $cfg['endpoint'],
        $cfg['model'],
        $cfg['topic'],
        (string) $cfg['count'],
        implode(',', $cfg['feeds']),
    ])), 0, 16);
}

/** 当天的缓存文件路径（`soraglass-ai-<前缀>-<Ymd>.json`） */
function sg_ai_cache_file(): string
{
    return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
        . 'soraglass-ai-' . sg_ai_cache_prefix() . '-' . date('Ymd') . '.json';
}

/**
 * 往期：同一套配置下、今天之前的缓存文件，按日期从新到旧。
 *
 * 日报每天只在有人访问首页时生成一次，所以「往期」= 历史上真的被生成并被访问过的那些天。
 */
function sg_ai_archive(int $limit = 6): array
{
    $pattern = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR
        . 'soraglass-ai-' . sg_ai_cache_prefix() . '-*.json';
    $files = glob($pattern);
    if (!$files) {
        return [];
    }

    $today = date('Y.m.d');
    $issues = [];
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            continue;
        }
        $issue = json_decode($raw, true);
        if (!is_array($issue) || empty($issue['items']) || ($issue['date'] ?? '') === $today) {
            continue;
        }
        $issues[] = $issue;
    }

    usort($issues, static fn($a, $b) => strcmp((string) $b['date'], (string) $a['date']));

    return array_slice($issues, 0, $limit);
}

/** 首页用：今天这一期 + 往期（新 → 旧） */
function sg_ai_issues(int $limit = 5): array
{
    $today = sg_ai_issue(false);
    $list = $today ? [$today] : [];
    foreach (sg_ai_archive($limit) as $issue) {
        $list[] = $issue;
    }
    return $list;
}

/**
 * 抓取 RSS / Atom 候选条目。
 *
 * 只取标题、链接、摘要、时间；解析失败就跳过这一路源，不让整份日报挂掉。
 * 返回 [{title, summary, source, url, date}]。
 */
function sg_ai_feed_items(int $limit = 30): array
{
    $cfg = sg_ai_cfg();
    $out = [];
    $seen = [];

    foreach ($cfg['feeds'] as $feed) {
        if (preg_match('#^https?://#i', $feed) !== 1) {
            continue;
        }
        $xml = sg_http_get($feed, ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml'], 8);
        if ($xml === '') {
            continue;
        }

        $host = (string) parse_url($feed, PHP_URL_HOST);

        /* 先试 SimpleXML（有扩展时最稳），没有就退回正则 —— 两者都不完美，
           但这个函数的目标只是「拿到候选」，解析不出就当这路源没内容。 */
        $nodes = [];
        if (function_exists('simplexml_load_string') && stripos($xml, '<') !== false) {
            $prev = libxml_use_internal_errors(true);
            $doc = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if ($doc !== false) {
                foreach ($doc->xpath('//item') ?: [] as $node) {
                    $nodes[] = [
                        'title' => (string) $node->title,
                        'link'  => (string) $node->link,
                        'desc'  => (string) ($node->description ?? ''),
                        'date'  => (string) ($node->pubDate ?? ''),
                    ];
                }
                foreach ($doc->xpath('//*[local-name()="entry"]') ?: [] as $node) {
                    $link = '';
                    foreach ($node->link ?: [] as $l) {
                        $rel = (string) $l['rel'];
                        if ($rel === '' || $rel === 'alternate') {
                            $link = (string) $l['href'];
                            break;
                        }
                    }
                    $nodes[] = [
                        'title' => (string) $node->title,
                        'link'  => $link,
                        'desc'  => (string) ($node->summary ?? $node->content ?? ''),
                        'date'  => (string) ($node->updated ?? $node->published ?? ''),
                    ];
                }
            }
        }

        if (!$nodes) {
            if (preg_match_all('#<item[\s>].*?</item>#is', $xml, $m)) {
                foreach ($m[0] as $chunk) {
                    $nodes[] = [
                        'title' => sg_ai_tag($chunk, 'title'),
                        'link'  => sg_ai_tag($chunk, 'link'),
                        'desc'  => sg_ai_tag($chunk, 'description'),
                        'date'  => sg_ai_tag($chunk, 'pubDate'),
                    ];
                }
            } elseif (preg_match_all('#<entry[\s>].*?</entry>#is', $xml, $m)) {
                foreach ($m[0] as $chunk) {
                    $nodes[] = [
                        'title' => sg_ai_tag($chunk, 'title'),
                        'link'  => sg_ai_href($chunk),
                        'desc'  => sg_ai_tag($chunk, 'summary'),
                        'date'  => sg_ai_tag($chunk, 'updated'),
                    ];
                }
            }
        }

        foreach ($nodes as $node) {
            $title = trim(html_entity_decode(strip_tags($node['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = trim($node['link']);
            if ($title === '' || $url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $summary = trim(html_entity_decode(strip_tags($node['desc']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $summary = trim((string) preg_replace('/\s+/u', ' ', $summary));
            $out[] = [
                'title'   => $title,
                'summary' => mb_substr($summary, 0, 220),
                'source'  => $host,
                'url'     => $url,
                'date'    => sg_ai_date($node['date']),
            ];
        }
    }

    usort($out, static fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
    return array_slice($out, 0, max(4, $limit));
}

/** 从一段 XML 片段里取标签文本 */
function sg_ai_tag(string $chunk, string $tag): string
{
    if (preg_match('#<' . $tag . '[^>]*>(.*?)</' . $tag . '>#is', $chunk, $m)) {
        return trim((string) $m[1]);
    }
    return '';
}

/** Atom 的 link 写在属性里：<link rel="alternate" href="…"/> */
function sg_ai_href(string $chunk): string
{
    if (preg_match('#<link[^>]+href=["\']([^"\']+)["\']#i', $chunk, $m)) {
        return trim((string) $m[1]);
    }
    return '';
}

/** 各种日期格式统一成 Y.m.d；解析不出来就留空 */
function sg_ai_date(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    $ts = strtotime($raw);
    if ($ts === false || $ts <= 0) {
        return '';
    }
    return date('Y.m.d', $ts);
}

/**
 * 把接口返回的 error.message 变成一句站长能照着改的提示。
 *
 * 最常见的两种「填了没反应」：模型名不存在（404 / not found）、Key 无效（401）。
 */
function sg_ai_api_error(string $msg): string
{
    $msg = trim($msg);
    $lower = strtolower($msg);
    $text = '模型接口报错：' . mb_substr($msg, 0, 140);

    if (strpos($lower, 'not found') !== false || strpos($lower, 'does not exist') !== false
        || strpos($lower, 'not supported') !== false || strpos($lower, 'unknown model') !== false) {
        $text .= '（多半是「模型」不是该服务商真实存在的模型名，去控制台抄一个）';
    } elseif (strpos($lower, 'api key') !== false || strpos($lower, 'unauthorized') !== false
        || strpos($lower, 'permission') !== false || strpos($lower, 'invalid key') !== false) {
        $text .= '（检查 API Key 是否有效、是否属于所选服务商）';
    } elseif (strpos($lower, 'quota') !== false || strpos($lower, 'rate limit') !== false) {
        $text .= '（额度或频率到上限了，稍后再试或换 Key）';
    }
    return $text;
}

/**
 * 按服务商的协议发一次请求，返回模型给出的纯文本（失败写 SG_AI_ERROR 并返回空串）。
 *
 * 三家协议差异只在「请求体怎么拼」和「正文从哪取」：
 *   openai    → payload.messages + choices[0].message.content
 *   anthropic → payload.system / messages + content[0].text
 *   gemini    → URL 里带模型名与 key，body.contents + candidates[0].content.parts[0].text
 */
function sg_ai_request(string $system, string $user, array $cfg): string
{
    $format = $cfg['format'];

    if ($format === 'anthropic') {
        $payload = [
            'model'       => $cfg['model'],
            'max_tokens'  => 2048,
            'temperature' => 0.4,
            'system'      => $system,
            'messages'    => [['role' => 'user', 'content' => $user]],
        ];
        $body = sg_http_post_json($cfg['endpoint'], $payload, [
            'x-api-key: ' . $cfg['key'],
            'anthropic-version: 2023-06-01',
        ], 30);
        if ($body === '') {
            sg_ai_fail('模型接口没有响应（检查接口地址 / Key / 服务器能否出网）');
            return '';
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            sg_ai_fail('模型接口返回的不是 JSON：' . mb_substr($body, 0, 120));
            return '';
        }
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? (string) ($data['error']['message'] ?? '未知错误') : (string) $data['error'];
            sg_ai_fail(sg_ai_api_error($msg));
            return '';
        }
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (!is_array($block)) {
                continue;
            }
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        return $text;
    }

    if ($format === 'gemini') {
        $endpoint = str_replace('{model}', rawurlencode($cfg['model']), $cfg['endpoint']);
        $sep = strpos($endpoint, '?') === false ? '?' : '&';
        $endpoint .= $sep . 'key=' . rawurlencode($cfg['key']);
        $payload = [
            'contents'         => [['parts' => [['text' => $system . "\n\n" . $user]]]],
            'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json'],
        ];
        $body = sg_http_post_json($endpoint, $payload, ['Content-Type: application/json'], 30);
        if ($body === '') {
            sg_ai_fail('模型接口没有响应（检查接口地址 / Key / 服务器能否出网）');
            return '';
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            sg_ai_fail('模型接口返回的不是 JSON：' . mb_substr($body, 0, 120));
            return '';
        }
        if (isset($data['error'])) {
            $msg = is_array($data['error']) ? (string) ($data['error']['message'] ?? '未知错误') : (string) $data['error'];
            sg_ai_fail(sg_ai_api_error($msg));
            return '';
        }
        $text = '';
        foreach ((array) ($data['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (is_array($part)) {
                $text .= (string) ($part['text'] ?? '');
            }
        }
        return $text;
    }

    // 默认：OpenAI 兼容（DeepSeek / 智谱 / Moonshot / 通义 / 豆包 / MiniMax 都走这里）
    // 不发 response_format：各家兼容度不一（有的直接 400），我们靠提示词 + sg_ai_json_block() 兜住围栏
    $payload = [
        'model'       => $cfg['model'],
        'temperature' => 0.4,
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ],
    ];
    $body = sg_http_post_json($cfg['endpoint'], $payload, [
        'Authorization: Bearer ' . $cfg['key'],
    ], 30);
    if ($body === '') {
        sg_ai_fail('模型接口没有响应（检查接口地址 / Key / 服务器能否出网）');
        return '';
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        sg_ai_fail('模型接口返回的不是 JSON：' . mb_substr($body, 0, 120));
        return '';
    }
    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? (string) ($data['error']['message'] ?? '未知错误') : (string) $data['error'];
        sg_ai_fail(sg_ai_api_error($msg));
        return '';
    }

    // 只取正文；不再解析 citations / search_results（联网模式已下线，见第二十五轮）
    return (string) ($data['choices'][0]['message']['content'] ?? '');
}

/**
 * 请求模型：给它候选，让它挑 N 条并写摘要。
 * 返回规范化后的选刊期号，失败返回 null。
 */
function sg_ai_issue(bool $force = false): ?array
{
    if (!sg_ai_enabled()) {
        return null;
    }

    $cacheFile = sg_ai_cache_file();
    if (!$force && is_file($cacheFile) && (time() - (int) @filemtime($cacheFile)) < 86400) {
        $cached = json_decode((string) @file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['items'])) {
            return $cached;
        }
    }

    // 刚失败过就别再试了：同一个接口连续超时会把首页拖成 5 秒
    $failFile = $cacheFile . '.fail';
    if (!$force && is_file($failFile) && (time() - (int) @filemtime($failFile)) < 300) {
        $GLOBALS['SG_AI_ERROR'] = (string) @file_get_contents($failFile);
        return null;
    }

    $cfg = sg_ai_cfg();
    $candidates = sg_ai_feed_items(30);
    if (!$candidates) {
        sg_ai_fail('没有候选条目：在「AI 日报 · 候选源（RSS）」里填至少一个能访问的 RSS/Atom');
        return null;
    }

    $indexed = [];
    foreach ($candidates as $i => $item) {
        $indexed[] = [
            'i'       => $i + 1,
            'title'   => $item['title'],
            'summary' => $item['summary'],
            'source'  => $item['source'],
            'date'    => $item['date'],
        ];
    }

    $system = '你是一名中文科技编辑。你只做两件事：从给定候选里挑出与主题最相关的条目，并为每条写一句中文摘要。'
        . '严禁编造候选之外的内容、链接或数据；严禁改写标题。只输出 JSON，不要任何解释文字。';

    $user = "今天的主题：{$cfg['topic']}\n"
        . '请从下面的候选里挑出最合适的 ' . $cfg['count'] . " 条，按重要性排序。\n"
        . "输出 JSON，结构固定：\n"
        . '{"title":"10-20 字的当日标题","desc":"30-60 字导语，概括今天这批内容的共同点",'
        . '"notice":"一句给读者的提示","items":[{'
        . '"i":候选序号,'
        . '"channel":"ai 或 paper",'
        . '"titleZh":"24 字以内的中文标题，忠实原意、不加事实",'
        . '"summary":"40-70 字中文摘要，说清这条讲了什么",'
        . '"points":["三条中文要点，每条 12-24 字，必须是原文里的事实"],'
        . '"note":"一句写在页边的话，可以是取舍理由或阅读提示",'
        . '"tags":["两个到三个中文短标签"],'
        . '"minutes":1到9的整数，表示读完原文大约几分钟'
        . '}]}'
        . "\nchannel 规则：偏产品/工程/行业动向的填 ai；论文、评测、研究发布填 paper。"
        . "\n两栏都要有：如果候选里确实没有论文类内容，把最接近研究的那一条放进 paper，不要留空。"
        . "\npoints 必须来自候选摘要里的事实，不要补充候选里没有的信息。"
        . "\n\n候选：\n"
        . json_encode($indexed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $content = sg_ai_request($system, $user, $cfg);
    if ($content === '') {
        return null;
    }

    $picked = json_decode(sg_ai_json_block($content), true);
    if (!is_array($picked) || empty($picked['items'])) {
        sg_ai_fail('模型没有返回可用的 JSON 结构');
        return null;
    }

    $items = [];
    foreach ((array) $picked['items'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $index = (int) ($row['i'] ?? 0);
        if ($index < 1 || !isset($candidates[$index - 1])) {
            continue;
        }
        $base = $candidates[$index - 1];
        $summary = trim((string) ($row['summary'] ?? ''));
        $note = trim((string) ($row['note'] ?? $row['why'] ?? ''));
        $titleZh = trim((string) ($row['titleZh'] ?? ''));
        $channel = (string) ($row['channel'] ?? 'ai');

        /* 要点：数组则逐条清洗，字符串则按行/分号拆 */
        $points = [];
        $rawPoints = $row['points'] ?? [];
        if (is_string($rawPoints)) {
            $rawPoints = preg_split('/[\r\n;；]+/', $rawPoints) ?: [];
        }
        foreach ((array) $rawPoints as $point) {
            $point = trim((string) $point);
            if ($point !== '') {
                $points[] = $point;
            }
        }
        $points = array_slice($points, 0, 4);

        $tags = [];
        foreach ((array) ($row['tags'] ?? []) as $tag) {
            $tag = trim((string) $tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_slice($tags, 0, 3);

        $minutes = (int) ($row['minutes'] ?? 0);
        $minutes = max(1, min(9, $minutes ?: 1));

        $items[] = [
            'id'         => md5((string) $base['url']),
            'channel'    => $channel === 'paper' ? 'paper' : 'ai',
            'title'      => (string) $base['title'],
            'titleZh'    => $titleZh !== '' ? mb_substr($titleZh, 0, 40) : '',
            'summary'    => $summary !== '' ? $summary : (string) $base['summary'],
            'source'     => (string) $base['source'],
            'sourceDate' => (string) $base['date'],
            'url'        => (string) $base['url'],
            'note'       => $note,
            'points'     => $points,
            'tags'       => $tags,
            'minutes'    => $minutes,
        ];
        if (count($items) >= $cfg['count']) {
            break;
        }
    }

    if (!$items) {
        sg_ai_fail('模型挑选的条目都对不上候选序号');
        return null;
    }

    /* 模型常把十条全塞进「AI 动态」，研究论文那栏就空着。
       这里按「来源域名 / 标题关键词 / 摘要关键词」兜底挑一条最近的挪过去，
       找不到就让它空着（模板会隐藏空栏，不会出现「这一栏还没有条目」）。 */
    $hasPaper = false;
    foreach ($items as $item) {
        if ($item['channel'] === 'paper') {
            $hasPaper = true;
            break;
        }
    }
    if (!$hasPaper && count($items) > 1) {
        foreach ($items as $i => $item) {
            $haystack = strtolower($item['source'] . ' ' . $item['title'] . ' ' . $item['summary']);
            if (preg_match('/arxiv|paper|research|study|benchmark|论文|研究|评测|基准|预印本/', $haystack) === 1) {
                $items[$i]['channel'] = 'paper';
                break;
            }
        }
    }

    $issue = [
        'date'    => date('Y.m.d'),
        'weekday' => sg_ai_weekday(),
        'label'   => 'AI 日报',
        'kicker'  => 'AI CURATED',
        'title'   => trim((string) ($picked['title'] ?? '')) ?: $cfg['topic'],
        'desc'    => trim((string) ($picked['desc'] ?? '')),
        'cover'   => sg_ai_cover(),
        'notice'  => trim((string) ($picked['notice'] ?? '')) ?: '本期由模型从候选源中挑选，标题与链接均来自原文。',
        'items'   => $items,
    ];

    @file_put_contents($cacheFile, json_encode($issue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @unlink($cacheFile . '.fail');
    return $issue;
}


/** 模型偶尔会裹一层 ```json，剥掉再解析 */
function sg_ai_json_block(string $content): string
{
    $content = trim($content);
    if (strpos($content, '```') !== false) {
        if (preg_match('/```(?:json)?\s*(.*?)```/is', $content, $m)) {
            $content = trim((string) $m[1]);
        }
    }
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $content = substr($content, $start, $end - $start + 1);
    }
    return $content;
}

/**
 * AI 日报的封面：模型不会给图，所以按「后台配的日报背景 → 最新相册封面 → 主题自带图」取
 */
function sg_ai_cover(): string
{
    $cover = trim((string) sg_opt('sgBriefingBg', ''));
    if ($cover === '') {
        $albums = sg_albums();
        $cover = trim((string) ($albums[0]['coverThumb'] ?? ($albums[0]['cover'] ?? '')));
    }
    return $cover !== '' ? $cover : 'dist/img/photos/briefing-3.jpg';
}

function sg_ai_weekday(): string
{
    $map = ['日', '一', '二', '三', '四', '五', '六'];
    return '星期' . $map[(int) date('w')];
}

/** 清掉今天的缓存（管理员点「重新生成」时用） */
function sg_ai_flush(): void
{
    $file = sg_ai_cache_file();
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * 首页要用的入口：拿到当期 AI 选刊（失败返回 null，页面自动退回静态选刊）。
 * 管理员带 ?sg_ai=refresh 访问时强制重生成。
 */
function sg_ai_briefing(): ?array
{
    if (!sg_ai_enabled()) {
        return null;
    }
    $force = false;
    if (sg_user_is_admin() && isset($_GET['sg_ai']) && (string) $_GET['sg_ai'] === 'refresh') {
        sg_ai_flush();
        $force = true;
    }
    return sg_ai_issue($force);
}

/** 音乐页那套诊断行的同款：管理员才看得到 */
function sg_ai_diag(): string
{
    $cfg = sg_ai_cfg();
    if (!sg_ai_enabled()) {
        return '';
    }
    $issue = sg_ai_issue(false);
    if ($issue) {
        return 'AI 日报：' . $issue['title'] . '（' . count($issue['items']) . ' 条，模型 ' . $cfg['model'] . '，主题「' . $cfg['topic'] . '」）';
    }
    return 'AI 日报未生成：' . sg_ai_error();
}