<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('forbids downloading another participant certificate within a lookup session', function () {
    $alice = Participant::factory()->create(['nokp' => '900101015555']);
    $bob = Participant::factory()->create(['nokp' => '880202025566']);
    $event = Event::factory()->create(['certificate_type' => 'participation_certificate']);

    $bobRegistration = Registration::factory()->for($bob)->for($event)->create();

    $this->withSession(['certificate_lookup_participant_id' => $alice->id])
        ->get(route('certificate-lookup.download', $bobRegistration))
        ->assertForbidden();
});

it('returns 404 when downloading a registration without an issued certificate', function () {
    $participant = Participant::factory()->create(['nokp' => '900101015555']);
    $event = Event::factory()->create(['certificate_type' => 'participation_certificate']);
    $registration = Registration::factory()->for($participant)->for($event)->create();
    $registration->forceFill(['certificate_type' => null])->save();

    $this->withSession(['certificate_lookup_participant_id' => $participant->id])
        ->get(route('certificate-lookup.download', $registration))
        ->assertNotFound();
});

it('normalises a dashed nokp on lookup', function () {
    $participant = Participant::factory()->create(['nokp' => '900101015555']);

    $this->post(route('certificate-lookup.search'), ['nokp' => '900101-01-5555'])
        ->assertRedirect(route('certificate-lookup.result'))
        ->assertSessionHas('certificate_lookup_participant_id', $participant->id);
});

it('rejects a nokp that is not twelve digits', function (string $nokp) {
    $this->post(route('certificate-lookup.search'), ['nokp' => $nokp])
        ->assertSessionHasErrors('nokp');
})->with([
    'too short' => '12345',
    'non digits' => 'abcdefghijkl',
    'eleven digits' => '90010101555',
]);

it('throttles repeated certificate lookups for the same nokp', function () {
    RateLimiter::clear('127.0.0.1|'.sha1('900101015555'));

    foreach (range(1, 5) as $attempt) {
        $this->post(route('certificate-lookup.search'), ['nokp' => '900101015555']);
    }

    $this->post(route('certificate-lookup.search'), ['nokp' => '900101015555'])
        ->assertTooManyRequests();
});
