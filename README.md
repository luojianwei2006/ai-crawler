# AI 内容采集插件平台

> 一个面向**开发者**的 AI 驱动内容采集插件管理与运行平台。每个插件即一个网站爬虫，可配置目标 URL、登录 Cookie、到期时间、AI 模型与采集深度，一键运行并通过右侧实时日志面板观察全过程。

> **一期边界（已确认）**：开发者为主 · 私有化自托管 · 独立子进程沙箱 · 单机单租户 + 手动运行 · 内部 MCP 风格插件协议 · 免抽成

---

## 项目背景与定位

本项目从一句话想法出发，完整走完了产品生命周期的六个阶段：**调研 → PRD → 评审 → 研发 → 测试 → 复盘**（详见下方[文档索引](#文档索引)）。目标是交付一套**可自托管运行**的源码级工程，而非只停留在文档。

平台把"网站采集"抽象为一个个**插件**：开发者用 PHP 编写爬虫逻辑、打包上传、经管理员审核后上架到市场；普通用户安装插件、填入参数（URL / Cookie / 到期日 / AI 模型 / 采集深度）、点击运行，右侧实时日志面板通过 SSE 流式回显运行过程，AI 模型负责内容理解与结构化抽取。

## 核心特性

| 模块 | 能力 |
|------|------|
| **管理后台** | 用户管理、AI 技能配置、插件上/下架审核 |
| **用户后台** | 安装 / 启停插件、修改密码 |
| **插件定义** | 每个插件 = 一个网站爬虫（内容 + 链接） |
| **插件参数** | URL、登录 Cookie、到期日期、AI 模型、采集深度 |
| **模型管理** | 增删改（GLM / DeepSeek / GPT），带**测试按钮**回显 |
| **运行闭环** | 设参 + 选模型 → 运行 → 右栏 **SSE 实时日志** |
| **插件开发** | PHP 框架，上传即上架（标题 / 开发者 / 版本 / 更新日期 / 描述）+ 开发模板 |

## 架构

```
┌─────────────────────────────────────────────────────────────┐
│  浏览器 (Vue3 + Pinia + Vite)                                  │
│   Login / Market / MyPlugins / RunView(左参数·右SSE日志) /     │
│   Models / Dev(下载模板)                                       │
└───────────────┬─────────────────────────────────────────────┘
                │  HTTPS / JSON  (Sanctum Token)
┌───────────────▼─────────────────────────────────────────────┐
│  Laravel 后端 (PHP 8.2)                                        │
│  ├─ Auth / Users / Admin中间件                                │
│  ├─ ModelProvider (CRUD + ChatAdapter.test)                   │
│  ├─ Plugin (ZipArchive解压 + 危险函数扫描 + 审核闸门)          │
│  ├─ Task (run → RunCrawlJob → SandboxRunner)                  │
│  ├─ Market / Cookie (加密 + 行隔离)                            │
│  └─ SSE 实时日志流                                            │
└───────┬───────────────────────┬─────────────────────────────┘
        │                        │
┌───────▼──────────┐   ┌────────▼──────────────────────────┐
│ MySQL 8          │   │ 插件子进程沙箱 (proc_open)          │
│ 8 张核心表        │   │  env 注入 + 禁用函数 + 白名单        │
│                  │   │  + 超时 killer + 崩溃隔离            │
│                  │   │  → CrawlerService                    │
│                  │   │     Guzzle + DomCrawler (静态)        │
│                  │   │     Panther (JS渲染, 可选)            │
│                  │   │     robots.txt 合规 + 深度 BFS        │
│                  │   │  ↔ ChatAdapter (AI 抽取, 可选)        │
└──────────────────┘   └────────────────────────────────────┘
```

## 技术栈

- **后端**：Laravel 11（PHP 8.2）+ MySQL 8
- **前端**：Vue 3 + Pinia + Vite + Axios（Sanctum）+ Element Plus（UI 组件库）
- **采集**：Guzzle + Symfony DomCrawler（静态）；Symfony Panther（headless Chrome，JS 渲染可选）
- **模型**：OpenAI 兼容 `ChatAdapter`，统一对接 GLM / DeepSeek / GPT（仅 `base_url` + `model` 不同）
- **实时**：SSE（原生 `EventSource`），一期单向；反向控制二期升 WebSocket
- **隔离**：`proc_open` 子进程 + 禁用函数 + 白名单 + 超时 killer + NDJSON 协议
- **部署**：Dockerfile（PHP 8.2-fpm，可选 Chrome）+ docker-compose（app + mysql）

## 目录结构

```
ai-crawler-plugin-platform/
├── README.md            # 本文件（项目总览）
├── report.md            # 竞品 / 行业调研（Apify、MCP、Laravel 栈、沙箱、合规）
├── PRD.md               # 产品需求文档 v0.1（含 EARS 验收 + 6 项已确认决策）
├── review.md            # 设计 / 研发评审（页面·状态·插件协议·表结构）
├── tasks.md             # 研发任务拆分 M0–M10
├── testcases.md         # 测试用例与验收清单
├── retrospective.md     # 阶段复盘（目标达成 + 二期候选）
├── action-items.md      # 问题 / 待办汇总（A–E）
├── results/             # Deep Research 原始输出（outline / fields / 数据）
└── scaffold/            # 工程源码
    ├── backend/         # Laravel 后端（详见 scaffold/README.md）
    ├── frontend/        # Vue3 前端
    ├── infra/           # Docker 部署
    └── README.md        # 工程说明（架构 + 目录 + 快速起 + 模块完成度）
```

## 快速开始

### 本地研发

> `scaffold/backend` 已是**完整可运行的 Laravel 11 工程**（含 `artisan` / `bootstrap/` / `public/index.php` / `config/` / `routes/api.php` 等），克隆后直接安装依赖即可，无需再引导框架或手动覆盖文件。

**后端（scaffold/backend 直接运行）**

```bash
# 前置：PHP ≥ 8.2（需 mbstring / dom / xml / curl / zip / mysql / sqlite / bcmath 扩展）+ Composer + Node ≥ 18
# 安装 Composer： https://getcomposer.org/download/   （macOS: brew install composer）

cd scaffold/backend
composer install                      # 内置 composer.lock，按锁定版本可复现安装
cp .env.example .env
php artisan key:generate
php artisan migrate --seed           # 建表 + 种子：admin@example.com / admin123 + 示例插件 + 模型
php artisan serve --port=8000        # 后端 http://localhost:8000
```

> 安全公告说明：Laravel 11.x 在部分 Composer 版本会触发安全公告拦截导致 `composer install` 失败。仓库 `composer.json` 已设置 `policy.advisories.block=false` 并忽略已知公告 ID；个别发行版 Composer 不读取该配置时，可临时执行 `composer config -g policy.advisories.block false` 再安装。

**前端**

```bash
cd scaffold/frontend
npm install
npm run dev                         # http://localhost:5173（代理 /api → 8000）
```

**跑一次采集**：浏览器开前端 → Market 安装 example 插件 → MyPlugins 填参数 → RunView 点运行；右侧 SSE 日志实时回显（AI 抽取需先在 Models 配可用模型）。

### Docker 私有化一键起

```bash
cd scaffold/infra
docker compose up --build        # 启动 app(PHP 8.2-fpm) + mysql
# 环境变量注入沙箱配额 / 合规开关（见 backend/.env.example）
```

## 模块完成度（对照 action-items.md M0–M10）

- [x] **M0** 脚手架 / 私有化镜像（Dockerfile + docker-compose）
- [x] **M1** 用户 / 权限（User + Auth + **Admin 中间件** + UsersController）
- [x] **M2** 模型管理（CRUD + ChatAdapter + 测试按钮回显）
- [x] **M3** 插件上传 + **真实 ZipArchive 解压 manifest** + **静态危险函数扫描** + 审核（admin 闸门）
- [x] **M4** 爬虫引擎（Guzzle+DomCrawler + **Panther JS 渲染分支** + **robots.txt 合规** + 深度 BFS）
- [x] **M5** 子进程沙箱（NDJSON 解析 + env 注入 + **超时 killer** + 崩溃隔离）
- [x] **M6** SSE 实时日志（stream + 前端 Pinia/EventSource）
- [x] **M7** 运行闭环（run → RunCrawlJob → SandboxRunner → CrawlerService → 落 task_logs → SSE）
- [x] **M8** 市场 + **开发模板**（`public/dev/template.zip` 已生成，DevView 可下载）
- [x] **M9** Cookie 管理（加密 / 过期校验 / 租户行隔离）
- [x] **M10** 合规 / 埋点（robots 开关 + 合规 env；埋点统计字段已预留，聚合为二期）

## 插件开发指南

1. 在前端 **Dev** 页下载 `template.zip`（含 `manifest.json` + `bootstrap.php` + `README.md`）。
2. 按 `manifest.json` 声明插件元信息（title / author / version / update_date / description / hooks）。
3. 在 `bootstrap.php` 中用沙箱提供的 NDJSON 协议 `emit()` 回显日志、调用父进程提供的采集 / AI 钩子（白名单 `ALLOWED_HOOKS`）。
4. 打包 ZIP → 管理后台上传 → 触发**静态危险函数扫描** → 管理员审核上架 → 市场可见。

> 沙箱约束：子进程仅能访问注入的环境变量与白名单钩子；`exec` / `shell_exec` / `proc_open` 等危险函数被禁用；运行超时由父进程强制 kill。详见 `backend/app/Services/SandboxRunner.php` 与 `backend/app/Plugins/PluginManifest.php`。

## 安全与合规

- **子进程隔离**：`proc_open` 派生独立进程，禁用危险函数，仅开放白名单钩子。
- **超时 killer**：`stream_select` 循环监听 + `proc_terminate`，防止插件卡死占用资源。
- **崩溃隔离**：子进程异常退出不拖垮主进程，错误写入 `task_logs`。
- **敏感数据**：登录密码经 **bcrypt** 哈希存储（`password_enc`）；API Key 与 Cookie 经 Laravel `Crypt`（AES-256）加密存储。
- **合规**：尊重目标站点 `robots.txt`；可通过 `COMPLIANCE_*` 环境变量统一开关。

## 文档索引

| 文档 | 内容 |
|------|------|
| [report.md](./report.md) | 竞品 / 行业调研（Apify、MCP 协议、Laravel 栈、沙箱、合规） |
| [PRD.md](./PRD.md) | 产品需求文档 v0.1（EARS 验收 + §10 六项已确认决策） |
| [review.md](./review.md) | 设计 / 研发评审（页面·状态·插件协议·表结构） |
| [tasks.md](./tasks.md) | 研发任务拆分 M0–M10 |
| [testcases.md](./testcases.md) | 测试用例与验收清单 |
| [retrospective.md](./retrospective.md) | 阶段复盘（目标达成 + 二期候选） |
| [action-items.md](./action-items.md) | 问题 / 待办汇总（A–E） |
| [scaffold/README.md](./scaffold/README.md) | 工程说明（架构 + 目录 + 快速起 + 模块完成度矩阵） |

## 路线图（二期，非阻断）

- [ ] 合规代理（Bright Data / Zyte）集成，提升反爬场景成功率
- [ ] 生产队列 worker（`QUEUE_CONNECTION=redis` + `php artisan queue:work`）
- [ ] 反向控制通道（WebSocket 替代单向 SSE）
- [ ] 多租户 / 计量计费（当前为一期单机单租户）
- [ ] 埋点聚合看板（字段已预留）

## 许可证

本项目源码按仓库 LICENSE 文件所示许可发布。插件市场为**免抽成**模式，开发者上传的插件版权归开发者所有。
