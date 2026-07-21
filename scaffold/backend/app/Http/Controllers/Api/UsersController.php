<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Response};

/**
 * 用户管理（管理员，PRD §4.1）
 * 列表 / 新增 / 改角色状态 / 删除。受 Admin 中间件保护。
 */
class UsersController extends Controller
{
    public function index()
    {
        return User::select(['id', 'name', 'email', 'role', 'status', 'created_at'])->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'in:admin,user,developer',
            'status'   => 'in:active,disabled',
        ]);
        $data['password_enc'] = Hash::make($data['password']);
        unset($data['password']);
        return User::create($data);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'   => 'string',
            'role'   => 'in:admin,user,developer',
            'status' => 'in:active,disabled',
        ]);
        $user->update($data);
        return $user;
    }

    public function destroy(User $user)
    {
        $user->delete();
        return Response::noContent();
    }
}
