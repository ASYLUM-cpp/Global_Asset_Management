<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Asset extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'original_filename',
        'original_path',
        'file_extension',
        'file_size',
        'mime_type',
        'sha256_hash',
        'upload_source',
        'uploader_ip',
        'ingested_at',
        'group_classification',
        'group_confidence',
        'description',
        'pipeline_status',
        'preview_status',
        'review_status',
        'review_reason',
        'is_master',
        'derived_from',
        'storage_disk',
        'storage_path',
        'preview_path',
        'thumbnail_path',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'group_confidence' => 'decimal:2',
            'is_master' => 'boolean',
            'ingested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['group_classification', 'review_status', 'description', 'pipeline_status'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Asset {$eventName}");
    }

    // ── Relationships ──────────────────────────────────

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function master(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'derived_from');
    }

    public function derivedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'derived_from');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(AssetTag::class);
    }

    public function approvedTags(): HasMany
    {
        return $this->hasMany(AssetTag::class)->where('auto_approved', true);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssetVersion::class)->orderByDesc('version_number');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class, 'collection_asset')->withTimestamps();
    }

    // ── Scopes ─────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved');
    }

    public function scopePendingReview($query)
    {
        return $query->where('review_status', 'pending');
    }

    public function scopeInGroup($query, string $group)
    {
        return $query->where('group_classification', $group);
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->hasRole('Admin')) {
            return $query;
        }

        $groups = [];
        if ($user->hasRole('Food Team'))      $groups[] = 'Food';
        if ($user->hasRole('Media Team'))      $groups[] = 'Media';
        if ($user->hasRole('Marketing Team'))  { $groups[] = 'Business'; $groups[] = 'Lifestyle'; }

        return $query->whereIn('group_classification', $groups);
    }

    /**
     * Check whether the given user is allowed to access this asset (RBAC).
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        $groups = [];
        if ($user->hasRole('Food Team'))      $groups[] = 'Food';
        if ($user->hasRole('Media Team'))      $groups[] = 'Media';
        if ($user->hasRole('Marketing Team'))  { $groups[] = 'Business'; $groups[] = 'Lifestyle'; }

        return in_array($this->group_classification, $groups);
    }

    // ── Accessors ──────────────────────────────────────

    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
        return $bytes . ' B';
    }

    public function getGroupBadgeColorAttribute(): string
    {
        return match ($this->group_classification) {
            'Food'      => 'emerald',
            'Media'     => 'violet',
            'Business'  => 'blue',
            'Location'  => 'teal',
            'Nature'    => 'green',
            'Lifestyle' => 'pink',
            'Specialty' => 'orange',
            default     => 'slate',
        };
    }
}
