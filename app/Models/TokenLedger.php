<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenLedger extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'project_id',
        'conversation_id',
        'run_id',
        'idempotency_key',
        'correlation_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'raw_tokens',
        'multiplier_snapshot',
        'weighted_tokens',
        'data_source', // actual, estimated, unknown
        'status', // success, failed, cancelled
        'latency_ms',
        'attempt_count',
        'error_code',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'raw_tokens' => 'integer',
            'multiplier_snapshot' => 'decimal:4',
            'weighted_tokens' => 'decimal:4',
            'latency_ms' => 'integer',
            'attempt_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
