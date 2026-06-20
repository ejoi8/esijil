<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model. Provides the organization relationship
 * that Filament tenancy scopes resources on, and auto-fills organization_id from
 * the current tenant on insert (admin panel + tests). Code paths with no tenant
 * — the public registration flow, seeders, jobs — set organization_id explicitly.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null && ($tenant = Filament::getTenant()) !== null) {
                $model->setAttribute('organization_id', $tenant->getKey());
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
