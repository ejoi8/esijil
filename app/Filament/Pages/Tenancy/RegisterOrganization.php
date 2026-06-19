<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\UserRole;
use App\Models\Organization;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Self-serve "create organization" page (Filament tenant registration). The
 * creating user joins the new organization as its admin.
 */
class RegisterOrganization extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Create organization';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->rule('alpha_dash')
                    ->unique(Organization::class, 'slug')
                    ->helperText('Used in the URL, e.g. /auth/your-slug.'),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        $organization = Organization::create($data);

        $user = Auth::user();
        $organization->users()->attach($user);

        // The creator administers the new organization. With spatie teams this
        // scopes the role to this organization; without teams it's global (no-op
        // for an existing admin).
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->getKey());
        $user->assignRole(UserRole::Admin->value);

        return $organization;
    }
}
