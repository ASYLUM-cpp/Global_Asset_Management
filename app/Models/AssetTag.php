<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetTag extends Model
{
    protected $fillable = [
        'asset_id',
        'tag',
        'confidence',
        'auto_approved',
        'is_manual',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'auto_approved' => 'boolean',
            'is_manual' => 'boolean',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // Confidence colour helper
    public function getConfidenceColorAttribute(): string
    {
        if ($this->confidence >= 0.70) return 'emerald';
        if ($this->confidence >= 0.50) return 'amber';
        return 'red';
    }
}
