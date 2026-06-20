<?php

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Enums\EventStatus;
use App\Filament\Resources\Participants\Pages\CreateParticipant;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use App\Models\User;
use App\Services\Certificates\PdfmeCertificateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    CustomField::create([
        'entity' => 'participant',
        'key' => 'jawatan',
        'label' => 'Jawatan',
        'type' => CustomFieldType::Text->value,
        'scope' => CustomFieldScope::PublicForm->value,
        'sort' => 10,
        'active' => true,
        'cert_var' => 'participant_jawatan',
    ]);

    CustomField::create([
        'entity' => 'participant',
        'key' => 'shirt_size',
        'label' => 'Saiz Baju',
        'type' => CustomFieldType::Select->value,
        'options' => ['S' => 'S', 'M' => 'M', 'L' => 'L'],
        'scope' => CustomFieldScope::PublicForm->value,
        'sort' => 20,
        'active' => true,
    ]);

    CustomField::create([
        'entity' => 'participant',
        'key' => 'internal_note',
        'label' => 'Nota Dalaman',
        'type' => CustomFieldType::Textarea->value,
        'scope' => CustomFieldScope::Admin->value,
        'sort' => 30,
        'active' => true,
    ]);
});

function openEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
}

function registrationPayload(array $overrides = []): array
{
    // membership_status is now a required public participant custom field, so it
    // must always be supplied under participant_details (merged with overrides).
    $participantDetails = array_merge(
        ['membership_status' => 'member'],
        $overrides['participant_details'] ?? [],
    );
    unset($overrides['participant_details']);

    return array_merge([
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'phone' => '0123456789',
    ], $overrides, ['participant_details' => $participantDetails]);
}

it('renders public custom fields on the registration form but not admin-scoped ones', function () {
    $event = openEvent();

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful()
        ->assertSee('Jawatan')
        ->assertSee('Saiz Baju')
        ->assertDontSee('Nota Dalaman');
});

it('validates a custom select field against its options', function () {
    $event = openEvent();

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'participant_details' => ['shirt_size' => 'XXL'],
    ]))->assertSessionHasErrors('participant_details.shirt_size');
});

it('stores custom field values in participant details on public registration', function () {
    $event = openEvent();

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'participant_details' => ['jawatan' => 'Setiausaha', 'shirt_size' => 'M'],
    ]))->assertRedirect();

    expect(Participant::query()->firstWhere('nokp', '900101015555')->details)
        ->toMatchArray(['jawatan' => 'Setiausaha', 'shirt_size' => 'M']);
});

it('keeps existing details when re-registering with new public values', function () {
    $event = openEvent();
    $participant = Participant::factory()->create([
        'nokp' => '900101015555',
        'details' => ['internal_note' => 'kept'],
    ]);

    $this->post($event->publicRegistrationUrl(), registrationPayload([
        'participant_details' => ['jawatan' => 'Bendahari'],
    ]))->assertRedirect();

    expect($participant->refresh()->details)
        ->toMatchArray(['internal_note' => 'kept', 'jawatan' => 'Bendahari']);
});

it('exposes custom fields with a cert_var as certificate variables', function () {
    $participant = Participant::factory()->create(['details' => ['jawatan' => 'Setiausaha']]);
    $registration = Registration::factory()->for($participant)->create()->load(['event', 'participant']);

    $method = (new ReflectionMethod(PdfmeCertificateRenderer::class, 'buildVariables'));
    $variables = $method->invoke(app(PdfmeCertificateRenderer::class), $registration);

    expect($variables['participant_jawatan'])->toBe('Setiausaha')
        ->and($variables['participant_name'])->toBe($participant->full_name);
});

it('saves a custom field via the admin participant form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateParticipant::class)
        ->fillForm([
            'full_name' => 'Nor Aisyah',
            'email' => 'aisyah@example.test',
            'nokp' => '880202025566',
            'details' => ['membership_status' => 'member', 'jawatan' => 'Pengerusi'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Participant::query()->firstWhere('nokp', '880202025566')->details)
        ->toMatchArray(['jawatan' => 'Pengerusi']);
});
