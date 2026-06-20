<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to access the filament auth panel', function () {
    $this->actingAs(User::factory()->create())
        ->followingRedirects()   // tenancy redirects /auth -> the tenant dashboard
        ->get('/auth')
        ->assertSuccessful();
});

it('allows staff to access the filament auth panel', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->followingRedirects()
        ->get('/auth')
        ->assertSuccessful();
});

it('lets an authenticated member reach the panel (per-organization roles gate features inside)', function () {
    // Panel access is gated by authentication + tenancy now; a member with no
    // role reaches the panel but their per-organization role governs what they
    // can actually do (resource policies still deny).
    $this->actingAs(User::factory()->roleless()->create())
        ->followingRedirects()
        ->get('/auth')
        ->assertSuccessful();
});
