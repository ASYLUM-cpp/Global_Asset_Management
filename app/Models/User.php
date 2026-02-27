<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\Traits\CausesActivity;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, CausesActivity, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ── Relationships ──────────────────────────────────

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'uploaded_by');
    }

    public function reviewedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'reviewed_by');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class, 'created_by');
    }

    // ── Accessors ──────────────────────────────────────

    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', $this->name);
        return strtoupper(
            substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1)
        );
    }

    public function getPrimaryRoleAttribute(): string
    {
        return $this->roles->first()?->name ?? 'User';
    }

    /**
     * Get the allowed asset groups for this user's role.
     */
    public function getAllowedGroupsAttribute(): array
    {
        if ($this->hasRole('Admin')) return ['Food', 'Media', 'Business', 'Location', 'Nature', 'Lifestyle', 'Specialty'];
        
        $groups = [];
        if ($this->hasRole('Food Team'))      $groups[] = 'Food';
        if ($this->hasRole('Media Team'))      $groups[] = 'Media';
        if ($this->hasRole('Marketing Team'))  { $groups[] = 'Business'; $groups[] = 'Lifestyle'; }

        return $groups;
    }
}
