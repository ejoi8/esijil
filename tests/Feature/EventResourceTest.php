<?php

use App\Enums\EventModule;
use App\Enums\EventStatus;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\RelationManagers\IssuedCertificatesRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationFieldsRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Events\RelationManagers\ScannerStationsRelationManager;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores the authenticated creator and selected certificate template when creating events', function () {
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create([
        'key' => 'seminar-template',
    ]);

    $this->actingAs($user);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => 'Seminar Kepimpinan',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'organizer_name' => 'PUSPANITA Kebangsaan',
            'status' => 'draft',
            'certificate_template_id' => $template->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->created_by)->toBe($user->id)
        ->and($event->certificate_template_id)->toBe($template->id);
});

it('requires the event end date to remain chronological', function () {
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => 'Seminar Integriti',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'organizer_name' => 'PUSPANITA Kebangsaan',
            'status' => 'draft',
            'certificate_template_id' => $template->id,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'ends_at',
        ]);
});

it('registers relation managers for registrations and issued certificates', function () {
    expect(EventResource::getRelations())->toBe([
        RegistrationsRelationManager::class,
        IssuedCertificatesRelationManager::class,
        RegistrationFieldsRelationManager::class,
        ScannerStationsRelationManager::class,
    ])
        ->and(RegistrationsRelationManager::isLazy())->toBeFalse()
        ->and(IssuedCertificatesRelationManager::isLazy())->toBeFalse()
        ->and(RegistrationFieldsRelationManager::isLazy())->toBeFalse()
        ->and(ScannerStationsRelationManager::isLazy())->toBeFalse();
});

it('renders the event edit page with associated data tabs', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create();
    Registration::factory()->for($event)->create();

    Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Registrations')
        ->assertSee('Issued Certificates');
});

it('shows the download pdf action in the issued certificates relation manager', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create();
    $registration = Registration::factory()->for($event)->create();

    Livewire::test(IssuedCertificatesRelationManager::class, [
        'ownerRecord' => $event,
        'pageClass' => EditEvent::class,
    ])
        ->assertSuccessful()
        ->assertTableActionExists('download_certificate', record: $registration)
        ->assertTableActionVisible('download_certificate', record: $registration);
});

it('shows the signed public registration url on the event edit page for published events', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
    ]);

    Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Signed Registration URL')
        ->assertSee($event->publicRegistrationUrl());
});

it('shows enabled modules and their gated settings on the event view page', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create([
        'status' => EventStatus::Published,
        'modules' => [
            EventModule::Registration->value,
            EventModule::Attendance->value,
            EventModule::Certificate->value,
        ],
    ]);

    Livewire::test(ViewEvent::class, ['record' => $event->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Modules & Certificate')
        ->assertSee('Enabled modules')
        // The modules array renders as human labels, not raw enum values.
        ->assertSee('Attendance')
        // Attendance + Certificate gates open these two settings.
        ->assertSee('Scan matches by')
        ->assertSee('Release certificate')
        ->assertSee('Signed Registration URL');
});

it('hides attendance-only settings on the view page when the module is off', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create([
        'modules' => [EventModule::Registration->value, EventModule::Certificate->value],
    ]);

    Livewire::test(ViewEvent::class, ['record' => $event->getRouteKey()])
        ->assertSuccessful()
        ->assertSee('Release certificate')
        ->assertDontSee('Scan matches by');
});

it('preserves an assigned certificate template when the certificate module is toggled off', function () {
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create();
    $this->actingAs($user);

    $event = Event::factory()->create([
        'certificate_template_id' => $template->id,
        'modules' => [EventModule::Registration->value, EventModule::Certificate->value],
    ]);

    // Turning the Certificate module off hides the template Select; a hidden
    // field is not dehydrated, so the stored value must survive the save.
    Livewire::test(EditEvent::class, ['record' => $event->getRouteKey()])
        ->fillForm(['modules' => [EventModule::Registration->value]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($event->refresh()->certificate_template_id)->toBe($template->id);
});

it('updates event status from the events table inline select column', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create([
        'status' => EventStatus::Draft,
    ]);

    Livewire::test(ListEvents::class)
        ->call('updateTableColumnState', 'status', (string) $event->getKey(), EventStatus::Published->value);

    expect($event->refresh()->status)->toBe(EventStatus::Published);
});
