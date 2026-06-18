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

it('forbids users without a panel role from accessing the filament auth panel', function () {
    $this->actingAs(User::factory()->roleless()->create())
        ->get('/auth')
        ->assertForbidden();
});
