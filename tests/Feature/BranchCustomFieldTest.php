<?php

use App\Enums\CustomFieldEntity;
use App\Enums\EventStatus;
use App\Filament\Resources\Participants\Pages\ListParticipants;
use App\Models\CustomField;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Participant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function openBranchEvent(): Event
{
    return Event::factory()->create([
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);
}

it('creates a branch participant dropdown field from the migration', function () {
    $branch = CustomField::query()
        ->where('entity', 'participant')
        ->whereNull('event_id')
        ->where('key', 'branch')
        ->first();

    expect($branch)->not->toBeNull()
        ->and($branch->type->value)->toBe('select')
        ->and($branch->options)->toHaveKey('Selangor');
});

it('shows the branch dropdown on the public registration form', function () {
    $event = openBranchEvent();

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful()
        ->assertSee('Cawangan');
});

it('stores the branch value into participant details on public registration', function () {
    $event = openBranchEvent();

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'participant_details' => ['branch' => 'Selangor', 'membership_status' => 'member'],
    ])->assertRedirect();

    expect(Participant::query()->firstWhere('nokp', '900101015555')->details)
        ->toMatchArray(['branch' => 'Selangor']);
});

it('rejects a branch value outside the dropdown options', function () {
    $event = openBranchEvent();

    $this->post($event->publicRegistrationUrl(), [
        'full_name' => 'Siti Puspanita',
        'email' => 'siti@example.test',
        'nokp' => '900101015555',
        'participant_details' => ['branch' => 'Atlantis', 'membership_status' => 'member'],
    ])->assertSessionHasErrors('participant_details.branch');
});

it('does not leak another organization\'s participant fields onto the public registration form', function () {
    // Org #1 (PUSPANITA, created by the org backfill migration) owns the seeded
    // "Cawangan" participant field. A second org defines its own public field —
    // the public form, scoped to the event's organization, must not show it.
    $puspanita = Organization::query()->where('slug', 'puspanita')->firstOrFail();

    $otherOrg = Organization::factory()->create();
    CustomField::factory()
        ->forEntity(CustomFieldEntity::Participant)
        ->publicForm()
        ->create([
            'organization_id' => $otherOrg->id,
            'key' => 'other_org_field',
            'label' => 'OtherOrgSecretField',
        ]);

    $event = Event::factory()->create([
        'organization_id' => $puspanita->id,
        'status' => EventStatus::Published,
        'registration_open' => true,
    ]);

    // The public registration route runs with no Filament tenant (unlike the admin
    // panel). Clear the tenant the test harness sets so this faithfully reproduces
    // production — otherwise the leak is masked by falling back to the test tenant.
    Filament::setTenant(null);

    $this->get($event->publicRegistrationUrl())
        ->assertSuccessful()
        ->assertSee('Cawangan')                 // the event org's own field renders
        ->assertDontSee('OtherOrgSecretField'); // a different org's field must not leak
});

it('filters participants by branch using the JSON select filter', function () {
    $this->actingAs(User::factory()->create());

    $selangor = Participant::factory()->create(['details' => ['branch' => 'Selangor']]);
    $johor = Participant::factory()->create(['details' => ['branch' => 'Johor']]);

    Livewire::test(ListParticipants::class)
        ->filterTable('detail_branch', 'Selangor')
        ->assertCanSeeTableRecords([$selangor])
        ->assertCanNotSeeTableRecords([$johor]);
});
