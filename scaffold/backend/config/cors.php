<?php

/**
 * CORS 配置跨域资源共享（私有化自托管场景）
 *
 * 开发时前端通过 Vite 代理（/api → localhost:8000）同源访问，不走 CORS；
 * 生产部署时若前端与后端不在同域，此配置允许来自任意域的 API 请求。
 * 由于已移除 withCredentials（纯 Bearer Token 鉴权），supports_credentials 可关闭。
 */

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
