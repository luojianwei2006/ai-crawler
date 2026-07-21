<?php

namespace App\Http\Controllers\Api;

use App\Models\{Plugin, UserPlugin};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 插件市场 + 我的插件（PRD §4.4 / tasks M8）
 * 展示标题/开发者/版本/更新日期/说明（manifest declared）。
 */
class MarketController extends Controller
{
    /** 市场：已上架插件列表（公共可见） */
    public function index()
    {
        return Plugin::where('status', 'published')
            ->select(['id', 'name', 'developer', 'version', 'description', 'updated_at_field'])
            ->get();
    }

    /** 我的插件：已安装 + 启停状态 */
    public function mine(Request $request)
    {
        $u = $request->user();
        return UserPlugin::with('plugin:id,name,developer,version,description,updated_at_field')
            ->where('user_id', $u->id)
            ->get();
    }

    /** 安装（市场 → 我的插件，默认停用） */
    public function install(Request $request, Plugin $plugin)
    {
        if ($plugin->status !== 'published') {
            return response()->json(['error' => 'not_published'], 422);
        }
        $u = $request->user();
        return UserPlugin::firstOrCreate(
            ['user_id' => $u->id, 'plugin_id' => $plugin->id],
            ['enabled' => false]
        );
    }

    /** 启用 / 停用（状态机，PRD §8 State-driven） */
    public function toggle(Request $request, Plugin $plugin)
    {
        $u = $request->user();
        $up = UserPlugin::where('user_id', $u->id)
            ->where('plugin_id', $plugin->id)->firstOrFail();
        $up->update(['enabled' => ! $up->enabled]);
        return response()->json(['enabled' => $up->enabled]);
    }
}
