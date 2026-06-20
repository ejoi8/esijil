<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use RuntimeException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            // Platform (super-admin) panel: global operators only.
            'platform' => (bool) $this->is_platform_admin,
            // Tenant panel: any authenticated user; per-organization roles
            // (spatie teams) govern what they may do inside each tenant.
            'auth' => true,
            default => false,
        };
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class);
    }

    /**
     * @return Collection<int, Organization>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->organizations;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        // Platform admins can enter any tenant (cross-tenant support/operation);
        // everyone else only the organizations they belong to.
        return $this->is_platform_admin || $this->organizations()->whereKey($tenant)->exists();
    }

    public function delete(): ?bool
    {
        // Checked here (not in a deleting event) because the spatie HasRoles
        // trait removes the user's role pivots on the deleting event, which
        // would run before any deleting listener of ours.
        if ($this->isLastAdministrator()) {
            throw new RuntimeException('Cannot delete the last administrator.');
        }

        return parent::delete();
    }

    protected function isLastAdministrator(): bool
    {
        if (! $this->roles()->where('name', UserRole::Admin->value)->exists()) {
            return false;
        }

        return static::query()
            ->whereKeyNot($this->getKey())
            ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Admin->value))
            ->doesntExist();
    }
}
