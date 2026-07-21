<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 采集任务状态机（tasks M7 / PRD §3 主流程）
 * status: pending | running | success | failed | aborted
 */
class CrawlTask extends Model
{
    protected $fillable = [
        'user_id', 'plugin_id', 'model_id',
        'params_json', 'status', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'params_json' => 'array',
        'started_at'  => 'datetime',
        'ended_at'    => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(TaskLog::class);
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
