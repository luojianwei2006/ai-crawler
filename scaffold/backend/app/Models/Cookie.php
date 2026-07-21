<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cookie/登录态管理（tasks M9 / PRD §4.6,§8）
 * cookie_enc 为 AES-256 密文；查询强制带 user_id（租户行隔离）。
 * status: valid | expired；运行前校验 expired_at。
 */
class Cookie extends Model
{
    protected $hidden = ['cookie_enc'];

    protected $fillable = [
        'user_id', 'site', 'cookie_enc',
        'expired_at', 'last_used_at', 'status',
    ];

    protected $casts = [
        'expired_at'   => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /** 是否已过期（阻断任务，PRD §8 Unwanted） */
    public function isExpired(): bool
    {
        return $this->expired_at === null
            || $this->expired_at->isPast()
            || $this->status === 'expired';
    }
}
