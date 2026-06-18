<?php

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Notifications\RegistrationSubmitted;
use App\Services\Certificates\StoredCertificatePdf;
use App\Settings\NotificationSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

it('does not expose a public event registration listing page', function () {
    $this->get('/events')
        ->assertNotFound();
});

it('generates a signed registration url with no expiry', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
    ]);

    $url = $event->publicRegistrationUrl();
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toHaveKey('signature')
        ->and($query)->not->toHaveKey('expires');
});

it('requires a valid signed url to access the public registration form', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
    ]);

    $this->get(route('events.register.show', ['event' => $event->public_id]))
        ->assertForbidden();
});

it('shows the public registration form without the membership notes field', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
    ]);

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful()
        ->assertSee('Hantar Pendaftaran')
        ->assertSee('Sedang Dihantar...')
        ->assertSee('data-registration-form', false)
        ->assertSee('data-submit-button', false)
        ->assertDontSee('name="membership_notes"', false)
        ->assertDontSee('Catatan');
});

it('registers a participant for a published event', function () {
    Notification::fake();

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
    $url = $event->publicRegistrationUrl();

    $this->post($url, [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101-01-5555',
        'phone' => '0123456789',
        'participant_details' => ['membership_status' => 'member'],
    ])
        ->assertRedirect()
        ->assertSessionHas('registration_created');

    $participant = Participant::query()->firstWhere('nokp', '900101015555');

    expect($participant)->not->toBeNull()
        ->and($participant->full_name)->toBe('Siti Puspanita')
        ->and($participant->email)->toBe('siti@example.test')
        ->and($participant->details['membership_status'])->toBe('member');

    $registration = Registration::query()
        ->where('event_id', $event->id)
        ->where('participant_id', $participant->id)
        ->first();

    expect($registration)->not->toBeNull();

    $this->assertDatabaseHas(Registration::class, [
        'event_id' => $event->id,
        'participant_id' => $participant->id,
        'attendance_status' => 'registered',
        'source' => 'public_form',
    ]);

    $this->assertDatabaseHas(Registration::class, [
        'id' => $registration->id,
        'certificate_type' => $event->certificate_type->value,
        'cert_serial_number' => null,
    ]);

    Notification::assertSentTo(
        $participant,
        RegistrationSubmitted::class,
        fn (RegistrationSubmitted $notification): bool => $notification->registration->is($registration),
    );

    $this->get(route('events.register.success', $registration))
        ->assertSuccessful()
        ->assertSee('Pendaftaran berjaya diterima.')
        ->assertSee('Muat Turun Sijil');
});

it('queues the registration confirmation notification after registration', function () {
    Queue::fake();

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101-01-5555',
        'phone' => '0123456789',
        'participant_details' => ['membership_status' => 'member'],
    ])
        ->assertRedirect()
        ->assertSessionHas('registration_created');

    $registration = Registration::query()->firstOrFail();
    $participant = Participant::query()->firstOrFail();

    Queue::assertPushed(
        SendQueuedNotifications::class,
        fn (SendQueuedNotifications $job): bool => $job->notification instanceof RegistrationSubmitted
            && $job->notification instanceof ShouldQueue
            && $job->notification->tries === 3
            && $job->notification->afterCommit === true
            && $job->notification->backoff() === [60, 300, 900]
            && $job->notification->registration->is($registration)
            && $job->notifiables->contains(fn (Participant $notifiable): bool => $notifiable->is($participant)),
    );
});

it('does not create duplicate registrations for the same participant and event', function () {
    Notification::fake();

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
    $participant = Participant::factory()->create([
        'nokp' => '900101015555',
    ]);
    Registration::factory()->for($event)->for($participant)->create();
    $url = $event->publicRegistrationUrl();

    $this->post($url, [
        'full_name' => 'Siti Puspanita',
        'email' => 'updated@example.test',
        'nokp' => '900101015555',
        'phone' => null,
        'participant_details' => ['membership_status' => 'non_member'],
    ])
        ->assertRedirect(route('events.register.success', Registration::query()->first()))
        ->assertSessionHas('registration_exists');

    expect(Registration::query()
        ->where('event_id', $event->id)
        ->where('participant_id', $participant->id)
        ->count())->toBe(1);

    Notification::assertNotSentTo($participant, RegistrationSubmitted::class);
});

it('throttles repeated registration attempts for the same participant', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
    $url = $event->publicRegistrationUrl();
    RateLimiter::clear('127.0.0.1|event:'.$event->id.'|'.sha1('900101015555'));
    $payload = [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'phone' => '0123456789',
        'participant_details' => ['membership_status' => 'member'],
    ];

    foreach (range(1, 5) as $attempt) {
        $this->post($url, $payload)
            ->assertRedirect();
    }

    $this->post($url, $payload)
        ->assertTooManyRequests();
});

it('does not send registration confirmation when disabled in application settings', function () {
    Notification::fake();

    $settings = app(NotificationSettings::class);
    $settings->registration_submitted_enabled = false;
    $settings->save();

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101-01-5555',
        'phone' => '0123456789',
        'participant_details' => ['membership_status' => 'member'],
    ])
        ->assertRedirect()
        ->assertSessionHas('registration_created');

    $participant = Participant::query()->firstWhere('nokp', '900101015555');

    expect($participant)->not->toBeNull();

    Notification::assertNotSentTo($participant, RegistrationSubmitted::class);
});

it('forbids registration success page without the matching session', function () {
    $registration = Registration::factory()->create();

    $this->get(route('events.register.success', $registration))
        ->assertForbidden();
});

it('downloads the certificate from the registration success session', function () {
    $registration = Registration::factory()->create();

    $this->mock(StoredCertificatePdf::class)
        ->shouldReceive('download')
        ->once()
        ->withArgs(fn (Registration $downloadedRegistration): bool => $downloadedRegistration->is($registration))
        ->andReturn(response()->streamDownload(fn () => print 'PDF', 'certificate.pdf', [
            'Content-Type' => 'application/pdf',
        ]));

    $this->withSession([
        'event_registration_success_id' => $registration->id,
    ])->get(route('events.register.certificate', $registration))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('rejects registration when the registration window is closed', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => false,
    ]);
    $url = $event->publicRegistrationUrl();

    $this->post($url, [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'participant_details' => ['membership_status' => 'member'],
    ])
        ->assertSessionHasErrors(['event']);

    $this->assertDatabaseMissing(Participant::class, [
        'nokp' => '900101015555',
    ]);
});

it('keeps the signed registration url valid indefinitely', function () {
    Carbon::setTestNow('2026-05-01 09:00:00');

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'starts_at' => Carbon::parse('2026-05-01 09:00:00'),
        'ends_at' => Carbon::parse('2026-05-01 17:00:00'),
    ]);
    $url = $event->publicRegistrationUrl();

    Carbon::setTestNow('2027-06-01 00:00:00');

    $this->get($url)
        ->assertSuccessful();

    Carbon::setTestNow();
});

it('does not expose draft event registration pages even with a signed url', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Draft,
    ]);

    $this->get($event->publicRegistrationUrl())
        ->assertNotFound();
});
