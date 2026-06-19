<?php

namespace Database\Seeders;

use App\Authorization\Permissions;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeamId = $registrar->getPermissionsTeamId();

        $registrar->forgetCachedPermissions();

        // Role + permission definitions are global (team_id null) and shared
        // across every organization; assignments to users are per-team. Spatie
        // resolves a role by null-team OR current-team, so global defs are found
        // when assigning within any team.
        $registrar->setPermissionsTeamId(null);

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')
                ->syncPermissions(Permissions::forRole($role));
        }

        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId($previousTeamId);   // don't clobber the caller's team
    }
}
