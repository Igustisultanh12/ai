<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelMultiplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'multiplier',
        'source',
        'description',
        'supports_tools',
        'supports_vision',
        'context_window',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:4',
            'supports_tools' => 'boolean',
            'supports_vision' => 'boolean',
            'context_window' => 'integer',
        ];
    }
}
