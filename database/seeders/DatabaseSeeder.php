<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'puspanita'],
            ['name' => 'PUSPANITA Kebangsaan', 'locale' => 'ms', 'status' => 'active'],
        );

        $admin->organizations()->syncWithoutDetaching([$organization->id]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            CertificateTemplateSeeder::class,
        ]);
    }
}
