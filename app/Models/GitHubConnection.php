<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GitHubConnection extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'github_connections';

    protected $fillable = [
        'user_id',
        'github_username',
        'token_encrypted',
        'scopes',
        'status',
        'last_sync_at',
    ];

    protected $hidden = [
        'token_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'token_encrypted' => 'encrypted',
            'scopes' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
