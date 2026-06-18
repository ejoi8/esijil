<?php

use App\Models\Event;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the QR sheet for an admin in the same organization', function () {
    $event = Event::factory()->create(['title' => 'Seminar Integriti']);
    $participant = Participant::factory()->create(['full_name' => 'Ali bin Abu']);
    Registration::factory()->for($event)->for($participant)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('auth.events.qr-sheet', $event))
        ->assertOk()
        ->assertSee('Seminar Integriti')
        ->assertSee('Ali bin Abu')
        ->assertSee('data:image/svg+xml', false);
});

it('forbids a user without the registration.view permission', function () {
    $event = Event::factory()->create();

    $this->actingAs(User::factory()->roleless()->create())
        ->get(route('auth.events.qr-sheet', $event))
        ->assertForbidden();
});

it('forbids a user from another organization', function () {
    $otherOrg = Organization::factory()->create();
    $event = Event::factory()->create(['organization_id' => $otherOrg->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('auth.events.qr-sheet', $event))
        ->assertForbidden();
});
