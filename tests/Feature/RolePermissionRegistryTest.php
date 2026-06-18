<?php

use App\Authorization\Permissions;
use App\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function sortedNames(iterable $names): array
{
    return collect($names)->sort()->values()->all();
}

it('seeds exactly the registry permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(sortedNames(Permission::pluck('name')))->toBe(sortedNames(Permissions::all()));
});

it('grants each role exactly its registry permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(sortedNames(Role::findByName(UserRole::Admin->value, 'web')->permissions->pluck('name')))
        ->toBe(sortedNames(Permissions::forRole(UserRole::Admin)))
        ->and(sortedNames(Role::findByName(UserRole::Staff->value, 'web')->permissions->pluck('name')))
        ->toBe(sortedNames(Permissions::forRole(UserRole::Staff)));
});

it('limits staff to operational permissions', function () {
    $staff = Permissions::forRole(UserRole::Staff);

    expect($staff)->toContain('participant.view')
        ->and($staff)->not->toContain('user.view')
        ->and($staff)->not->toContain('customField.view')
        ->and($staff)->not->toContain('participant.forceDelete')
        ->and($staff)->not->toContain(Permissions::SETTINGS_MANAGE)
        ->and($staff)->not->toContain(Permissions::EMAIL_LOG_VIEW);
});

it('has a policy for every registry resource', function () {
    foreach (Permissions::RESOURCES as $resource) {
        expect(class_exists('App\\Policies\\'.ucfirst($resource).'Policy'))->toBeTrue();
    }
});
