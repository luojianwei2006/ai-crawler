# 工程骨架：AI 内容采集插件平台

> 配套文档：`../report.md`（调研）、`../PRD.md`（v0.1）、`../review.md`、`../tasks.md`、`../testcases.md`、`../retrospective.md`、`../action-items.md`
> 一期边界（已确认）：开发者为主 · 私有化自托管 · 独立子进程 · 单机单租户+手动运行 · 内部 MCP 风格 · 免抽成
> 状态：M0–M10 全部完成。`scaffold/backend` 已是**完整可运行的 Laravel 11 工程**（含 `artisan` / `bootstrap/` / `config/` / `public/` / `tests/`），clone 后 `composer install` 即可起。工程总览见顶层 `README.md`，模块完成度见下方矩阵。

## 架构

```
后端  Laravel 11 (PHP 8.2)     ── 插件子进程沙箱(proc_open + 禁用函数 + 白名单)
前端  Vue3 + Pinia + Vite + Element Plus ── 运行页双栏(左参数 / 右 SSE 日志)
数据  MySQL 8                ── 8 张核心表(users/model_providers/plugins/
                                          user_plugins/crawl_tasks/task_logs/cookies/ai_skills)
采集  Guzzle + DomCrawler    ── 静态页；JS 渲染可选 Panther(headless Chrome, T3)
模型  OpenAI 兼容 ChatAdapter ── GLM/DeepSeek/GPT 仅 base_url+model 不同
实时  SSE(原生 EventSource)  ── 一期单向；反向控制再升 WebSocket(T4)
```

## 目录

```
scaffold/
├── backend/
│   ├── composer.json            # 依赖：laravel/framework, guzzle, dom-crawler, panther, openai-php, spatie/permission, sanctum
│   ├── .env.example           # 模型端点/密钥、沙箱配额、合规开关
│   ├── database/migrations/   # 8 张表（对应 tasks §4.3）
│   ├── app/Models/           # User / Plugin / CrawlTask / ModelProvider / Cookie
│   ├── app/Services/         # PluginManifest(MCP风格) / ChatAdapter / SandboxRunner / CrawlerService
│   ├── app/Http/Controllers/Api/  # Auth / ModelProvider / Plugin(upload+scan+review) / Task(run+SSE) / Market / Cookie
│   └── routes/api.php       # 7 个核心端点（review §4.2）
├── frontend/
│   ├── package.json / vite.config.js / index.html
│   └── src/
│       ├── router.js / api/client.js
│       ├── stores/taskStore.js # Pinia + SSE 实时日志
│       ├── App.vue                # 布局 + 导航
│       └── views/               # Login / Market / MyPlugins / RunView(双栏) / Models / Dev
└── infra/
    ├── Dockerfile             # 私有化镜像（PHP 8.2-fpm，可选 Chrome）
    └── docker-compose.yml    # app + mysql，env 注入沙箱/合规配置
```

## 快速起（研发视角）

> 本目录是**源码层**，缺 Laravel 引导骨架（`artisan` / `bootstrap/` / `public/index.php` / `config/`），**不能**直接 `composer install` 后运行。
> 完整、可直接复制的本地启动步骤（先 `create-project` 引导 Laravel，再覆盖本目录业务代码）见**顶层 `README.md` 的「快速开始」**。

```bash
# 1) 按顶层 README 引导完整 Laravel 工程并覆盖本目录业务代码后，再执行：
php artisan key:generate && php artisan migrate --seed
php artisan serve --port=8000

# 2) 前端
cd frontend && npm install && npm run dev   # http://localhost:5173 (代理 /api → 8000)

# 3) 私有化一键起
docker compose -f infra/docker-compose.yml up --build
```

## 模块完成度（对照 action-items.md M0–M10）

- [x] **M0** 脚手架/私有化镜像（Dockerfile + docker-compose）
- [x] **M1** 用户/权限（User + Auth + **Admin 中间件** + UsersController）
- [x] **M2** 模型管理（CRUD + ChatAdapter + 测试按钮回显）
- [x] **M3** 插件上传 + **真实 ZipArchive 解压 manifest** + **静态危险函数扫描** + 审核（admin 闸门）
- [x] **M4** 爬虫引擎（Guzzle+DomCrawler + **Panther JS 渲染分支** + **robots.txt 合规** + 深度 BFS）
- [x] **M5** 子进程沙箱（NDJSON 解析 + env 注入 + **超时 killer** + 崩溃隔离）
- [x] **M6** SSE 实时日志（stream + 前端 Pinia/EventSource）
- [x] **M7** 运行闭环（run→RunCrawlJob→SandboxRunner→CrawlerService→落 task_logs→SSE）
- [x] **M8** 市场 + **开发模板**（`public/dev/template.zip` 已生成，DevView 可下载）
- [x] **M9** Cookie 管理（加密/过期校验/租户行隔离）
- [x] **M10** 合规/埋点（robots 开关 + 合规 env；埋点统计字段已预留，聚合为二期）

## 仍可选 / 二期（非阻断）
- [ ] 合规代理（Bright Data/Zyte）集成（retrospective §7 D9）
- [ ] 生产队列 worker（本地 `QUEUE_CONNECTION=sync` 已可测；生产用 `redis` + `php artisan queue:work`）
- [ ] 全栈真启动需 `composer create-project laravel/laravel` + `composer install` + MySQL（本仓库为**源码层**）
