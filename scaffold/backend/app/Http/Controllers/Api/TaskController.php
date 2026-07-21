<?php

namespace App\Http\Controllers\Api;

use App\Models\{CrawlTask, UserPlugin, Cookie, Plugin, ModelProvider};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Response};

/**
 * 采集任务：运行闭环 + SSE 实时日志（PRD §3 主流程 / §4.9 / tasks M6-M7）
 */
class TaskController extends Controller
{
    /**
     * 运行（PRD §8 Event-driven）
     * 校验参数 → 校验插件已安装启用 → 校验 Cookie 未过期 → 建任务(pending)
     */
    public function run(Request $request)
    {
        $u = $request->user();
        $data = $request->validate([
            'plugin_id' => 'required|integer|exists:plugins,id',
            'model_id'  => 'required|integer|exists:model_providers,id',
            'url'       => 'required|url',
            'depth'     => 'integer|min:0|max:' . config('compliance.max_depth', 3),
            'cookie_id' => 'nullable|integer|exists:cookies,id',
            'js_render' => 'nullable|boolean',
        ]);

        // 1) 用户已安装且启用该插件（权限可见性：仅自己）
        $up = UserPlugin::where('user_id', $u->id)
            ->where('plugin_id', $data['plugin_id'])->firstOrFail();
        if (! $up->enabled) {
            return response()->json(['error' => 'plugin_disabled'], 422);
        }

        // 2) Cookie 过期/失效校验（PRD §8 Ubiquitous + Unwanted）
        if (! empty($data['cookie_id'])) {
            $ck = Cookie::where('user_id', $u->id) // 强制租户行隔离
                ->where('id', $data['cookie_id'])->firstOrFail();
            if ($ck->isExpired()) {
                $ck->update(['status' => 'expired']); // 标记失效
                return response()->json([
                    'error'   => 'cookie_expired',
                    'message' => 'Cookie 已过期，请刷新后重试',
                ], 422);
            }
        }

        // 3) 建任务（pending）
        $task = CrawlTask::create([
            'user_id'    => $u->id,
            'plugin_id'  => $data['plugin_id'],
            'model_id'   => $data['model_id'],
            'params_json' => $data,
            'status'     => 'pending',
        ]);

        // 4) 异步执行（本地 QUEUE_CONNECTION=sync 即同步跑；
        //    生产改 database/redis 并起 queue:work。
        //    RunCrawlJob 经 SandboxRunner 子进程沙箱执行插件，
        //    复用 CrawlerService 引擎，日志落库供 SSE 推流（stream()）。
        \App\Jobs\RunCrawlJob::dispatch($task->id);

        return response()->json([
            'task_id' => $task->id,
            'status'   => CrawlTask::find($task->id)->status,
        ]);
    }

    /**
     * SSE 实时日志流（tasks M6）
     * 前端 EventSource('/api/tasks/{id}/stream')；任务结束发 event:done。
     */
    public function stream(Request $request, $taskId)
    {
        $u = $request->user();
        $task = CrawlTask::where('user_id', $u->id)->findOrFail($taskId);

        return Response::stream(function () use ($task) {
            $lastId = 0;
            $finished = ['success', 'failed', 'aborted'];
            while (true) {
                $t = $task->fresh();
                if (in_array($t->status, $finished, true)) {
                    // 补发剩余日志
                    foreach ($t->logs()->where('id', '>', $lastId)->get() as $log) {
                        echo "data: " . json_encode([
                            'level' => $log->level, 'message' => $log->message,
                        ]) . "\n\n";
                        $lastId = $log->id;
                    }
                    echo "event: done\ndata: {\"status\":\"{$t->status}\"}\n\n";
                    break;
                }
                foreach ($t->logs()->where('id', '>', $lastId)->get() as $log) {
                    echo "data: " . json_encode([
                        'level' => $log->level, 'message' => $log->message,
                    ]) . "\n\n";
                    $lastId = $log->id;
                }
                ob_flush();
                flush();
                sleep(1);
            }
        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
