<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password_enc',
        'role',
        'status',
        'tenant_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_enc',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * 鉴权使用的密码字段为 password_enc（bcrypt 哈希，见 AuthController / DatabaseSeeder）。
     * 覆盖默认 getAuthPassword，使 Auth::attempt 能正确校验该列。
     */
    public function getAuthPassword(): string
    {
        return $this->password_enc;
    }

    /**
     * 是否管理员（role=admin）。供 Admin 中间件与控制器 abort_if 使用。
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
