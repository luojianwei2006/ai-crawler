import os, zipfile

OUT = "/workspace/ai-crawler-plugin-platform/scaffold/backend/public/dev/template.zip"
os.makedirs(os.path.dirname(OUT), exist_ok=True)

MANIFEST = """{
  "name": "my-site-crawler",
  "developer": "your-name",
  "version": "1.0.0",
  "description": "在此填写插件说明",
  "updated_at": "2026-07-21",
  "hooks": ["onBeforeCrawl", "onAfterParse", "onError"],
  "permissions": {"network": true, "filesystem": false, "command": false},
  "tools": [{"name": "crawl", "description": "执行一次采集", "input": {"url": "string", "depth": "int"}}],
  "resources": [{"name": "result", "schema": {"title": "string", "url": "string", "content": "text"}}],
  "bootstrap": "bootstrap.php"
}
"""

BOOTSTRAP = """<?php
/**
 * 插件入口（开发者模板）
 * 平台以子进程沙箱运行本文件：php -d disable_functions=... bootstrap.php <base64输入>
 * 输入 argv[1] = base64(json)，含 {url, depth, cookie?, model?, js_render?}
 * 输出协议（NDJSON 到 stdout）：
 *   日志：{"t":"log","level":"info","message":"..."}
 *   结果（末行）：{"t":"result","ok":true,"items":[...]}
 */

$base = getenv('APP_BASE_PATH') ?: '/var/www';
require_once $base . '/vendor/autoload.php';

use App\\Plugins\\PluginManifest;
use App\\Services\\CrawlerService;
use GuzzleHttp\\Client as Guzzle;

$input = json_decode(base64_decode($argv[1] ?? ''), true) ?? [];
$emit = function (string $level, string $msg): void {
    fwrite(STDOUT, json_encode(['t' => 'log', 'level' => $level, 'message' => $msg]) . "\\n");
};

try {
    $url   = $input['url'] ?? '';
    $depth = (int) ($input['depth'] ?? 1);
    $js    = (bool) ($input['js_render'] ?? false);

    $manifest = PluginManifest::fromJson([
        'name' => 'my-plugin', 'developer' => 'you', 'version' => '1.0.0',
        'description' => '我的采集插件', 'updated_at' => date('Y-m-d'),
        'hooks' => ['onBeforeCrawl', 'onAfterParse', 'onError'],
        'permissions' => ['network' => true, 'filesystem' => false, 'command' => false],
        'tools' => [], 'resources' => [],
    ]);
    $svc = new CrawlerService(new Guzzle(), $manifest);

    // TODO: 在此编写你的"解析规则"（仅解析，不碰抓取引擎）
    $parseRule = function (\\Symfony\\Component\\DomCrawler\\Crawler $c, string $cur) {
        return [
            'url'   => $cur,
            'title' => $c->filter('title')->count() ? $c->filter('title')->text() : '',
        ];
    };

    $result = $svc->crawl($url, $depth, $parseRule, $emit, $js);
    fwrite(STDOUT, json_encode(['t' => 'result', 'ok' => $result['ok'] ?? false, 'items' => $result['items'] ?? []]) . "\\n");
} catch (\\Throwable $e) {
    $emit('error', 'onError: ' . $e->getMessage());
    fwrite(STDOUT, json_encode(['t' => 'result', 'ok' => false, 'items' => []]) . "\\n");
}
"""

README = """# 插件开发模板

## 目录
- `manifest.json`：插件元信息（钩子点、权限声明、Tools/Resources）。钩子仅允许：
  onBeforeCrawl / onAfterParse / onError / onAfterCrawl。
- `bootstrap.php`：插件入口，由平台在**子进程沙箱**中执行。

## 协议
1. 平台调用：`php -d disable_functions=... bootstrap.php <base64(json)>`
2. 你的代码读取 `argv[1]`（base64 后的 JSON：url / depth / cookie / model / js_render）。
3. 输出用 NDJSON 到 stdout：
   - 日志：`{"t":"log","level":"info","message":"..."}`
   - 结果（末行）：`{"t":"result","ok":true,"items":[...]}`
4. 复用平台 `CrawlerService` 引擎；你只写**解析规则**（见 bootstrap.php 的 `$parseRule`）。

## 安全
- 不可使用 exec/shell_exec/system/popen/proc_open 等危险函数，上架会自动扫描并驳回。
- 需要 command 权限须管理员显式授权。

## 打包上传
`zip my-plugin.zip manifest.json bootstrap.php` → 平台“插件开发”页上传 → 自动扫描 → 管理员审核 → 上架。
"""

with zipfile.ZipFile(OUT, "w", zipfile.ZIP_DEFLATED) as z:
    z.writestr("manifest.json", MANIFEST)
    z.writestr("bootstrap.php", BOOTSTRAP)
    z.writestr("README.md", README)

print("written:", OUT, os.path.getsize(OUT), "bytes")
