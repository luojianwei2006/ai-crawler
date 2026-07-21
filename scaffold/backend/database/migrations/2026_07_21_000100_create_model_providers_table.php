<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 模型管理（tasks M2 / PRD §4.7）：GLM/DeepSeek/GPT 均 OpenAI 兼容
    public function up(): void
    {
        Schema::create('model_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('vendor');              // glm | deepseek | openai
            $table->string('base_url');           // OpenAI 兼容端点
            $table->string('api_key_enc');       // AES-256 密文
            $table->string('model');              // 如 glm-4-plus / deepseek-chat / gpt-4o
            $table->unsignedInteger('quota')->default(0);   // 额度（token 或请求）
            $table->unsignedInteger('used')->default(0);
            $table->string('status')->default('active');    // active | disabled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_providers');
    }
};
