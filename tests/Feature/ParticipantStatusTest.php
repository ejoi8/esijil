<?php

use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the attendance pass with a check-in QR', function () {
    $participant = Participant::factory()->create(['full_name' => 'Siti Aminah']);

    $this->get(route('participant.status', $participant->public_token))
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertSee('data:image/svg+xml', false);
});

it('404s for an unknown participant token', function () {
    $this->get(route('participant.status', 'not-a-real-token'))->assertNotFound();
});

it('shows the check-in QR on the success page for attendance events', function () {
    $event = Event::factory()->create(['modules' => ['registration', 'attendance']]);
    $participant = Participant::factory()->create();
    $registration = Registration::factory()->for($event)->for($participant)->create();

    $this->withSession(['event_registration_success_id' => $registration->id])
        ->get(route('events.register.success', $registration))
        ->assertOk()
        ->assertSee('Kod QR Kehadiran');
});

it('omits the check-in QR on the success page for non-attendance events', function () {
    $event = Event::factory()->create(['modules' => ['registration', 'certificate']]);
    $participant = Participant::factory()->create();
    $registration = Registration::factory()->for($event)->for($participant)->create();

    $this->withSession(['event_registration_success_id' => $registration->id])
        ->get(route('events.register.success', $registration))
        ->assertOk()
        ->assertDontSee('Kod QR Kehadiran');
});
