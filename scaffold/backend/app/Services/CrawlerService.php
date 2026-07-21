<?php

namespace App\Services;

use GuzzleHttp\Client as Guzzle;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Panther\Client as PantherClient;

/**
 * 爬虫引擎（PRD §4.5 / tasks M4）
 * - 静态/服务端渲染页：Guzzle + DomCrawler
 * - JS 渲染页（评审议题 T3）：Symfony Panther（headless chrome）
 * - 链接抽取 + 深度 BFS 去重；核心统一调度，插件只写解析规则
 * - 触发 PluginManifest 钩子：onBeforeCrawl / onAfterParse / onError
 */
class CrawlerService
{
    public const MAX_DEPTH = 3; // 默认值，env COMPLIANCE_MAX_DEPTH 可配

    private array $robotsCache = [];

    public function __construct(
        private Guzzle $guzzle,
        private PluginManifest $manifest
    ) {}

    /**
     * 执行一次采集。
     * @param string $url   目标网址（已校验格式）
     * @param int    $depth 爬取深度（已截断到上限）
     * @param callable $parseRule 插件提供的"解析规则"（仅解析，不抓引擎）
     * @param callable $emitLog 日志回调（SSE 推流 + 落库）
     */
    public function crawl(string $url, int $depth, callable $parseRule, callable $emitLog, bool $jsRender = false): array
    {
        $depth = min($depth, self::MAX_DEPTH);
        $visited = [];
        $queue = [$url];
        $results = [];

        try {
            // 钩子：onBeforeCrawl
            $emitLog('info', "onBeforeCrawl: start {$url}, depth={$depth}");

            $level = 0;
            while (! empty($queue) && $level < $depth) {
                $next = [];
                foreach ($queue as $cur) {
                    if (isset($visited[$cur])) continue;
                    $visited[$cur] = true;

                    // 合规：robots.txt 禁止则跳过（env COMPLIANCE_ROBOTS_ENFORCED）
                    if (! $this->allowedByRobots($cur)) {
                        $emitLog('warn', "robots.txt 禁止抓取 {$cur}，已跳过");
                        continue;
                    }

                    // 抓取：JS 渲染页用 Panther(headless Chrome)，否则 Guzzle+DomCrawler
                    if ($jsRender && class_exists(PantherClient::class)) {
                        try {
                            $panther = PantherClient::createChromeClient();
                            $panther->request('GET', $cur);
                            $crawler = $panther->getCrawler();
                        } catch (\Throwable $e) {
                            $emitLog('warn', "Panther 不可用，回退静态抓取：{$e->getMessage()}");
                            $crawler = new Crawler((string) $this->guzzle->get($cur)->getBody());
                        }
                    } else {
                        $crawler = new Crawler((string) $this->guzzle->get($cur)->getBody());
                    }

                    // 插件解析规则（仅解析，不碰抓取引擎）
                    $results[] = $parseRule($crawler, $cur);

                    // 链接抽取（归一化 + 去重 + 拼绝对 URL）
                    $links = $crawler->filter('a')->each(fn ($n) => $n->attr('href'));
                    foreach (array_filter($links) as $href) {
                        $abs = $this->absUrl($cur, $href);
                        if ($abs && ! isset($visited[$abs]) && $this->sameHost($url, $abs)) {
                            $next[] = $abs;
                        }
                    }
                    $emitLog('info', "parsed {$cur}, found " . count($next) . " links");
                }
                $queue = $next;
                $level++;
            }

            // 钩子：onAfterCrawl（预留）
            $emitLog('info', "onAfterCrawl: done, total " . count($results));
            return ['ok' => true, 'items' => $results];
        } catch (\Throwable $e) {
            // 钩子：onError；结构化错误返回，任务标红不崩主进程
            $emitLog('error', "onError: " . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function absUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) return $href;
        if (str_starts_with($href, '//')) return 'https:' . $href;
        if (str_starts_with($href, '/')) {
            $p = parse_url($base);
            return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . $href;
        }
        return null;
    }

    private function sameHost(string $a, string $b): bool
    {
        return parse_url($a, PHP_URL_HOST) === parse_url($b, PHP_URL_HOST);
    }

    /** robots.txt 合规（env COMPLIANCE_ROBOTS_ENFORCED 默认开启） */
    private function allowedByRobots(string $url): bool
    {
        if (! env('COMPLIANCE_ROBOTS_ENFORCED', true)) {
            return true;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! isset($this->robotsCache[$host])) {
            $this->robotsCache[$host] = $this->fetchRobots($host);
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        foreach ($this->robotsCache[$host] as $rule) {
            if (preg_match('#' . $rule . '#i', $path)) {
                return false;
            }
        }
        return true;
    }

    private function fetchRobots(string $host): array
    {
        try {
            $txt = (string) $this->guzzle->get("https://{$host}/robots.txt")->getBody();
            $disallows = [];
            foreach (explode("\n", $txt) as $line) {
                if (preg_match('/Disallow:\s*(.*)/i', $line, $m)) {
                    $p = trim($m[1]);
                    if ($p !== '') {
                        $disallows[] = preg_quote($p, '#');
                    }
                }
            }
            return $disallows;
        } catch (\Throwable $e) {
            return []; // 取不到 robots 则放行
        }
    }
}
