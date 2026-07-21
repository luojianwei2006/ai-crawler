<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Cookie/登录态管理（tasks M9 / PRD §4.6 / §8）：AES-256 密文 + 租户行隔离
    public function up(): void
    {
        Schema::create('cookies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('site');               // 目标域
            $table->text('cookie_enc');         // AES-256 密文，禁止明文
            $table->timestamp('expired_at');    // 失效日期，运行前校验
            $table->timestamp('last_used_at')->nullable();
            $table->string('status')->default('valid'); // valid | expired
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->index(['user_id', 'site']); // 强制租户行隔离
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookies');
    }
};
