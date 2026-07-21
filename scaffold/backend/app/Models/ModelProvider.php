<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 模型供应商（tasks M2 / PRD §4.7）：OpenAI 兼容
 * api_key_enc 为 AES-256 密文，禁止明文、禁止日志打印。
 */
class ModelProvider extends Model
{
    protected $hidden = ['api_key_enc'];

    protected $fillable = [
        'name', 'vendor', 'base_url',
        'api_key_enc', 'model', 'quota', 'used', 'status',
    ];

    protected $casts = ['quota' => 'integer', 'used' => 'integer'];
}
