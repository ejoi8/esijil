<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        // The seeded admin operates the platform (super-admin panel).
        $admin->forceFill(['is_platform_admin' => true])->save();

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'puspanita'],
            ['name' => 'PUSPANITA Kebangsaan', 'locale' => 'ms', 'status' => 'active'],
        );

        $admin->organizations()->syncWithoutDetaching([$organization->id]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            CertificateTemplateSeeder::class,
        ]);

        // Make the seeded admin an administrator of PUSPANITA (team-scoped).
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $admin->assignRole(UserRole::Admin->value);
    }
}
