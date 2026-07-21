<?php

namespace App\Jobs;

use App\Models\{CrawlTask, Plugin, Cookie, ModelProvider};
use App\Plugins\PluginManifest;
use App\Services\SandboxRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\{Crypt, Log};

/**
 * 采集任务异步执行（tasks M7 / PRD §3 主流程闭环）
 * 串联：SandboxRunner（子进程沙箱）执行插件 bootstrap →
 *        插件内复用 CrawlerService 引擎 → 经 NDJSON 吐日志/结果 →
 *        本 Job 把日志落 task_logs（SSE 推流由前端读取）。
 *
 * 本地测试：.env 用 QUEUE_CONNECTION=sync，dispatch 即同步执行，无需 Redis/Worker。
 * 生产：改 database 队列（redis）并起 `php artisan queue:work`。
 */
class RunCrawlJob implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public int $taskId) {}

    public function handle(SandboxRunner $runner): void
    {
        $task = CrawlTask::findOrFail($this->taskId);
        $task->update(['status' => 'running', 'started_at' => now()]);

        try {
            $plugin  = Plugin::findOrFail($task->plugin_id);
            $manifest = PluginManifest::fromJson($plugin->manifest_json ?? []);

            // bootstrap 路径：manifest 声明 或 内置示例
            $bootstrap = ($plugin->manifest_json['bootstrap'] ?? null)
                ?: base_path('plugins/builtin/example/bootstrap.php');

            $params = $task->params_json ?? [];

            // Cookie 解密仅在进程内传入（不落日志）
            if (! empty($params['cookie_id'])) {
                $ck = Cookie::where('user_id', $task->user_id)
                    ->where('id', $params['cookie_id'])->first();
                if ($ck && ! $ck->isExpired()) {
                    $params['cookie'] = Crypt::decryptString($ck->cookie_enc);
                }
            }

            // 模型信息（供插件内调用）
            if ($task->model_id) {
                $mp = ModelProvider::find($task->model_id);
                if ($mp) {
                    $params['model'] = [
                        'base_url' => $mp->base_url,
                        'api_key'  => Crypt::decryptString($mp->api_key_enc),
                        'model'    => $mp->model,
                    ];
                }
            }

            // 在子进程沙箱内执行插件，解析其 NDJSON 输出
            $res = $runner->run($bootstrap, $manifest->permissions, $params);

            foreach ($res->logs as $l) {
                $task->logs()->create([
                    'level'   => $l['level'] ?? 'info',
                    'message' => $l['message'] ?? '',
                ]);
            }
            $task->update([
                'status'   => $res->ok ? 'success' : 'failed',
                'ended_at' => now(),
            ]);

        } catch (\Throwable $e) {
            // 崩溃隔离：任务标失败，不影响其他（PRD §8 Unwanted）
            $task->logs()->create(['level' => 'error', 'message' => $e->getMessage()]);
            $task->update(['status' => 'failed', 'ended_at' => now()]);
            Log::error("RunCrawlJob failed task={$this->taskId}: " . $e->getMessage());
        }
    }
}
