<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'description',
        'template',
        'visibility',
        'status',
        'github_repo',
        'github_branch',
        'auto_push_github',
        'active_checkpoint_id',
    ];

    protected function casts(): array
    {
        return [
            'auto_push_github' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function checkpoints(): HasMany
    {
        return $this->hasMany(ProjectCheckpoint::class);
    }

    public function activeCheckpoint(): BelongsTo
    {
        return $this->belongsTo(ProjectCheckpoint::class, 'active_checkpoint_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ProjectRun::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(ProjectExport::class);
    }
}
