<?php

use App\Filament\Pages\ManageApplicationSettings;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('lets an admin open the users resource', function () {
    $this->actingAs(User::factory()->create())
        ->get(UserResource::getUrl('index'))
        ->assertSuccessful();
});

it('forbids staff from the users resource', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(UserResource::getUrl('index'))
        ->assertForbidden();
});

it('lets staff open an operational resource', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(EventResource::getUrl('index'))
        ->assertSuccessful();
});

it('forbids staff from application settings', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get(ManageApplicationSettings::getUrl())
        ->assertForbidden();
});

it('refuses to delete the last administrator', function () {
    $admin = User::factory()->create();

    $threw = false;

    try {
        $admin->delete();
    } catch (RuntimeException) {
        $threw = true;
    }

    expect($threw)->toBeTrue()
        ->and(User::query()->whereKey($admin->getKey())->exists())->toBeTrue();
});

it('forbids a user without registration access from the admin certificate download', function () {
    $registration = Registration::factory()->create();

    $this->actingAs(User::factory()->roleless()->create())
        ->get(route('auth.registrations.certificate', $registration))
        ->assertForbidden();
});

it('lets an admin download a registration certificate', function () {
    $registration = Registration::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('auth.registrations.certificate', $registration))
        ->assertSuccessful();
});
