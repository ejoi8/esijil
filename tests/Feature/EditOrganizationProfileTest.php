<?php

use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use App\Models\Organization;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationSubmitted;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the organization profile with notification preferences', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(EditOrganizationProfile::class)
        ->assertSuccessful()
        ->assertSee('Send registration confirmation')
        ->assertSee('Allow certificate emails');
});

it('saves notification preferences onto the organization', function () {
    $this->actingAs(User::factory()->create());
    $organization = Filament::getTenant();

    Livewire::test(EditOrganizationProfile::class)
        ->fillForm([
            'registration_submitted_enabled' => false,
            'certificate_issued_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $organization->refresh();

    expect($organization->notifies('registration_submitted_enabled'))->toBeFalse()
        ->and($organization->notifies('certificate_issued_enabled'))->toBeFalse();
});

it('keeps notification preferences isolated per organization', function () {
    $other = Organization::factory()->create();
    $other->update(['settings' => ['notifications' => ['registration_submitted_enabled' => false]]]);

    $current = Filament::getTenant();   // default org, no preference set

    expect($current->notifies('registration_submitted_enabled'))->toBeTrue()
        ->and($other->notifies('registration_submitted_enabled'))->toBeFalse();
});

it('only lets users with settings.manage view the organization profile', function () {
    $tenant = Filament::getTenant();

    $this->actingAs(User::factory()->create());
    expect(EditOrganizationProfile::canView($tenant))->toBeTrue();

    $this->actingAs(User::factory()->staff()->create());
    expect(EditOrganizationProfile::canView($tenant))->toBeFalse();
});

it('sends a registration notification test from the organization profile', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $registration = Registration::factory()->create();

    Livewire::test(EditOrganizationProfile::class)
        ->callAction(TestAction::make('sendTestNotification')->schemaComponent('test_notification_actions', 'form'), [
            'notification' => 'registration_submitted',
            'recipient' => 'admin@example.test',
            'registration_id' => $registration->id,
        ])
        ->assertHasNoFormErrors();

    Notification::assertSentOnDemand(
        RegistrationSubmitted::class,
        fn (RegistrationSubmitted $notification, array $channels, object $notifiable): bool => $notification->registration->is($registration)
            && ($notifiable->routes['mail'] ?? null) === 'admin@example.test',
    );
});
