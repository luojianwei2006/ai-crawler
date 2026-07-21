<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI\Factory;

/**
 * 统一模型适配层（PRD §4.7 / tasks M2）
 * GLM / DeepSeek / GPT 均 OpenAI 兼容，仅 base_url + model 不同。
 * 测试按钮与正式调用复用同一适配器（PRD §8 Event-driven）。
 */
class ChatAdapter
{
    public function __construct(private Factory $factory) {}

    /**
     * 发送一次对话补全。
     * @param string $baseUrl  OpenAI 兼容端点
     * @param string $apiKey   AES-256 解密后的明文 key（仅进程内使用，禁止落日志）
     * @param string $model
     * @param array  $messages [{role,content}]
     * @param bool   $stream
     */
    public function complete(
        string $baseUrl,
        string $apiKey,
        string $model,
        array  $messages,
        bool   $stream = false
    ): array {
        $client = $this->factory
            ->withBaseUri($baseUrl)
            ->withApiKey($apiKey)
            ->make();

        /** @var Client $client */
        $resp = $client->chat()->create([
            'model'    => $model,
            'messages' => $messages,
            'stream'   => $stream,
        ]);

        return [
            'content' => $resp->choices[0]->message->content ?? '',
            'usage'   => $resp->usage?->toArray() ?? [],
        ];
    }

    /**
     * 模型测试按钮：最小 payload，回显延迟/token/错误码（PRD §4.7）。
     */
    public function test(string $baseUrl, string $apiKey, string $model): array
    {
        $start = microtime(true);
        try {
            $r = $this->complete($baseUrl, $apiKey, $model, [
                ['role' => 'user', 'content' => 'ping'],
            ], false);
            $r['latency_ms'] = (int) ( (microtime(true) - $start) * 1000 );
            $r['error'] = null;
        } catch (\Throwable $e) {
            // 不泄露 key；仅回显错误码/消息
            $r = [
                'content' => '',
                'usage'   => [],
                'latency_ms' => (int) ( (microtime(true) - $start) * 1000 ),
                'error' => ['code' => $e->getCode(), 'message' => $e->getMessage()],
            ];
        }
        return $r;
    }
}
