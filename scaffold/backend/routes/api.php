<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    AuthController,
    ModelProviderController,
    PluginController,
    TaskController,
    MarketController,
    CookieController,
    UsersController
};
use App\Http\Middleware\Admin;
use App\Http\Middleware\DebugAuth;

/*
 * API 路由（对应 review §4.2 内部 MCP 风格接口；一期不对外暴露为 MCP Server）
 * 鉴权：Laravel Sanctum（guard=api）
 */

Route::post('/login', [AuthController::class, 'login']);

// ---- 调试路由（上线前删除） ----
Route::get('/_debug/ping', function () {
    return response()->json([
        'status'       => 'ok',
        'php_version'  => PHP_VERSION,
        'headers'      => [
            'authorization'      => request()->header('Authorization'),
            'bearer_token'       => request()->bearerToken(),
            'content_type'       => request()->header('Content-Type'),
            'cookie'             => substr(request()->header('Cookie') ?? '', 0, 80),
        ],
    ]);
});

Route::middleware([DebugAuth::class, 'auth:sanctum'])->group(function () {
    // 调试：需要有效 Token
    Route::get('/_debug/auth-ping', function () {
        $u = request()->user();
        return response()->json([
            'authenticated' => ! is_null($u),
            'user_id'       => $u?->id,
            'user_email'    => $u?->email,
            'bearer_received' => substr(request()->bearerToken() ?? '', 0, 40) . '…',
        ]);
    });

    // 账号：改密 / 登出（PRD §4.1）
    Route::post('/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // 模型管理：所有人可读 active；增删改需管理员（PRD §4.7 / tasks M2）
    Route::apiResource('models', ModelProviderController::class)
        ->only(['index', 'store', 'update', 'destroy']);
    Route::post('/models/{id}/test', [ModelProviderController::class, 'test']);

    // 插件市场 + 安装 / 启停（PRD §4.4 / tasks M8）
    Route::get('/market', [MarketController::class, 'index']);
    Route::get('/my-plugins', [MarketController::class, 'mine']);
    Route::post('/plugins/{plugin}/install', [MarketController::class, 'install']);
    Route::post('/plugins/{plugin}/toggle', [MarketController::class, 'toggle']);

    // 插件上传（开发者，触发扫描，PRD §4.8 / tasks M3）
    Route::post('/plugins/upload', [PluginController::class, 'upload']);

    // 采集任务：运行 + SSE 实时日志（PRD §3 主流程 / tasks M7）
    Route::post('/tasks/run', [TaskController::class, 'run']);
    Route::get('/tasks/{task}/stream', [TaskController::class, 'stream']);

    // Cookie/登录态管理（PRD §4.6 / tasks M9）
    Route::apiResource('cookies', CookieController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    // ---- 管理员专用（Admin 中间件闸门，见 app/Http/Middleware/Admin.php）----
    Route::middleware([DebugAuth::class, 'auth:sanctum', Admin::class])
        ->prefix('admin')->group(function () {
            Route::get('/plugins/pending', [PluginController::class, 'pending']);
            Route::post('/plugins/{plugin}/review', [PluginController::class, 'review']);
            Route::apiResource('users', UsersController::class); // 用户管理（PRD §4.1）
        });
});
