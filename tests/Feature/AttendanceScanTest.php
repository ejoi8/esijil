<?php

use App\Enums\ScanMatchMode;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\ScannerStation;
use App\Models\User;
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

it('identifies a participant without checking in when confirm is false', function () {
    $station = scanStation();
    $participant = Participant::factory()->create();
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);

    $this->postJson(route('api.scan'), [
        'station_token' => $station->token,
        'code' => $participant->public_token,
        'confirm' => false,
    ])
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'found', 'name' => $participant->full_name]);

    // Identify must not write anything.
    expect($participant->registrations()->first()->checked_in_at)->toBeNull();
});

it('records the check-in only after confirm is true', function () {
    $station = scanStation();
    $participant = Participant::factory()->create();
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);
    $payload = ['station_token' => $station->token, 'code' => $participant->public_token];

    $this->postJson(route('api.scan'), $payload + ['confirm' => false])->assertJson(['status' => 'found']);
    expect($participant->registrations()->first()->checked_in_at)->toBeNull();

    $this->postJson(route('api.scan'), $payload + ['confirm' => true])->assertJson(['status' => 'present']);
    expect($participant->registrations()->first()->checked_in_at)->not->toBeNull();
});

it('requires the station PIN when one is set', function () {
    $station = scanStation();
    $station->update(['pin' => '4823']);
    $participant = Participant::factory()->create();
    Registration::factory()->for($station->event)->for($participant)->create(['checked_in_at' => null]);
    $base = ['station_token' => $station->token, 'code' => $participant->public_token];

    // Missing PIN -> rejected.
    $this->postJson(route('api.scan'), $base)
        ->assertStatus(403)->assertJson(['ok' => false, 'status' => 'pin_invalid']);
    // Wrong PIN -> rejected.
    $this->postJson(route('api.scan'), $base + ['pin' => '0000'])
        ->assertStatus(403)->assertJson(['status' => 'pin_invalid']);
    expect($participant->registrations()->first()->checked_in_at)->toBeNull();

    // Correct PIN -> checked in.
    $this->postJson(route('api.scan'), $base + ['pin' => '4823'])->assertJson(['status' => 'present']);
    expect($participant->registrations()->first()->checked_in_at)->not->toBeNull();
});

it('verifies the station PIN at the gate before the scanner opens', function () {
    $station = scanStation();
    $station->update(['pin' => '4823']);

    $this->postJson(route('api.scan.verify'), ['station_token' => $station->token, 'pin' => '4823'])
        ->assertOk()->assertJson(['ok' => true]);

    $this->postJson(route('api.scan.verify'), ['station_token' => $station->token, 'pin' => '0000'])
        ->assertStatus(403)->assertJson(['ok' => false]);

    // A station without a PIN is open.
    $open = scanStation();
    $this->postJson(route('api.scan.verify'), ['station_token' => $open->token])
        ->assertOk()->assertJson(['ok' => true]);

    // An unknown station token is rejected before the camera opens.
    $this->postJson(route('api.scan.verify'), ['station_token' => 'not-a-real-token', 'pin' => '4823'])
        ->assertStatus(401)->assertJson(['ok' => false]);
});

it('embeds the PIN for a logged-in org member but not for guests or outsiders', function () {
    // Own organization + event so membership is unambiguous (the harness auto-joins
    // factory users to a default org, which would otherwise muddy "non-member").
    $org = Organization::factory()->create();
    $event = Event::factory()->create(['organization_id' => $org->id, 'modules' => ['attendance']]);
    $station = ScannerStation::factory()->for($event)->create(['pin' => '4823']);

    // Guest: PIN required, PIN not embedded / not in the markup.
    $this->get(route('scan.show', $station->token))
        ->assertOk()
        ->assertViewHas('pinRequired', true)
        ->assertViewHas('embeddedPin', null)
        ->assertDontSee('4823');

    // Member of the event's organization: PIN embedded, no prompt.
    $member = User::factory()->create();
    $member->organizations()->syncWithoutDetaching([$org->id]);
    $this->actingAs($member)->get(route('scan.show', $station->token))
        ->assertOk()
        ->assertViewHas('pinRequired', false)
        ->assertViewHas('embeddedPin', '4823');

    // Logged-in user who is NOT in the event's organization is treated like a guest.
    $outsider = User::factory()->create();
    $this->actingAs($outsider)->get(route('scan.show', $station->token))
        ->assertViewHas('pinRequired', true)
        ->assertViewHas('embeddedPin', null)
        ->assertDontSee('4823');
});
