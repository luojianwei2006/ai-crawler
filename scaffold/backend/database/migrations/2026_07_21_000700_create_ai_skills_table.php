<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // AI 技能（评审议题 T5 确认是否一期）：模型 + 提示/处理规则封装
    public function up(): void
    {
        Schema::create('ai_skills', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('model_provider_id');
            $table->json('prompt_config_json')->nullable(); // 提示词/处理规则
            $table->timestamps();

            $table->foreign('model_provider_id')->references('id')->on('model_providers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_skills');
    }
};
