<?php

namespace App\Http\Controllers\Api;

use App\Models\ModelProvider;
use App\Services\ChatAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt, Response};

/**
 * 模型管理：增删改 + 测试按钮（PRD §4.7 / tasks M2）
 * api_key 一律 AES-256 密文存储；测试解密仅进程内使用，禁止落日志。
 */
class ModelProviderController extends Controller
{
    /** 列表（隐藏 api_key） */
    public function index(Request $request)
    {
        return ModelProvider::where(
            fn ($q) => $request->user()->isAdmin()
                ? $q->whereRaw('1=1')
                : $q->where('status', 'active')
        )->get();
    }

    /** 新增（密文存储） */
    public function store(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'name'      => 'required|string',
            'vendor'    => 'required|in:glm,deepseek,openai',
            'base_url'  => 'required|url',
            'api_key'   => 'required|string',
            'model'     => 'required|string',
            'quota'     => 'integer|min:0',
        ]);

        $data['api_key_enc'] = Crypt::encryptString($data['api_key']);
        unset($data['api_key']);
        $data['status'] = 'active';

        return ModelProvider::create($data);
    }

    /** 更新 */
    public function update(Request $request, $id)
    {
        abort_if(! $request->user()->isAdmin(), 403);
        $mp = ModelProvider::findOrFail($id);
        $data = $request->validate([
            'name'     => 'string',
            'base_url' => 'url',
            'api_key'  => 'string',
            'model'    => 'string',
            'quota'    => 'integer|min:0',
            'status'   => 'in:active,disabled',
        ]);
        if (isset($data['api_key'])) {
            $data['api_key_enc'] = Crypt::encryptString($data['api_key']);
            unset($data['api_key']);
        }
        $mp->update($data);
        return $mp;
    }

    public function destroy(Request $request, $id)
    {
        abort_if(! $request->user()->isAdmin(), 403);
        ModelProvider::findOrFail($id)->delete();
        return response()->noContent();
    }

    /**
     * 测试按钮（PRD §4.7 / §8 Event-driven）：最小 payload，回显延迟/token/错误码。
     */
    public function test(Request $request, $id, ChatAdapter $adapter)
    {
        $mp = ModelProvider::findOrFail($id);
        $key = Crypt::decryptString($mp->api_key_enc); // 仅进程内

        $r = $adapter->test($mp->base_url, $key, $mp->model);
        return Response::json([
            'latency_ms' => $r['latency_ms'] ?? null,
            'usage'      => $r['usage'] ?? [],
            'error'      => $r['error'] ?? null,
        ]);
    }
}
