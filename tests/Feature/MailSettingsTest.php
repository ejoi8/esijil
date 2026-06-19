<?php

use App\Filament\Pages\ManageApplicationSettings;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\RegistrationSubmitted;
use App\Providers\AppServiceProvider;
use App\Settings\MailSettings;
use App\Settings\NotificationSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('saves notification toggles from the tenant settings page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageApplicationSettings::class)
        ->fillForm([
            'registration_submitted_enabled' => false,
            'certificate_issued_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(NotificationSettings::class);
    $settings->refresh();

    expect($settings->registration_submitted_enabled)->toBeFalse()
        ->and($settings->certificate_issued_enabled)->toBeFalse();
});

it('renders the tenant notification settings page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageApplicationSettings::class)
        ->assertSuccessful()
        ->assertSee('Send registration confirmation')
        ->assertSee('Allow certificate emails');
});

it('sends a registration notification test from the settings page', function () {
    Notification::fake();

    $this->actingAs(User::factory()->create());

    $registration = Registration::factory()->create();

    Livewire::test(ManageApplicationSettings::class)
        ->callAction(TestAction::make('sendTestNotification')->schemaComponent('test_notification_actions', 'form'), [
            'notification' => 'registration_submitted',
            'recipient' => 'admin@example.test',
            'registration_id' => $registration->id,
        ])
        ->assertHasNoFormErrors();

    Notification::assertSentOnDemand(
        RegistrationSubmitted::class,
        fn (RegistrationSubmitted $notification, array $channels, object $notifiable): bool => $notification->registration->is($registration)
            && $channels === ['mail']
            && ($notifiable->routes['mail'] ?? null) === 'admin@example.test',
    );
});

it('exposes the test notification action on the settings page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ManageApplicationSettings::class)
        ->assertActionExists(TestAction::make('sendTestNotification')->schemaComponent('test_notification_actions', 'form'))
        ->assertActionHasLabel(TestAction::make('sendTestNotification')->schemaComponent('test_notification_actions', 'form'), 'Send test notification')
        ->assertActionHasIcon(TestAction::make('sendTestNotification')->schemaComponent('test_notification_actions', 'form'), Heroicon::OutlinedBellAlert);
});

it('applies stored smtp settings during application boot', function () {
    $settings = app(MailSettings::class);
    $settings->mailer = 'smtp';
    $settings->scheme = 'tls';
    $settings->host = 'smtp.example.test';
    $settings->port = 587;
    $settings->username = 'mailer@example.test';
    $settings->password = 'secret-password';
    $settings->from_address = 'noreply@example.test';
    $settings->from_name = 'eSIJIL Mailer';
    $settings->save();

    config([
        'mail.default' => 'array',
        'mail.mailers.smtp.url' => 'smtp://ignored.example.test',
        'mail.mailers.smtp.scheme' => null,
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 2525,
        'mail.mailers.smtp.username' => null,
        'mail.mailers.smtp.password' => null,
        'mail.from.address' => 'hello@example.com',
        'mail.from.name' => 'Laravel',
    ]);

    (new AppServiceProvider(app()))->boot();

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.url'))->toBeNull()
        ->and(config('mail.mailers.smtp.scheme'))->toBe('tls')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('mailer@example.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('secret-password')
        ->and(config('mail.from.address'))->toBe('noreply@example.test')
        ->and(config('mail.from.name'))->toBe('eSIJIL Mailer');
});
