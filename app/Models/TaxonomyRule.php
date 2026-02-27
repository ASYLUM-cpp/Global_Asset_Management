<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class TaxonomyRule extends Model
{
    protected $fillable = [
        'raw_term',
        'canonical_term',
        'group_hint',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get all active rules, cached for 24 hours.
     */
    public static function getCachedRules(): \Illuminate\Support\Collection
    {
        return Cache::remember('taxonomy_rules', 86400, function () {
            return static::where('is_active', true)->get();
        });
    }

    /**
     * Normalise a raw tag string using the rules table.
     */
    public static function normalize(string $rawTag): ?string
    {
        $rules = static::getCachedRules();
        $match = $rules->firstWhere('raw_term', strtolower(trim($rawTag)));
        return $match?->canonical_term;
    }

    /**
     * Clear the cached rules (call after import/edit).
     */
    public static function clearCache(): void
    {
        Cache::forget('taxonomy_rules');
    }
}
