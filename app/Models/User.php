<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use RuntimeException;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
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
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'auth'
            && $this->hasAnyRole(UserRole::values());
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
