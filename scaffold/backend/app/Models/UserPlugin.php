<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户已安装插件（tasks M8 / PRD §4.4）
 * 一条记录 = 某用户安装了某插件，并记录启用状态。
 */
class UserPlugin extends Model
{
    protected $fillable = [
        'user_id',
        'plugin_id',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plugin(): BelongsTo
    {
        return $this->belongsTo(Plugin::class);
    }
}
