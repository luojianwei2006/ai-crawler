<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Response};

/**
 * 账号：登录 / 改密 / 登出（PRD §4.1 / tasks M1）
 * 使用 Sanctum SPA session 鉴权，不生成 Bearer Token。
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($data)) {
            return Response::json(['error' => 'invalid_credentials'], 401);
        }

        /** @var User $u */
        $u = Auth::user();

        // Session 已由 Auth::attempt 自动创建，客户端通过 Cookie 维持会话
        return Response::json(['role' => $u->role]);
    }

    public function changePassword(Request $request)
    {
        /** @var User $u */
        $u = $request->user();
        $data = $request->validate([
            'old_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);
        if (! Hash::check($data['old_password'], $u->password_enc)) {
            return Response::json(['error' => 'old_password_wrong'], 422);
        }
        $u->update(['password_enc' => Hash::make($data['new_password'])]);
        return Response::json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Response::json(['ok' => true]);
    }
}
