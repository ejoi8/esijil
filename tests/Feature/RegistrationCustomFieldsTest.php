<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Registrations\Pages\CreateRegistration;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Certificates\PdfmeCertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves a registration custom field via the admin registration form', function () {
    $this->actingAs(User::factory()->create());
    CustomField::factory()->forEntity('registration')->create(['key' => 'session', 'label' => 'Session']);

    $event = Event::factory()->create();
    $participant = Participant::factory()->create();

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'event_id' => $event->id,
            'participant_id' => $participant->id,
            'registered_at' => now()->format('Y-m-d H:i:s'),
            'attendance_status' => 'registered',
            'source' => 'public_form',
            'details' => ['session' => 'Morning'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Registration::query()->firstWhere('event_id', $event->id)->details)
        ->toMatchArray(['session' => 'Morning']);
});

it('stores public registration custom fields on the registration', function () {
    CustomField::factory()->forEntity('registration')->publicForm()->create(['key' => 'dietary', 'label' => 'Dietary']);

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Aiman Rahman',
        'email' => 'aiman@example.test',
        'nokp' => '910202025566',
        'participant_details' => ['membership_status' => 'member'],
        'registration_details' => ['dietary' => 'Vegetarian'],
    ])->assertRedirect();

    $registration = Registration::query()->firstWhere('event_id', $event->id);

    expect($registration->details)->toBe(['dietary' => 'Vegetarian']);
});

it('exposes registration custom fields with a cert_var as certificate variables', function () {
    CustomField::factory()->forEntity('registration')->create([
        'key' => 'seat',
        'label' => 'Seat',
        'cert_var' => 'seat_no',
    ]);

    $participant = Participant::factory()->create();
    $registration = Registration::factory()->for($participant)->create(['details' => ['seat' => 'A12']])
        ->load(['event', 'participant']);

    $method = new ReflectionMethod(PdfmeCertificateRenderer::class, 'buildVariables');
    $variables = $method->invoke(app(PdfmeCertificateRenderer::class), $registration);

    expect($variables['seat_no'])->toBe('A12');
});
