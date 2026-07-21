<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 插件定义与上下架状态机（tasks M3 / PRD §4.4,§4.8）
 * status: pending | published | rejected | removed | violation
 */
class Plugin extends Model
{
    protected $fillable = [
        'name', 'developer', 'version', 'description',
        'updated_at_field', 'manifest_json', 'dev_user_id',
        'status', 'scan_report_json',
    ];

    protected $casts = [
        'manifest_json'     => 'array',
        'scan_report_json' => 'array',
        'updated_at_field' => 'datetime',
    ];

    public function devUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dev_user_id');
    }

    public function userPlugins(): HasMany
    {
        return $this->hasMany(UserPlugin::class);
    }
}
