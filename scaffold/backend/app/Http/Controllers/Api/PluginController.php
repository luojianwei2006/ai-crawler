<?php

namespace App\Http\Controllers\Api;

use App\Models\{Plugin, User};
use App\Plugins\PluginManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Storage, Config};

/**
 * 插件：上传 + 安全扫描 + 审核队列（PRD §4.4,§4.8 / tasks M3 / §8 Unwanted）
 */
class PluginController extends Controller
{
    /**
     * 上传插件包（.zip），触发自动安全扫描 → 置 pending。
     */
    public function upload(Request $request)
    {
        /** @var User $u */
        $u = $request->user();
        $data = $request->validate([
            'package'   => 'required|file|mimes:zip|max:10240', // 10MB，见 TC-B06
            'developer' => 'required|string',
        ]);

        // 1) 存包并解压读取 manifest.json
        $path = $request->file('package')->store('plugins_raw');
        $manifestData = $this->readManifest(Storage::path($path));
        // 2) PluginManifest::fromJson 已校验钩子白名单与结构（越权钩子直接抛错）
        $m = PluginManifest::fromJson($manifestData);

        // 3) 自动安全扫描（评审议题 T6 / PRD §8 Unwanted）
        $scan = $this->scan(Storage::path($path), $m);
        if (! $scan['pass']) {
            // 危险函数/越权声明：直接驳回并给风险项清单
            $plugin = Plugin::create([
                'name'            => $m->name,
                'developer'       => $m->developer,
                'version'         => $m->version,
                'description'     => $m->description,
                'updated_at_field'=> $m->updatedAt,
                'manifest_json'   => (array) $m,
                'dev_user_id'     => $u->id,
                'status'          => 'rejected',
                'scan_report_json'=> $scan,
            ]);
            return response()->json([
                'status' => 'rejected',
                'scan_report' => $scan,
            ], 422);
        }

        // 4) 置 pending，等待管理员审核
        $plugin = Plugin::create([
            'name'            => $m->name,
            'developer'       => $m->developer,
            'version'         => $m->version,
            'description'     => $m->description,
            'updated_at_field'=> $m->updatedAt,
            'manifest_json'   => (array) $m,
            'dev_user_id'     => $u->id,
            'status'          => 'pending',
            'scan_report_json'=> $scan,
        ]);

        return response()->json(['status' => 'pending', 'plugin_id' => $plugin->id]);
    }

    /**
     * 管理员审核：通过 / 驳回（驳回须填原因）。
     */
    public function review(Request $request, $id)
    {
        abort_if(! $request->user()->isAdmin(), 403);
        $data = $request->validate([
            'action' => 'required|in:approve,reject',
            'reason' => 'required_if:action,reject|string',
        ]);

        $plugin = Plugin::findOrFail($id);
        if ($data['action'] === 'approve') {
            $plugin->update(['status' => 'published']);
        } else {
            $plugin->update([
                'status' => 'rejected',
                'scan_report_json' => array_merge(
                    $plugin->scan_report_json ?? [],
                    ['reject_reason' => $data['reason']]
                ),
            ]);
        }
        return response()->json(['status' => $plugin->status]);
    }

    /** 待审列表（管理员） */
    public function pending(Request $request)
    {
        abort_if(! $request->user()->isAdmin(), 403);
        return Plugin::where('status', 'pending')
            ->select(['id', 'name', 'developer', 'version', 'description', 'updated_at_field'])
            ->get();
    }

    /** 读取插件包内 manifest.json（真实解压，ZipArchive） */
    private function readManifest(string $zipPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('无法打开插件包');
        }
        try {
            $idx = $zip->locateName('manifest.json');
            if ($idx === false) {
                throw new \RuntimeException('插件包缺少 manifest.json');
            }
            $json = $zip->getFromIndex($idx);
        } finally {
            $zip->close();
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('manifest.json 解析失败');
        }
        return $data;
    }

    /** 静态安全扫描（评审议题 T6）：遍历 PHP 源，匹配禁用函数 / 越权声明 */
    private function scan(string $zipPath, PluginManifest $m): array
    {
        $risks = [];
        // 禁用函数清单（env SANDBOX_DISABLE_FUNCTIONS 覆盖）
        $dangerous = explode(',', env('SANDBOX_DISABLE_FUNCTIONS',
            'exec,shell_exec,system,passthru,proc_open,popen'));

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (! preg_match('/\.php$/i', $name)) {
                    continue;
                }
                $src = $zip->getFromIndex($i);
                foreach ($dangerous as $fn) {
                    $pat = '/\b' . str_replace(
                        ['*', '_'], ['.*', '_'],
                        preg_quote($fn, '/')
                    ) . '\s*\(/i';
                    if (preg_match($pat, $src)) {
                        $risks[] = "文件 {$name} 检出危险调用：{$fn}()";
                    }
                }
            }
            $zip->close();
        }

        if ($m->requiresCommandPermission()) {
            $risks[] = '申请 command 权限，需管理员显式授权';
        }

        return [
            'pass'       => count($risks) === 0,
            'risks'      => $risks,
            'scanned_at' => now()->toIso8601String(),
        ];
    }
}
