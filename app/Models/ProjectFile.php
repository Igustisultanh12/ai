<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectFile extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'path',
        'content',
        'size_bytes',
        'is_binary',
        'mime_type',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_binary' => 'boolean',
            'size_bytes' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
