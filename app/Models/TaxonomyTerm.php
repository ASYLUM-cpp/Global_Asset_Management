<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * TaxonomyTerm — controlled vocabulary entry loaded from the GAM spreadsheet.
 *
 * Stores groups, keywords, projects, doc keywords, viz keywords, and geo states.
 * Used by TaxonomyService to build AI prompts and by ApplyTaxonomy to validate tags.
 */
class TaxonomyTerm extends Model
{
    protected $fillable = [
        'term_type',
        'group_code',
        'facet',
        'term_code',
        'term_label',
        'parent_code',
        'description',
        'extra',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'extra'     => 'array',
        'is_active' => 'boolean',
    ];

    /* ── Scopes ────────────────────────────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('term_type', $type);
    }

    public function scopeForGroup($query, string $groupCode)
    {
        return $query->where('group_code', $groupCode);
    }

    /* ── Static helpers (cached) ───────────────────────────────────────────── */

    public static function getPrimaryGroups(): array
    {
        return Cache::remember('taxonomy:primary_groups', 86400, function () {
            return static::active()
                ->ofType('primary_group')
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });
    }

    public static function getDocGroups(): array
    {
        return Cache::remember('taxonomy:doc_groups', 86400, function () {
            return static::active()
                ->ofType('doc_group')
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });
    }

    public static function getKeywordsForGroup(string $groupCode): array
    {
        return Cache::remember("taxonomy:keywords:{$groupCode}", 86400, function () use ($groupCode) {
            return static::active()
                ->forGroup($groupCode)
                ->whereIn('term_type', ['keyword', 'keyword_category'])
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });
    }

    public static function getDocKeywordsForGroup(string $groupCode): array
    {
        return Cache::remember("taxonomy:doc_keywords:{$groupCode}", 86400, function () use ($groupCode) {
            return static::active()
                ->ofType('doc_keyword')
                ->forGroup($groupCode)
                ->orderBy('sort_order')
                ->get()
                ->toArray();
        });
    }

    public static function getAllTermLabels(): array
    {
        return Cache::remember('taxonomy:all_labels', 86400, function () {
            return static::active()
                ->pluck('term_label')
                ->map(fn ($l) => strtolower($l))
                ->unique()
                ->values()
                ->toArray();
        });
    }

    public static function clearTaxonomyCache(): void
    {
        $keys = [
            'taxonomy:primary_groups',
            'taxonomy:doc_groups',
            'taxonomy:all_labels',
        ];

        foreach (static::active()->distinct()->pluck('group_code') as $gc) {
            $keys[] = "taxonomy:keywords:{$gc}";
            $keys[] = "taxonomy:doc_keywords:{$gc}";
        }

        foreach ($keys as $key) {
            Cache::forget($key);
        }
    }
}
