<?php

use App\Enums\CertificatePdfRenderer;
use App\Filament\Platform\Pages\PlatformSettings;
use App\Mail\TestApplicationSettingsMail;
use App\Models\User;
use App\Settings\CertificateSettings;
use App\Settings\MailSettings;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function actingAsPlatformAdminOnPlatformPanel(): void
{
    test()->actingAs(User::factory()->platformAdmin()->create());
    Filament::setCurrentPanel(Filament::getPanel('platform'));
}

it('lets a platform admin save the platform mail settings', function () {
    actingAsPlatformAdminOnPlatformPanel();

    Livewire::test(PlatformSettings::class)
        ->fillForm([
            'mailer' => 'smtp',
            'scheme' => 'tls',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer@example.test',
            'password' => 'secret-password',
            'from_address' => 'noreply@example.test',
            'from_name' => 'EventFlow Mailer',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(MailSettings::class);
    $settings->refresh();

    expect($settings->host)->toBe('smtp.example.test')
        ->and($settings->port)->toBe(587)
        ->and($settings->from_address)->toBe('noreply@example.test');
});

it('lets a platform admin save the certificate renderer', function () {
    actingAsPlatformAdminOnPlatformPanel();

    Livewire::test(PlatformSettings::class)
        ->fillForm(['renderer' => CertificatePdfRenderer::Pdfme->value])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(CertificateSettings::class);
    $settings->refresh();

    expect($settings->renderer)->toBe(CertificatePdfRenderer::Pdfme->value);
});

it('renders the platform settings page with email and certificate sections', function () {
    actingAsPlatformAdminOnPlatformPanel();

    Livewire::test(PlatformSettings::class)
        ->assertSuccessful()
        ->assertSee('Email')
        ->assertSee('Certificate PDF Renderer');
});

it('sends a test email from the platform settings page', function () {
    Mail::fake();
    actingAsPlatformAdminOnPlatformPanel();

    Livewire::test(PlatformSettings::class)
        ->fillForm([
            'mailer' => 'smtp',
            'scheme' => 'tls',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer@example.test',
            'password' => 'secret-password',
            'from_address' => 'noreply@example.test',
            'from_name' => 'EventFlow Mailer',
        ])
        ->callAction(TestAction::make('sendTestEmail')->schemaComponent('test_email_actions', 'form'), [
            'recipient' => 'admin@example.test',
        ])
        ->assertHasNoFormErrors();

    Mail::assertSent(TestApplicationSettingsMail::class, 'admin@example.test');
});

it('forbids a non-platform user from the platform settings page', function () {
    Filament::setCurrentPanel(Filament::getPanel('platform'));
    $url = PlatformSettings::getUrl();

    $this->actingAs(User::factory()->create())
        ->get($url)
        ->assertForbidden();
});
