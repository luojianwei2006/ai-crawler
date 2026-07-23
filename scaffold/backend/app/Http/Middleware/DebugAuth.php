<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 鉴权调试中间件——在 auth:sanctum 前运行，记录请求头与认证结果。
 * 上线前可删除本文件并从 routes/api.php 移除引用。
 */
class DebugAuth
{
    public function handle(Request $request, Closure $next)
    {
        $bearer = $request->bearerToken();
        $authHeader = $request->header('Authorization');
        $cookie = $request->header('Cookie');
        $prefix = $request->method() . ' ' . $request->path();

        Log::info("[DEBUG-AUTH] ── {$prefix}", [
            'has_auth_header' => ! is_null($authHeader),
            'bearer_present'  => ! is_null($bearer),
            'bearer_preview'  => $bearer ? substr($bearer, 0, 35) . '…' : 'null',
            'cookie_preview'  => $cookie ? substr($cookie, 0, 80) . '…' : 'null',
        ]);

        $response = $next($request);

        Log::info("[DEBUG-AUTH] {$prefix} → {$response->getStatusCode()}", [
            'content_preview' => substr($response->getContent() ?: '', 0, 120),
        ]);

        return $response;
    }
}
