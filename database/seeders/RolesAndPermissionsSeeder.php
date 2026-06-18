<?php

namespace Database\Seeders;

use App\Authorization\Permissions;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions(Permissions::forRole($role));
        }

        User::query()->where('email', 'admin@admin.com')->first()?->assignRole(UserRole::Admin->value);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
