<?php

use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Enums\EventStatus;
use App\Fields\CustomFields;
use App\Filament\Resources\Participants\Pages\CreateParticipant;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function checkboxEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
}

function consentField(bool $required = true): void
{
    CustomField::create([
        'entity' => 'participant',
        'key' => 'consent',
        'label' => 'Saya bersetuju dengan terma',
        'type' => CustomFieldType::Checkbox->value,
        'scope' => CustomFieldScope::PublicForm->value,
        'required' => $required,
        'sort' => 50,
        'active' => true,
    ]);
}

it('rejects a public registration when a required checkbox is unchecked', function () {
    consentField();
    $event = checkboxEvent();

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Aiman',
        'email' => 'aiman@example.test',
        'participant_details' => ['membership_status' => 'member'],
    ])->assertSessionHasErrors('participant_details.consent');
});

it('stores a checked checkbox value in participant details', function () {
    consentField();
    $event = checkboxEvent();

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Aiman',
        'email' => 'aiman@example.test',
        'participant_details' => ['membership_status' => 'member', 'consent' => '1'],
    ])->assertRedirect();

    expect(Participant::query()->firstWhere('email', 'aiman@example.test')->details)
        ->toMatchArray(['consent' => '1']);
});

it('saves a checkbox custom field via the admin participant form', function () {
    $this->actingAs(User::factory()->create());

    CustomField::create([
        'entity' => 'participant',
        'key' => 'newsletter',
        'label' => 'Langgan berita',
        'type' => CustomFieldType::Checkbox->value,
        'scope' => CustomFieldScope::Admin->value,
        'required' => false,
        'sort' => 50,
        'active' => true,
    ]);

    Livewire::test(CreateParticipant::class)
        ->fillForm([
            'full_name' => 'Nor',
            'email' => 'nor@example.test',
            'details' => ['membership_status' => 'member', 'newsletter' => true],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Participant::query()->firstWhere('email', 'nor@example.test')->details)
        ->toMatchArray(['newsletter' => true]);
});

it('displays a checkbox value as Ya / Tidak', function () {
    consentField();
    $field = CustomField::query()->firstWhere('key', 'consent');

    expect(CustomFields::display($field, '1'))->toBe('Ya')
        ->and(CustomFields::display($field, true))->toBe('Ya')
        ->and(CustomFields::display($field, null))->toBe('Tidak')
        ->and(CustomFields::display($field, '0'))->toBe('Tidak');
});
