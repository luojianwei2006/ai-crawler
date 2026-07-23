<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Response};

/**
 * 账号：登录 / 改密 / 登出
 * 使用极简 API Token（存 users.api_token），不依赖 Sanctum / Session。
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

        // 生成随机 token 存入 users.api_token
        $token = bin2hex(random_bytes(32));
        $u->api_token = $token;
        $u->save();

        return Response::json(['token' => $token, 'role' => $u->role]);
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
        /** @var User $u */
        $u = $request->user();
        if ($u) {
            $u->api_token = null;
            $u->save();
        }
        return Response::json(['ok' => true]);
    }
}
