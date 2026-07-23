<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 用户与权限体系（tasks M1 / PRD §4.1）
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password_enc');          // bcrypt 哈希（AuthController / DatabaseSeeder 统一用 Hash::make）
            $table->string('role')->default('user'); // admin | user | developer
            $table->unsignedBigInteger('tenant_id')->nullable(); // 一期单租户，预留
            $table->string('status')->default('active');   // active | disabled
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
