<?php

use App\Enums\UserRole;
use App\Filament\Pages\Tenancy\RegisterOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('lets a user create a new organization and join it as admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(RegisterOrganization::class)
        ->fillForm(['name' => 'Acme Events', 'slug' => 'acme-events'])
        ->call('register')
        ->assertHasNoFormErrors();

    $organization = Organization::firstWhere('slug', 'acme-events');

    expect($organization)->not->toBeNull()
        ->and($organization->name)->toBe('Acme Events')
        ->and($organization->users()->whereKey($user->id)->exists())->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($organization->getKey());
    expect($user->fresh()->hasRole(UserRole::Admin->value))->toBeTrue();
});

it('rejects a duplicate organization slug', function () {
    Organization::factory()->create(['slug' => 'taken']);
    $this->actingAs(User::factory()->create());

    Livewire::test(RegisterOrganization::class)
        ->fillForm(['name' => 'Another', 'slug' => 'taken'])
        ->call('register')
        ->assertHasFormErrors(['slug']);
});
