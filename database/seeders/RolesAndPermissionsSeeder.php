<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Resources gated by the admin panel and the abilities each exposes.
     */
    protected array $resources = [
        'branch',
        'participant',
        'event',
        'registration',
        'certificateTemplate',
        'user',
    ];

    protected array $abilities = ['view', 'create', 'update', 'delete', 'forceDelete'];

    /**
     * Resources a staff member may operate on (no users, no settings, no logs).
     */
    protected array $staffResources = [
        'branch',
        'participant',
        'event',
        'registration',
        'certificateTemplate',
    ];

    protected array $staffAbilities = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissionNames() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $admin = Role::findOrCreate('admin', 'web');
        $admin->syncPermissions(Permission::all());

        $staff = Role::findOrCreate('staff', 'web');
        $staff->syncPermissions($this->staffPermissionNames());

        User::query()->where('email', 'admin@admin.com')->first()?->assignRole($admin);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    protected function permissionNames(): array
    {
        $names = [];

        foreach ($this->resources as $resource) {
            foreach ($this->abilities as $ability) {
                $names[] = "{$resource}.{$ability}";
            }
        }

        $names[] = 'settings.manage';
        $names[] = 'emailLog.view';

        return $names;
    }

    /**
     * @return list<string>
     */
    protected function staffPermissionNames(): array
    {
        $names = [];

        foreach ($this->staffResources as $resource) {
            foreach ($this->staffAbilities as $ability) {
                $names[] = "{$resource}.{$ability}";
            }
        }

        return $names;
    }
}
