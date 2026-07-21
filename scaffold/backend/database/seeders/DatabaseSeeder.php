<?php

namespace Database\Seeders;

use App\Models\{User, Plugin, ModelProvider, UserPlugin};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\{Crypt, Hash};

/**
 * 一键起本地测试数据（tasks M0 / 阶段四 验收）
 * - 管理员 admin@example.com / admin123（登录后到 Models 改真实 key）
 * - 示例插件（已 published，指向内置 bootstrap）
 * - 一个模型供应商（占位 key，UI/DB 改为真实）
 * - 给管理员装好示例插件（默认停用，运行前在 UI 启用）
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'       => 'Admin',
                'role'       => 'admin',
                'status'     => 'active',
                'password_enc' => Hash::make('admin123'),
            ]
        );

        $plugin = Plugin::firstOrCreate(
            ['name' => '示例采集器'],
            [
                'developer'       => 'builtin',
                'version'         => '1.0.0',
                'description'     => '内置示例：爬取目标站 title/h1 与链接',
                'updated_at_field' => now(),
                'manifest_json'   => [
                    'name'        => '示例采集器',
                    'developer'   => 'builtin',
                    'version'     => '1.0.0',
                    'description' => '示例',
                    'updated_at'  => now()->toDateString(),
                    'hooks'       => ['onBeforeCrawl', 'onAfterParse', 'onError'],
                    'permissions' => ['network' => true, 'filesystem' => false, 'command' => false],
                    'tools'       => [],
                    'resources'   => [],
                    'bootstrap'   => base_path('plugins/builtin/example/bootstrap.php'),
                ],
                'dev_user_id'     => $admin->id,
                'status'          => 'published',
            ]
        );

        ModelProvider::firstOrCreate(
            ['name' => '默认 GPT'],
            [
                'vendor'      => 'openai',
                'base_url'    => 'https://api.openai.com/v1',
                'api_key_enc' => Crypt::encryptString('REPLACE_ME'),
                'model'       => 'gpt-4o',
                'quota'       => 0,
                'used'        => 0,
                'status'      => 'active',
            ]
        );

        UserPlugin::firstOrCreate(
            ['user_id' => $admin->id, 'plugin_id' => $plugin->id],
            ['enabled' => false]
        );
    }
}
