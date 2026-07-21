# 阶段三：设计与研发评审材料

> 关联文档：`PRD.md`（v0.1，6 项决策已确认）、`report.md`（竞品与行业分析）
> 一期边界（已确认）：开发者为主 · 私有化自托管 · 独立子进程沙箱 · 单机单租户+手动运行 · 内部 MCP 风格接口 · 市场免抽成
> 生成日期：2026-07-21

---

## 1. 评审目标与范围

- **目标**：在 PRD 基础上，对齐设计边界与研发可行性，识别技术依赖、接口契约、数据结构、兼容性与实现风险，把待办问题拆成可分配的事项。
- **不在一期范围**：多租户 SaaS、任务调度/定时、市场分成计费、外部 MCP 客户端对接（Claude/Cursor）、小白可视化拖拽。
- **评审交付**：① 评审议题清单 ② 设计师视角（页面/状态/交互/验收点）③ 研发视角（依赖/接口/表结构/风险）④ 待办事项拆分建议。

---

## 2. 评审议题清单（待评审会确认）

| # | 议题 | 归属 | 决策点 |
|---|------|------|--------|
| T1 | 插件 manifest 钩子点最终清单 | 研发 | onBeforeCrawl / onAfterParse / onError 是否够？是否加 onAfterCrawl |
| T2 | 子进程沙箱资源配额默认值 | 研发+运维 | 内存/超时/并发上限（一期单机） |
| T3 | 一期是否支持 JS 渲染（Panther） | 研发 | 决定私有化镜像是否预装 headless Chrome |
| T4 | 实时日志 SSE vs WebSocket | 研发 | 一期仅 SSE（无反向控制），预留 WebSocket 升级 |
| T5 | "AI 技能"与"模型"的关系定义 | 产品+研发 | 技能=模型+提示/处理规则封装？一期是否做技能层 |
| T6 | 上架安全扫描规则清单 | 研发+安全 | 危险函数/越权声明静态检测规则 |
| T7 | Cookie 加密密钥管理方案 | 研发 | APP_KEY 单钥 / KMS / 每租户密钥 |
| T8 | 爬取深度上限与限速默认值 | 研发 | 防失控、防封禁 |

---

## 3. 设计师视角

### 3.1 页面清单（按模块）
**管理后台（Admin）**
- 登录 / 权限入口
- 用户管理：用户列表、用户详情/编辑（角色、状态）
- AI 技能配置：模型供应商列表、模型配置（增删改+测试）、默认参数
- 插件审核：审核队列（待审/已上架/已下架/违规）、审核详情（安全扫描报告、驳回原因）
- 平台概览（可选，一期可省）

**用户/开发者后台（User/Developer）**
- 登录 / 修改密码（强制旧密码+强度校验）
- 插件市场：浏览 / 搜索 / 插件详情 / 安装
- 我的插件：已安装列表（启用 / 停用 / 卸载 / 详情）
- **运行页（核心）**：左栏参数表单 + 模型选择 + 运行按钮；右栏实时日志面板
- 插件开发：下载 PHP 模板、上传插件、我的上传列表（标题/开发者/版本/更新日期/说明）

### 3.2 状态清单（状态机）
| 对象 | 状态流转 |
|------|----------|
| 插件（市场侧） | 上传中 → 待审核 → (审核中) → 已上架 / 已驳回 / 已下架 / 违规下架 |
| 插件实例（用户侧） | 未安装 → 已安装(停用) → 已启用 ⇄ 已停用 |
| 采集任务 | 待运行 → 运行中 → 成功 / 失败 / 已中止 |
| 模型 | 草稿 → 已启用 / 已停用 → 已删除 |
| Cookie | 有效 → 即将过期(临近 expired_at) → 已过期(阻断) |
| 租户（预留） | 启用 / 禁用（一期单租户，仅留字段） |

