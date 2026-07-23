<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Hash, Log, Response};

/**
 * 账号：登录 / 改密 / 登出（PRD §4.1 / tasks M1）
 * 密码一律 AES-256（Crypt）密文；改密强制旧密码 + 强度。
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        Log::info('[AUTH] login attempt', ['email' => $data['email']]);

        if (! Auth::attempt($data)) {
            Log::info('[AUTH] Auth::attempt FAILED');
            return Response::json(['error' => 'invalid_credentials'], 401);
        }

        /** @var User $u */
        $u = Auth::user();
        Log::info('[AUTH] Auth::attempt OK', ['user_id' => $u->id, 'role' => $u->role]);

        try {
            $token = $u->createToken('api')->plainTextToken;
        } catch (\Throwable $e) {
            Log::error('[AUTH] createToken EXCEPTION: ' . $e->getMessage());
            return Response::json(['error' => 'token_creation_failed'], 500);
        }

        Log::info('[AUTH] token created', ['prefix' => substr($token, 0, 30) . '…']);
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
        $request->user()->currentAccessToken()->delete();
        return Response::json(['ok' => true]);
    }
}
