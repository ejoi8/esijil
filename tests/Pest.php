<?php

use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Multi-tenant test context: the migration seeds PUSPANITA as org #1.
        // Bind it as the active Filament tenant so models auto-fill organization_id
        // and resource pages resolve a tenant.
        if (Schema::hasTable('organizations')) {
            $organization = Organization::query()->first() ?? Organization::factory()->create();
            Filament::setCurrentPanel(Filament::getPanel('auth'));
            Filament::setTenant($organization, isQuiet: true);
            app(PermissionRegistrar::class)->setPermissionsTeamId($organization->getKey());

            // Ensure the global role/permission definitions exist so factory role
            // assignments resolve them (and assign within the current team).
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