### 3.3 交互边界
- **运行页双栏**：左参数、右日志；日志自动滚底 + 可暂停滚动；error 级标红并附错误码与建议。
- **权限可见性**：普通用户仅见自己的插件与 Cookie；管理员可见全量但**不可见用户明文 Cookie**（仅元信息）。
- **上传约束**：文件类型（.zip）、大小上限；扫描进行中禁用重复提交；驳回须展示风险项清单。
- **表单校验**：网址格式；Cookie 必填失效日期；爬取深度为正整数且 ≤ 上限；模型必选；校验失败就地提示。
- **模型测试反馈**：点击测试后展示 延迟 / 返回内容 / token 用量 / 错误码（复用运行日志样式）。

### 3.4 设计验收点
- [ ] 双栏布局在窄屏（<1024px）正确降级（如上下堆叠）
- [ ] 日志支持级别筛选（info/warn/error）与进度百分比
- [ ] 任务结束态（`done`）在 UI 明确呈现，可回溯历史日志
- [ ] 所有空态 / 加载态 / 错误态均有设计
- [ ] 越权数据（他人插件、明文 Cookie）在 UI 层不可见
- [ ] 上架驳回、Cookie 过期、任务失败均有明确提示与后续动作入口

---

## 4. 研发视角

### 4.1 技术依赖与选型（一期）
| 层 | 选型 | 说明 |
|----|------|------|
| 后端 | PHP 8.2+ / **Laravel 11/12** | Service Provider + Composer 承载插件自动注册；队列/加密/广播原生支持 |
| 前端 | **Vue3 + Pinia + Vite** | SSE 用原生 EventSource |
| 数据库 | **MySQL 8** | 事务/JSON 字段存 manifest |
| 爬虫 | guzzlehttp/guzzle + symfony/dom-crawler（+ Goutte） | 静态/服务端渲染页 |
| JS 渲染 | symfony/panther（headless Chrome） | T3 确认一期是否启用 |
| AI | openai-php/laravel（OpenAI 兼容） | GLM/DeepSeek/GPT 仅 base_url+model 不同；可选 One-API 网关聚合密钥 |
| 实时 | **SSE（原生 PHP flush）** | 一期单向；反向控制再升 WebSocket（Swoole/Ratchet） |
| 加密 | Laravel Encryption（AES-256） | Cookie/密码密文存储 |
| 权限 | spatie/laravel-permission 或 php-casbin | RBAC（管理员/普通用户/开发者） |
| 沙箱 | **proc_open 子进程 + disable_functions + 白名单** | PHP 无 autoload 级隔离，必须分进程 |
| 扫描 | 静态分析（正则/AST）危险函数 | 上架前自动安全扫描 |

### 4.2 接口设计（内部 MCP 风格插件协议草案）
**插件清单 PluginManifest（插件包内 `manifest.json`）**
```json
{
  "name": "example-site-crawler",
  "developer": "dev_team",
  "version": "1.0.0",
  "description": "采集 example.com 的文章与列表链接",
  "updated_at": "2026-07-21",
  "hooks": ["onBeforeCrawl", "onAfterParse", "onError"],
  "permissions": { "network": true, "filesystem": false, "command": false },
  "tools": [
    { "name": "crawl", "description": "执行一次采集", "input": { "url": "string", "depth": "int" } }
  ],
  "resources": [
    { "name": "result", "schema": { "title": "string", "url": "string", "content": "text" } }
  ]
}
```
**核心 API（REST，内部 MCP 风格，一期不对外暴露为 MCP Server）**
| 方法 | 路径 | 说明 |
|------|------|------|
| POST | `/api/plugins/upload` | 上传插件包（multipart），触发自动安全扫描 → 置"待审核" |
| GET | `/api/market` | 插件市场列表（已上架） |
| POST | `/api/plugins/{id}/install` | 用户安装 |
| POST | `/api/plugins/{id}/toggle` | 启用/停用 |
| POST | `/api/tasks/run` | 创建任务 `{plugin_id, params:{url,cookie,expired_at,model,depth}}` 并启动子进程 |
| GET | `/api/tasks/{id}/stream` | **SSE** 实时日志流 |
| POST | `/api/models` | 模型增/改/删 |
| POST | `/api/models/{id}/test` | 模型测试（最小 payload，回显延迟/token/错误码） |

