<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a platform admin reach the platform panel', function () {
    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get('/platform')
        ->assertSuccessful();
});

it('forbids a non-platform user (even an org admin) from the platform panel', function () {
    $this->actingAs(User::factory()->create())
        ->get('/platform')
        ->assertForbidden();
});

it('lets a platform admin see organizations across all tenants', function () {
    Organization::factory()->create(['name' => 'Acme Events Org']);

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get('/platform/organizations')
        ->assertOk()
        ->assertSee('Acme Events Org');
});

it('forbids a non-platform user from the organizations resource', function () {
    $this->actingAs(User::factory()->create())
        ->get('/platform/organizations')
        ->assertForbidden();
});

it('renders the organization edit form with the at-a-glance panel', function () {
    $organization = Organization::factory()->create(['name' => 'Editable Org']);

    $this->actingAs(User::factory()->platformAdmin()->create())
        ->get("/platform/organizations/{$organization->slug}/edit")
        ->assertOk()
        ->assertSee('Editable Org')
        ->assertSee('At a glance');
});
