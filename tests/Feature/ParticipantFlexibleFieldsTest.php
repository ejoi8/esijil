<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Participants\Pages\CreateParticipant;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Certificates\PdfmeCertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['participant_fields' => [
        'jawatan' => [
            'label' => 'Jawatan',
            'type' => 'text',
            'rules' => ['nullable', 'string', 'max:255'],
            'scope' => 'public',
            'sort' => 10,
            'active' => true,
            'cert_var' => 'participant_jawatan',
        ],
        'shirt_size' => [
            'label' => 'Saiz Baju',
            'type' => 'select',
            'options' => ['S' => 'S', 'M' => 'M', 'L' => 'L'],
            'rules' => ['nullable'],
            'scope' => 'public',
            'sort' => 20,
            'active' => true,
        ],
        'internal_note' => [
            'label' => 'Nota Dalaman',
            'type' => 'textarea',
            'rules' => ['nullable', 'string'],
            'scope' => 'admin',
            'sort' => 30,
            'active' => true,
        ],
    ]]);
});

function openEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]);
}

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'phone' => '0123456789',
        'membership_status' => 'member',
    ], $overrides);
}

it('renders public flexible fields on the registration form but not admin-scoped ones', function () {
    $event = openEvent();

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful()
        ->assertSee('Jawatan')
        ->assertSee('Saiz Baju')
        ->assertDontSee('Nota Dalaman');
});

it('validates a flexible select field against its options', function () {
    $event = openEvent();

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'details' => ['shirt_size' => 'XXL'],
    ]))->assertSessionHasErrors('details.shirt_size');
});

it('stores flexible field values in participant details on public registration', function () {
    $event = openEvent();

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'details' => ['jawatan' => 'Setiausaha', 'shirt_size' => 'M'],
    ]))->assertRedirect();

    expect(Participant::query()->firstWhere('nokp', '900101015555')->details)
        ->toBe(['jawatan' => 'Setiausaha', 'shirt_size' => 'M']);
});

it('keeps existing details when re-registering with new public values', function () {
    $event = openEvent();
    $participant = Participant::factory()->create([
        'nokp' => '900101015555',
        'details' => ['internal_note' => 'kept'],
    ]);

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'details' => ['jawatan' => 'Bendahari'],
    ]))->assertRedirect();

    expect($participant->refresh()->details)
        ->toBe(['internal_note' => 'kept', 'jawatan' => 'Bendahari']);
});

it('exposes flexible fields with a cert_var as certificate variables', function () {
    $participant = Participant::factory()->create(['details' => ['jawatan' => 'Setiausaha']]);
    $registration = Registration::factory()->for($participant)->create()->load(['event', 'participant']);

    $method = (new ReflectionMethod(PdfmeCertificateRenderer::class, 'buildVariables'));
    $variables = $method->invoke(app(PdfmeCertificateRenderer::class), $registration);

    expect($variables['participant_jawatan'])->toBe('Setiausaha')
        ->and($variables['participant_name'])->toBe($participant->full_name);
});

it('saves a flexible field via the admin participant form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateParticipant::class)
        ->fillForm([
            'full_name' => 'Nor Aisyah',
            'email' => 'aisyah@example.test',
            'nokp' => '880202025566',
            'membership_status' => 'member',
            'details' => ['jawatan' => 'Pengerusi'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Participant::query()->firstWhere('nokp', '880202025566')->details)
        ->toMatchArray(['jawatan' => 'Pengerusi']);
});
