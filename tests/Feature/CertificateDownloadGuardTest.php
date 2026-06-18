<?php

use App\Models\Registration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('returns 404 (not a 500) when the certificate event has been deleted', function () {
    $registration = Registration::factory()->create();
    $registration->event->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('auth.registrations.certificate', $registration))
        ->assertNotFound();
});

it('returns 404 (not a 500) when the certificate participant has been deleted', function () {
    $registration = Registration::factory()->create();
    $registration->participant->delete();

    $this->actingAs(User::factory()->create())
        ->get(route('auth.registrations.certificate', $registration))
        ->assertNotFound();
});