### 4.3 数据结构（核心表，一期）
```sql
users(id, name, email, password_enc, role, tenant_id, status, created_at)
model_providers(id, name, vendor, base_url, api_key_enc, model, quota, used, status)
plugins(id, name, developer, version, description, updated_at, manifest_json,
         dev_user_id, status, scan_report_json, created_at)
user_plugins(id, user_id, plugin_id, enabled)
crawl_tasks(id, user_id, plugin_id, model_id, params_json, status,
            started_at, ended_at)
task_logs(id, task_id, level, message, created_at)
cookies(id, user_id, site, cookie_enc, expired_at, last_used_at, status)
ai_skills(id, name, model_provider_id, prompt_config_json)   -- T5 确认是否一期
```

### 4.4 兼容性与实现风险
| 风险 | 影响 | 缓解 |
|------|------|------|
| PHP 无 autoload 级隔离 | 插件代码可能污染主进程 | **强制子进程/容器执行**，禁危险函数，权限白名单 |
| Panther 需 headless Chrome | 私有化镜像需预装 chrome/chromedriver | T3 确认一期是否支持 JS 渲染；镜像文档化依赖 |
| SSE 在 PHP-FPM 缓冲/超时 | 日志不实时或断流 | 关输出缓冲、设合理超时；反向控制改 WebSocket |
| Cookie 密钥管理 | 泄露即全站沦陷 | AES-256 + 密钥隔离（T7）；禁止日志打印明文 |
| 安全扫描误报/漏报 | 误伤正常插件 / 漏过风险代码 | 规则清单化（T6）+ 人工审核兜底 |
| 爬取深度失控 / 反爬封禁 | 任务卡死、IP 被封 | 深度上限 + 限速 + 可选合规代理 |
| 登录态采集法律风险 | CFAA/ToS/GDPR | 默认禁敏感数据；robots/限速/退出机制；用户告知前置 |

### 4.5 关键依赖与阻塞项
- **阻塞**：T2（沙箱配额）、T3（JS 渲染）、T6（扫描规则）需在评审会拍板，否则研发无法定稿。
- **依赖**：私有化部署镜像（PHP+Chrome+MySQL）环境标准；AI 模型供应商密钥（GLM/DeepSeek/GPT）由管理员配置。
- **外部**：合规代理（Bright Data/Zyte）为可选基础设施，不阻塞一期。

---

## 5. 评审结论沉淀与待办拆分建议

> 评审通过后，建议将结论**上传到项目资料库**，并把以下待办**拆成事项**分配给对应负责人（TAPD 当前未连通，先以清单交付）。

| 待办 | 归属角色 | 关联交付物 | 关注人 |
|------|----------|------------|--------|
| 定稿插件 manifest 钩子点与权限模型 | 技术负责人 | `review.md §4.2` | 产品、开发者 |
| 输出子进程沙箱资源配额与超时方案 | 技术负责人+运维 | `review.md §4.5` | 产品 |
| 确认一期 JS 渲染支持与镜像依赖 | 技术负责人 | `review.md T3` | 运维 |
| 编写上架安全扫描规则清单 | 研发+安全 | `review.md T6` | 技术负责人 |
| 定稿核心表结构与 API 契约 | 后端研发 | `review.md §4.3/4.2` | 产品、测试 |
| 产出运行页 + 市场 + 开发页高保真设计 | 设计师 | `review.md §3` | 产品、前端 |
| 基于 PRD 生成测试用例（含边界/异常） | 测试 | `PRD.md §8/§9` | 产品、研发 |

---

## 6. 流转提醒
- 评审结论请**沉淀到项目资料库**，关联 `PRD.md` 与 `report.md`，形成统一上下文。
- 上述待办请**创建事项**分配给技术负责人、设计师、测试同学，并**添加关注人**。
- 下一步进入**阶段四（研发跟进）**：可将技术任务拆子事项，附 PRD/设计稿/评审结论，跟踪进度与阻塞。
