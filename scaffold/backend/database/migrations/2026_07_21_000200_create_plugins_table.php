<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 插件定义与上下架（tasks M3 / PRD §4.4 / §4.8）
    public function up(): void
    {
        Schema::create('plugins', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // 插件标题
            $table->string('developer');            // 开发者
            $table->string('version');              // 语义化版本
            $table->text('description');            // 说明
            $table->timestamp('updated_at_field');  // 更新日期（manifest 声明值）
            $table->json('manifest_json');          // PluginManifest 全量
            $table->unsignedBigInteger('dev_user_id');
            $table->string('status')->default('pending'); // pending|published|rejected|removed|violation
            $table->json('scan_report_json')->nullable(); // 上架安全扫描报告
            $table->timestamps();

            $table->foreign('dev_user_id')->references('id')->on('users');
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugins');
    }
};
