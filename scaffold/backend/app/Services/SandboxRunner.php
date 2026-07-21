<?php

namespace App\Services;

/**
 * 子进程沙箱（PRD §8 / tasks M5）
 * PHP 无 autoload 级隔离，必须分进程执行插件代码。
 * - 危险函数禁用（env SANDBOX_DISABLE_FUNCTIONS）
 * - 内存/超时配额（env SANDBOX_*）
 * - 权限白名单（来自 PluginManifest->permissions）
 * - 进程崩溃/超时隔离：单插件故障不拖垮主进程（PRD §8 Unwanted）
 */
class SandboxRunner
{
    public function __construct(private array $config = []) {}

    /**
     * 在子进程中执行插件 bootstrap，返回结构化结果。
     * @param string $bootstrapPhp 插件入口文件路径
     * @param array  $permissions 来自 manifest 的权限声明
     * @param array  $input       传入插件的输入（如 {url, depth}）
     */
    public function run(string $bootstrapPhp, array $permissions, array $input): SandboxResult
    {
        // 1) 越权能力拒绝（PRD §8 Ubiquitous）：command 权限需管理员显式授权，否则禁止
        if (! empty($permissions['command']) && empty($this->config['command_granted'])) {
            return new SandboxResult(false, null, ['code' => 'permission_denied', 'message' => '插件申请 command 权限但未被授权']);
        }

        // 2) 组装子进程命令：注入禁用函数 + 内存 + 超时
        $ini = sprintf(
            '-d memory_limit=%s -d disable_functions=%s',
            $this->config['memory_limit'] ?? '256M',
            $this->config['disable_functions'] ?? 'exec,shell_exec,system,passthru,proc_open,popen'
        );
        $payload = escapeshellarg(base64_encode(json_encode($input)));
        $cmd = sprintf('%s %s %s %s',
            $this->config['php_binary'] ?? 'php',
            $ini,
            escapeshellarg($bootstrapPhp),
            $payload
        );

        // 3) proc_open 执行，捕获 stdout/stderr（TODO: 实现超时 killer）
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        // 注入 APP_BASE_PATH，供子进程加载应用 autoloader（复用 CrawlerService）
        $env = ['APP_BASE_PATH' => base_path()];
        $proc = proc_open($cmd, $descriptors, $pipes, null, $env);
        if (! is_resource($proc)) {
            return new SandboxResult(false, null, [], ['code' => 'proc_failed', 'message' => '无法启动子进程']);
        }
        fclose($pipes[0]);
        $stdout = '';
        $stderr = '';
        $timeout = (float) ($this->config['timeout'] ?? (int) env('SANDBOX_TIMEOUT', 300));
        $start = microtime(true);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        while (true) {
            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $n = @stream_select($read, $write, $except, 1);
            if ($n > 0) {
                foreach ($read as $p) {
                    $chunk = fread($p, 8192);
                    if ($chunk === '' || $chunk === false) {
                        continue;
                    }
                    if ($p === $pipes[1]) {
                        $stdout .= $chunk;
                    } else {
                        $stderr .= $chunk;
                    }
                }
            }
            $st = proc_get_status($proc);
            if (! $st['running']) {
                break;
            }
            if (microtime(true) - $start > $timeout) {
                proc_terminate($proc); // 超时隔离（PRD §8 Unwanted）
                $stderr .= "\n[sandbox] 超过 {$timeout}s 已终止";
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($proc);

        // 解析 NDJSON 协议：t=log → 日志行；t=result → 末行结果
        $logs = [];
        $result = null;
        foreach (explode("\n", trim($stdout)) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $d = json_decode($line, true);
            if (! is_array($d)) continue;
            if (($d['t'] ?? null) === 'log') {
                $logs[] = ['level' => $d['level'] ?? 'info', 'message' => $d['message'] ?? ''];
            } elseif (($d['t'] ?? null) === 'result') {
                $result = $d;
            }
        }

        if ($status !== 0) {
            // 崩溃隔离：返回 error 级，不向上抛致命（PRD §8 Unwanted）
            $logs[] = ['level' => 'error', 'message' => ($stderr ?: "exit code {$status}")];
            return new SandboxResult(false, $result, $logs, [
                'code'    => 'plugin_crashed',
                'message' => $stderr ?: "exit code {$status}",
            ]);
        }

        $ok = ($result['ok'] ?? false) === true;
        return new SandboxResult($ok, $result, $logs, null);
    }
}

class SandboxResult
{
    /**
     * @param bool  $ok     结果是否成功
     * @param ?array $result 末行 result JSON（{ok,items}）
     * @param array  $logs   NDJSON 解析出的日志行 [{level,message}]
     * @param ?array $error  子进程级错误（如崩溃）
     */
    public function __construct(
        public bool  $ok,
        public ?array $result,
        public array $logs = [],
        public ?array $error = null
    ) {}
}
