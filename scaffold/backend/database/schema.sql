-- =============================================================================
-- AI 内容采集插件平台 —— 数据库 Schema（MySQL 8）
-- -----------------------------------------------------------------------------
-- 本文件由 scaffold/backend/database/migrations/*.php 汇总生成，等价于
-- `php artisan migrate` 在 MySQL 8 上实际产出的表结构。
--
-- 使用方式：
--   mysql -u <user> -p <db_name> < database/schema.sql
-- 或在已建好的库里直接执行本文件。
--
-- 注意：
--   1) 唯一可信来源（source of truth）仍是 migrations 目录；迁移有变动请重新生成本文件。
--   2) 本文件只含 DDL（建表 / 索引 / 外键），不含初始数据。
--      初始数据由 database/seeders/DatabaseSeeder.php 在 `php artisan migrate --seed`
--      时写入（管理员 admin@example.com / admin123、示例插件、默认模型供应商），
--      其中密码经 bcrypt、API Key / Cookie 经 AES-256 加密，属运行时生成，未在此固化。
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- users —— 用户与权限体系（M1 / PRD §4.1）
-- -----------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(255) NOT NULL,
  `email`        VARCHAR(255) NOT NULL,
  `password_enc` VARCHAR(255) NOT NULL COMMENT 'bcrypt 哈希（AuthController / DatabaseSeeder 统一用 Hash::make）',
  `role`         VARCHAR(255) NOT NULL DEFAULT 'user' COMMENT 'admin | user | developer',
  `tenant_id`    BIGINT UNSIGNED NULL COMMENT '一期单租户，预留',
  `status`       VARCHAR(255) NOT NULL DEFAULT 'active' COMMENT 'active | disabled',
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- model_providers —— AI 模型管理（M2 / PRD §4.7），GLM/DeepSeek/GPT 均 OpenAI 兼容
-- -----------------------------------------------------------------------------
CREATE TABLE `model_providers` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `vendor`      VARCHAR(255) NOT NULL COMMENT 'glm | deepseek | openai',
  `base_url`    VARCHAR(255) NOT NULL COMMENT 'OpenAI 兼容端点',
  `api_key_enc` VARCHAR(255) NOT NULL COMMENT 'AES-256 密文',
  `model`       VARCHAR(255) NOT NULL COMMENT '如 glm-4-plus / deepseek-chat / gpt-4o',
  `quota`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '额度（token 或请求）',
  `used`        INT UNSIGNED NOT NULL DEFAULT 0,
  `status`      VARCHAR(255) NOT NULL DEFAULT 'active' COMMENT 'active | disabled',
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- personal_access_tokens —— Sanctum API 令牌（接入 auth:sanctum 鉴权）
-- -----------------------------------------------------------------------------
CREATE TABLE `personal_access_tokens` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` VARCHAR(255) NOT NULL,
  `tokenable_id`   BIGINT UNSIGNED NOT NULL,
  `name`           TEXT NOT NULL,
  `token`          VARCHAR(64) NOT NULL,
  `abilities`      TEXT NULL,
  `last_used_at`   TIMESTAMP NULL,
  `expires_at`     TIMESTAMP NULL,
  `created_at`     TIMESTAMP NULL,
  `updated_at`     TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  INDEX `personal_access_tokens_expires_at_index` (`expires_at`),
  INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- plugins —— 插件定义与上下架状态机（M3 / PRD §4.4, §4.8）
-- -----------------------------------------------------------------------------
CREATE TABLE `plugins` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(255) NOT NULL COMMENT '插件标题',
  `developer`        VARCHAR(255) NOT NULL COMMENT '开发者',
  `version`          VARCHAR(255) NOT NULL COMMENT '语义化版本',
  `description`      TEXT NOT NULL COMMENT '说明',
  `updated_at_field` TIMESTAMP NOT NULL COMMENT '更新日期（manifest 声明值）',
  `manifest_json`    JSON NOT NULL COMMENT 'PluginManifest 全量',
  `dev_user_id`      BIGINT UNSIGNED NOT NULL,
  `status`           VARCHAR(255) NOT NULL DEFAULT 'pending' COMMENT 'pending|published|rejected|removed|violation',
  `scan_report_json` JSON NULL COMMENT '上架安全扫描报告',
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `plugins_status_name_index` (`status`, `name`),
  CONSTRAINT `plugins_dev_user_id_foreign` FOREIGN KEY (`dev_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- user_plugins —— 用户侧插件实例：安装 / 启停状态机（M8 / PRD §4.4）
-- -----------------------------------------------------------------------------
CREATE TABLE `user_plugins` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    BIGINT UNSIGNED NOT NULL,
  `plugin_id`  BIGINT UNSIGNED NOT NULL,
  `enabled`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '停用 | 启用',
  `created_at` TIMESTAMP NULL,
  `updated_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_plugins_user_id_plugin_id_unique` (`user_id`, `plugin_id`),
  CONSTRAINT `user_plugins_user_id_foreign`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`),
  CONSTRAINT `user_plugins_plugin_id_foreign` FOREIGN KEY (`plugin_id`) REFERENCES `plugins` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- crawl_tasks —— 采集任务状态机（M7 / PRD §3 主流程）
-- -----------------------------------------------------------------------------
CREATE TABLE `crawl_tasks` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`     BIGINT UNSIGNED NOT NULL,
  `plugin_id`   BIGINT UNSIGNED NOT NULL,
  `model_id`    BIGINT UNSIGNED NULL,
  `params_json` JSON NOT NULL COMMENT '{url, cookie(密文), expired_at, depth, ...}',
  `status`      VARCHAR(255) NOT NULL DEFAULT 'pending' COMMENT 'pending|running|success|failed|aborted',
  `started_at`  TIMESTAMP NULL,
  `ended_at`    TIMESTAMP NULL,
  `created_at`  TIMESTAMP NULL,
  `updated_at`  TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `crawl_tasks_user_id_status_index` (`user_id`, `status`),
  CONSTRAINT `crawl_tasks_user_id_foreign`   FOREIGN KEY (`user_id`)   REFERENCES `users` (`id`),
  CONSTRAINT `crawl_tasks_plugin_id_foreign` FOREIGN KEY (`plugin_id`) REFERENCES `plugins` (`id`),
  CONSTRAINT `crawl_tasks_model_id_foreign`  FOREIGN KEY (`model_id`)  REFERENCES `model_providers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- task_logs —— 实时日志落库（M6 / PRD §4.9），SSE 推流 + 可回溯
-- -----------------------------------------------------------------------------
CREATE TABLE `task_logs` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id`    BIGINT UNSIGNED NOT NULL,
  `level`      VARCHAR(255) NOT NULL DEFAULT 'info' COMMENT 'info | warn | error',
  `message`    TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `task_logs_task_id_created_at_index` (`task_id`, `created_at`),
  CONSTRAINT `task_logs_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `crawl_tasks` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- cookies —— Cookie / 登录态管理（M9 / PRD §4.6），AES-256 密文 + 租户行隔离
-- -----------------------------------------------------------------------------
CREATE TABLE `cookies` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`      BIGINT UNSIGNED NOT NULL,
  `site`         VARCHAR(255) NOT NULL COMMENT '目标域',
  `cookie_enc`   TEXT NOT NULL COMMENT 'AES-256 密文，禁止明文',
  `expired_at`   TIMESTAMP NOT NULL COMMENT '失效日期，运行前校验',
  `last_used_at` TIMESTAMP NULL,
  `status`       VARCHAR(255) NOT NULL DEFAULT 'valid' COMMENT 'valid | expired',
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  INDEX `cookies_user_id_site_index` (`user_id`, `site`),
  CONSTRAINT `cookies_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- ai_skills —— AI 技能（评审议题 T5 确认是否一期）：模型 + 提示/处理规则封装
-- -----------------------------------------------------------------------------
CREATE TABLE `ai_skills` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                VARCHAR(255) NOT NULL,
  `model_provider_id`   BIGINT UNSIGNED NOT NULL,
  `prompt_config_json`  JSON NULL COMMENT '提示词/处理规则',
  `created_at`          TIMESTAMP NULL,
  `updated_at`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `ai_skills_model_provider_id_foreign` FOREIGN KEY (`model_provider_id`) REFERENCES `model_providers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
