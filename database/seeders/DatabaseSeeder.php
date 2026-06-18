<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@admin.com'],
            ['name' => 'Admin', 'password' => 'password'],
        );

        $this->call([
            RolesAndPermissionsSeeder::class,
            CertificateTemplateSeeder::class,
        ]);
    }
}
