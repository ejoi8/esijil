<?php

use App\Models\Event;
use App\Models\ScannerStation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the scanner page for an active station', function () {
    $event = Event::factory()->create(['title' => 'Seminar Integriti Kebangsaan']);
    $station = ScannerStation::factory()->for($event)->create(['label' => 'Door A']);

    $this->get(route('scan.show', $station->token))
        ->assertOk()
        ->assertSee('Seminar Integriti Kebangsaan')
        ->assertSee('Door A');
});

it('404s for an inactive station', function () {
    $station = ScannerStation::factory()->create(['active' => false]);

    $this->get(route('scan.show', $station->token))->assertNotFound();
});

it('404s for an expired station', function () {
    $station = ScannerStation::factory()->create(['expires_at' => now()->subDay()]);

    $this->get(route('scan.show', $station->token))->assertNotFound();
});

it('404s for an unknown station token', function () {
    $this->get(route('scan.show', 'not-a-real-token'))->assertNotFound();
});
