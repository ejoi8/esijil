<?php

use App\Enums\EventStatus;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\Events\RelationManagers\IssuedCertificatesRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationFieldsRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('stores the authenticated creator and selected template key when creating events', function () {
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create([
        'type' => 'participation_certificate',
        'key' => 'seminar-template',
    ]);

    $this->actingAs($user);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => 'Seminar Kepimpinan',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'organizer_name' => 'PUSPANITA Kebangsaan',
            'status' => 'draft',
            'certificate_type' => 'participation_certificate',
            'certificate_template_id' => $template->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $event = Event::query()->latest('id')->first();

    expect($event)->not->toBeNull()
        ->and($event->created_by)->toBe($user->id)
        ->and($event->template_key)->toBe('seminar-template');
});

it('requires the event end date to remain chronological', function () {
    $user = User::factory()->create();
    $template = CertificateTemplate::factory()->create([
        'type' => 'participation_certificate',
    ]);

    $this->actingAs($user);

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'title' => 'Seminar Integriti',
            'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(6)->format('Y-m-d H:i:s'),
            'organizer_name' => 'PUSPANITA Kebangsaan',
            'status' => 'draft',
            'certificate_type' => 'participation_certificate',
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
    ])
        ->and(RegistrationsRelationManager::isLazy())->toBeFalse()
        ->and(IssuedCertificatesRelationManager::isLazy())->toBeFalse()
        ->and(RegistrationFieldsRelationManager::isLazy())->toBeFalse();
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

it('updates event status from the events table inline select column', function () {
    $this->actingAs(User::factory()->create());

    $event = Event::factory()->create([
        'status' => EventStatus::Draft,
    ]);

    Livewire::test(ListEvents::class)
        ->call('updateTableColumnState', 'status', (string) $event->getKey(), EventStatus::Published->value);

    expect($event->refresh()->status)->toBe(EventStatus::Published);
});

it('shows certificate template guidance on the event create page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateEvent::class)
        ->assertSuccessful()
        ->assertSee('Only designs matching the type above.');
});
