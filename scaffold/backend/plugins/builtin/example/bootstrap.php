<?php

/**
 * 内置示例插件（沙箱子进程内执行）
 * - 经 APP_BASE_PATH 加载应用 autoloader，复用 CrawlerService 引擎
 * - 按 NDJSON 协议向 stdout 吐日志 / 最终结果
 *   日志行：{"t":"log","level":"info","message":"..."}
 *   结果行（末行）：{"t":"result","ok":true,"items":[...]}
 *
 * 真实插件由开发者上传 .zip，其 bootstrap 同此契约。
 */

$base = getenv('APP_BASE_PATH') ?: '/var/www';
require_once $base . '/vendor/autoload.php';

use App\Plugins\PluginManifest;
use App\Services\CrawlerService;
use GuzzleHttp\Client as Guzzle;

// 输入：base64(stdin/argv[1]) + JSON
$raw   = $argv[1] ?? '';
$input = json_decode(base64_decode($raw), true) ?? [];

// 日志协议
$emit = function (string $level, string $msg): void {
    fwrite(STDOUT, json_encode(['t' => 'log', 'level' => $level, 'message' => $msg]) . "\n");
};

try {
    $url   = $input['url']   ?? '';
    $depth = (int) ($input['depth'] ?? 1);
    // $input['cookie'] 为已解密明文（仅进程内），$input['model'] 可选

    // 插件自身的 manifest（与平台 PluginManifest 同构）
    $manifest = PluginManifest::fromJson([
        'name'        => '示例采集器',
        'developer'   => 'builtin',
        'version'     => '1.0.0',
        'description' => '爬取目标站 title/h1 与链接',
        'updated_at'  => date('Y-m-d'),
        'hooks'       => ['onBeforeCrawl', 'onAfterParse', 'onError'],
        'permissions' => ['network' => true, 'filesystem' => false, 'command' => false],
        'tools'       => [],
        'resources'   => [],
    ]);

    $svc = new CrawlerService(new Guzzle(), $manifest);

    // 插件的"解析规则"：仅解析，不碰抓取引擎（PRD §4.5）
    $parseRule = function (Symfony\Component\DomCrawler\Crawler $c, string $cur) {
        return [
            'url'   => $cur,
            'title' => $c->filter('title')->count() ? $c->filter('title')->text() : '',
            'h1'   => $c->filter('h1')->count() ? $c->filter('h1')->text() : '',
            // 可选：若配置了模型，这里可调用 $input['model'] 做摘要
        ];
    };

    $js = (bool) ($input['js_render'] ?? false);
    $result = $svc->crawl($url, $depth, $parseRule, $emit, $js);

    fwrite(STDOUT, json_encode([
        't'     => 'result',
        'ok'    => $result['ok'] ?? false,
        'items' => $result['items'] ?? [],
    ]) . "\n");

} catch (\Throwable $e) {
    $emit('error', 'onError: ' . $e->getMessage());
    fwrite(STDOUT, json_encode(['t' => 'result', 'ok' => false, 'items' => []]) . "\n");
}
