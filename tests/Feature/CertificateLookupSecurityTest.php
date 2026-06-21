<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('forbids downloading another participant certificate within a lookup session', function () {
    $alice = Participant::factory()->create(['email' => 'alice@example.com']);
    $bob = Participant::factory()->create(['email' => 'bob@example.com']);
    $event = Event::factory()->create();

    $bobRegistration = Registration::factory()->for($bob)->for($event)->create();

    $this->withSession(['certificate_lookup_participant_id' => $alice->id])
        ->get(route('certificate-lookup.download', $bobRegistration))
        ->assertForbidden();
});

it('returns 404 when downloading a registration without an issued certificate', function () {
    $participant = Participant::factory()->create(['email' => 'participant@example.com']);
    $event = Event::factory()->create(['certificate_template_id' => null]);
    $registration = Registration::factory()->for($participant)->for($event)->create();

    $this->withSession(['certificate_lookup_participant_id' => $participant->id])
        ->get(route('certificate-lookup.download', $registration))
        ->assertNotFound();
});

it('throttles repeated certificate lookups for the same email', function () {
    RateLimiter::clear('127.0.0.1|'.sha1(mb_strtolower('participant@example.com')));

    foreach (range(1, 5) as $attempt) {
        $this->post(route('certificate-lookup.search'), ['email' => 'participant@example.com']);
    }

    $this->post(route('certificate-lookup.search'), ['email' => 'participant@example.com'])
        ->assertTooManyRequests();
});
