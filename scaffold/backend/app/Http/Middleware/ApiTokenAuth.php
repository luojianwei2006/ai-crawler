<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 极简 API Token 鉴权——替代 Sanctum entire。
 * 从 Authorization: Bearer <token> 中提取 token，查询 users.api_token。
 * 找到则设置 Auth::setUser()，后续 $request->user() 可用。
 */
class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $user = User::where('api_token', $token)->first();

        if (! $user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
