<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * 用户与权限（tasks M1 / PRD §4.1）
 * 密码与 Cookie 一律密文；普通用户仅见自己数据。
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $hidden = ['password_enc'];

    protected $fillable = [
        'name', 'email', 'password_enc',
        'role', 'tenant_id', 'status',
    ];

    protected $casts = ['tenant_id' => 'integer'];

    public function isAdmin(): bool   { return $this->role === 'admin'; }
    public function isDeveloper(): bool { return $this->role === 'developer'; }
}
