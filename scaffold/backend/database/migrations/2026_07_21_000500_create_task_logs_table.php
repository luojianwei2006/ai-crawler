<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // 实时日志落库（tasks M6 / PRD §4.9）：SSE 推流 + 可回溯
    public function up(): void
    {
        Schema::create('task_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->string('level')->default('info'); // info | warn | error
            $table->text('message');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('crawl_tasks');
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_logs');
    }
};
