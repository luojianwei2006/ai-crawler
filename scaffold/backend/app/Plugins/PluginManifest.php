<?php

namespace App\Plugins;

/**
 * 插件清单（内部 MCP 风格协议，见 PRD §4.8 / review §4.2）
 * 一期不对外暴露为 MCP Server，仅用其范式定义插件能力。
 */
class PluginManifest
{
    /** 评审议题 T1：最终钩子点清单（admin 审核/研发可扩展） */
    public const ALLOWED_HOOKS = [
        'onBeforeCrawl',
        'onAfterParse',
        'onError',
        'onAfterCrawl', // 预留
    ];

    public string $name;
    public string $developer;
    public string $version;
    public string $description;
    public string $updatedAt;

    /** @var string[] 仅允许 ALLOWED_HOOKS 中的钩子 */
    public array $hooks = [];

    /** @var array{network:bool,filesystem:bool,command:bool} */
    public array $permissions = ['network' => true, 'filesystem' => false, 'command' => false];

    /** @var array<int,array{name:string,description:string,input:array}> */
    public array $tools = [];

    /** @var array<int,array{name:string,schema:array}> */
    public array $resources = [];

    /**
     * 从插件包内 manifest.json 解析并校验。
     * @throws \InvalidArgumentException 钩子越权或结构非法
     */
    public static function fromJson(array $data): self
    {
        $m = new self();
        foreach (['name', 'developer', 'version', 'description', 'updated_at'] as $f) {
            if (! isset($data[$f]) || ! is_string($data[$f])) {
                throw new \InvalidArgumentException("manifest 缺少字段: {$f}");
            }
        }
        $m->name = $data['name'];
        $m->developer = $data['developer'];
        $m->version = $data['version'];
        $m->description = $data['description'];
        $m->updatedAt = $data['updated_at'];

        // 钩子点白名单校验（越权钩子直接驳回，对应 PRD §8 Unwanted 扫描）
        $m->hooks = array_values(array_filter(
            $data['hooks'] ?? [],
            fn ($h) => in_array($h, self::ALLOWED_HOOKS, true)
        ));

        // 权限声明归一化
        $perm = $data['permissions'] ?? [];
        $m->permissions = [
            'network'    => (bool) ($perm['network'] ?? true),
            'filesystem' => (bool) ($perm['filesystem'] ?? false),
            'command'    => (bool) ($perm['command'] ?? false),
        ];

        $m->tools = $data['tools'] ?? [];
        $m->resources = $data['resources'] ?? [];

        return $m;
    }

    /** 该插件是否申请了高危权限（command），需管理员显式授权（PRD §8 Optional） */
    public function requiresCommandPermission(): bool
    {
        return $this->permissions['command'] === true;
    }
}
