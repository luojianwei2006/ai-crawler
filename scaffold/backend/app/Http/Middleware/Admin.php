<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 管理员闸门（M1 / M3）：仅 role=admin 可进入。
 * 配合 routes 的 admin 分组使用；控制器内仍保留 abort_if 兜底。
 */
class Admin
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if (! $u || ! $u->isAdmin()) {
            abort(403, '需要管理员权限');
        }
        return $next($request);
    }
}
