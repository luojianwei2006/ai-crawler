<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 用户侧插件实例（tasks M8 / PRD §4.4）：安装/启停状态机
    public function up(): void
    {
        Schema::create('user_plugins', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plugin_id');
            $table->boolean('enabled')->default(false); // 停用 | 启用
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('plugin_id')->references('id')->on('plugins');
            $table->unique(['user_id', 'plugin_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plugins');
    }
};
