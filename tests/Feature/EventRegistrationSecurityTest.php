<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

it('rejects a tampered signed registration url', function () {
    $event = Event::factory()->create(['status' => EventStatus::Published]);

    $this->get($event->publicRegistrationUrl().'tampered')
        ->assertForbidden();
});

it('restores a soft-deleted participant and registration instead of duplicating on re-registration', function () {
    Notification::fake();

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]);
    $participant = Participant::factory()->create(['nokp' => '900101015555']);
    $registration = Registration::factory()->for($participant)->for($event)->create();

    $registration->delete();
    $participant->delete();

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'phone' => '0123456789',
        'membership_status' => 'member',
    ])
        ->assertRedirect()
        ->assertSessionHas('registration_exists');

    expect(Participant::query()->where('nokp', '900101015555')->count())->toBe(1)
        ->and(Registration::query()->where('event_id', $event->id)->where('participant_id', $participant->id)->count())->toBe(1)
        // The trashed rows were restored (not freshly inserted)...
        ->and(Participant::onlyTrashed()->where('nokp', '900101015555')->doesntExist())->toBeTrue()
        ->and(Registration::onlyTrashed()->where('event_id', $event->id)->doesntExist())->toBeTrue()
        // ...and the restored participant was updated from the submission.
        ->and(Participant::query()->where('nokp', '900101015555')->value('full_name'))->toBe('Siti Puspanita');

    Notification::assertNothingSent();
});

it('builds a signed registration link for an event with no end date', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'ends_at' => null,
    ]);

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful();
});

it('returns 404 for the admin certificate download when no certificate was issued', function () {
    $registration = Registration::factory()->create();
    $registration->forceFill(['certificate_type' => null])->save();

    $this->actingAs(User::factory()->create())
        ->get(route('auth.registrations.certificate', $registration))
        ->assertNotFound();
});
