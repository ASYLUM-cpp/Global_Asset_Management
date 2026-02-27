<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Collection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'cover_image',
        'access_level',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'collection_asset')->withTimestamps();
    }

    // Check if a role has access to this collection
    public function isAccessibleByRole(string $roleName): bool
    {
        if ($this->access_level === 'public') return true;

        return $this->permittedRoles()->contains($roleName);
    }

    public function permittedRoles()
    {
        return \Illuminate\Support\Facades\DB::table('collection_permissions')
            ->where('collection_id', $this->id)
            ->pluck('role_name');
    }
}
