<?php

use App\Enums\ScanMatchMode;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScannerStation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function scanStation(array $eventAttributes = []): ScannerStation
{
    $event = Event::factory()->create(array_merge(['modules' => ['attendance']], $eventAttributes));

    return ScannerStation::factory()->for($event)->create();
}

it('checks in a registered participant scanned by token', function () {
    $station = scanStation();
    $participant = Participant::factory()->create();
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);

    $this->postJson(route('api.scan'), [
        'station_token' => $station->token,
        'code' => $participant->public_token,
    ])
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'present', 'name' => $participant->full_name]);

    expect($participant->registrations()->first()->checked_in_at)->not->toBeNull();
});

it('is idempotent — a second scan reports already checked-in', function () {
    $station = scanStation();
    $participant = Participant::factory()->create();
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);
    $payload = ['station_token' => $station->token, 'code' => $participant->public_token];

    $this->postJson(route('api.scan'), $payload)->assertJson(['status' => 'present']);
    $this->postJson(route('api.scan'), $payload)->assertJson(['status' => 'already']);
});

it('rejects an unknown code as invalid', function () {
    $station = scanStation();

    $this->postJson(route('api.scan'), ['station_token' => $station->token, 'code' => 'NOPE'])
        ->assertOk()
        ->assertJson(['ok' => false, 'status' => 'invalid']);
});

it('returns 401 for an invalid station token', function () {
    $this->postJson(route('api.scan'), ['station_token' => 'not-a-real-token', 'code' => 'x'])
        ->assertStatus(401)
        ->assertJson(['ok' => false]);
});

it('matches by external id when the event uses external-id mode', function () {
    $station = scanStation(['scan_match_mode' => ScanMatchMode::ExternalId->value]);
    $participant = Participant::factory()->create(['external_id' => '900101015555']);
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);

    $this->postJson(route('api.scan'), ['station_token' => $station->token, 'code' => '900101015555'])
        ->assertJson(['ok' => true, 'status' => 'present']);
});

it('rejects a participant who is not registered for the event', function () {
    $station = scanStation();
    $participant = Participant::factory()->create();

    $this->postJson(route('api.scan'), ['station_token' => $station->token, 'code' => $participant->public_token])
        ->assertJson(['ok' => false, 'status' => 'invalid']);
});
