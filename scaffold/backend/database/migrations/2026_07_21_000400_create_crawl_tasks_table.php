<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 采集任务状态机（tasks M7 / PRD §3 主流程）
    public function up(): void
    {
        Schema::create('crawl_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plugin_id');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('params_json');   // {url, cookie(密文), expired_at, depth, ...}
            $table->string('status')->default('pending'); // pending|running|success|failed|aborted
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('plugin_id')->references('id')->on('plugins');
            $table->foreign('model_id')->references('id')->on('model_providers');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawl_tasks');
    }
};
