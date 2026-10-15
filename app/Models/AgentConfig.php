<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'provider',
        'base_url',
        'api_key_encrypted',
        'default_model',
        'allowed_models',
        'max_output_tokens',
        'temperature',
        'timeout_seconds',
        'concurrency_limit',
        'max_iterations',
        'is_active',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'api_key_encrypted' => 'encrypted',
            'allowed_models' => 'array',
            'max_output_tokens' => 'integer',
            'temperature' => 'float',
            'timeout_seconds' => 'integer',
            'concurrency_limit' => 'integer',
            'max_iterations' => 'integer',
            'is_active' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public function getMaskedApiKeyAttribute(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        $length = strlen($this->api_key_encrypted);
        if ($length <= 8) {
            return '••••••••';
        }

        $prefix = substr($this->api_key_encrypted, 0, 6);
        $suffix = substr($this->api_key_encrypted, -4);
        return "{$prefix}••••••••{$suffix}";
    }
}
