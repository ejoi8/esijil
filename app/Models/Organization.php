<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The tenant. All tenant-owned models belong to one organization via the
 * BelongsToOrganization trait; users belong to many via the pivot.
 */
#[Fillable([
    'name',
    'slug',
    'locale',
    'settings',
    'status',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * A per-organization notification preference, defaulting to enabled when the
     * organization has not configured it. Stored under settings.notifications.
     */
    public function notifies(string $key): bool
    {
        return (bool) data_get($this->settings, "notifications.{$key}", true);
    }
}
