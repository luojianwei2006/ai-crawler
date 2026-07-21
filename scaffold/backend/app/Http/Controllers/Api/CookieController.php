<?php

namespace App\Http\Controllers\Api;

use App\Models\Cookie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Crypt};

/**
 * Cookie/登录态管理（PRD §4.6,§8 / tasks M9）
 * cookie_enc 一律 AES-256 密文；查询强制 user_id（租户行隔离）。
 */
class CookieController extends Controller
{
    public function index(Request $request)
    {
        // 仅返回元信息，绝不返回密文
        return Cookie::where('user_id', $request->user()->id)
            ->select(['id', 'site', 'expired_at', 'status', 'last_used_at'])
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'site'        => 'required|string',
            'cookie'      => 'required|string',
            'expired_at'  => 'required|date|after:now', // 必填失效日期，见 TC-B04
        ]);
        $data['cookie_enc'] = Crypt::encryptString($data['cookie']);
        unset($data['cookie']);
        $data['user_id'] = $request->user()->id;
        $data['status']   = 'valid';
        return Cookie::create($data);
    }

    public function update(Request $request, Cookie $cookie)
    {
        abort_if($cookie->user_id !== $request->user()->id, 403); // 租户隔离
        $data = $request->validate([
            'cookie'     => 'string',
            'expired_at' => 'date|after:now',
            'status'     => 'in:valid,expired',
        ]);
        if (isset($data['cookie'])) {
            $data['cookie_enc'] = Crypt::encryptString($data['cookie']);
            unset($data['cookie']);
        }
        $cookie->update($data);
        return $cookie;
    }

    public function destroy(Request $request, Cookie $cookie)
    {
        abort_if($cookie->user_id !== $request->user()->id, 403);
        $cookie->delete();
        return response()->noContent();
    }
}
