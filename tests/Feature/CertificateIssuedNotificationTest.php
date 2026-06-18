<?php

use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Settings\NotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('emails the certificate link to the participant via the registrations table action', function () {
    Notification::fake();
    $this->actingAs(User::factory()->create());

    // The factory issues a certificate, so certificate_type is set.
    $registration = Registration::factory()->create();

    Livewire::test(ListRegistrations::class)
        ->callTableAction('email_certificate', $registration);

    Notification::assertSentTo($registration->participant, CertificateIssued::class);
});

it('bulk-emails certificates to the selected participants', function () {
    Notification::fake();
    $this->actingAs(User::factory()->create());

    $a = Registration::factory()->create();
    $b = Registration::factory()->create();

    Livewire::test(ListRegistrations::class)
        ->callTableBulkAction('email_certificates', [$a, $b]);

    Notification::assertSentTimes(CertificateIssued::class, 2);
});

it('hides the email-certificate action when certificate emails are disabled', function () {
    $this->actingAs(User::factory()->create());

    app(NotificationSettings::class)->fill(['certificate_issued_enabled' => false])->save();

    $registration = Registration::factory()->create();

    Livewire::test(ListRegistrations::class)
        ->assertTableActionHidden('email_certificate', $registration);
});

it('points the certificate email at the public lookup page', function () {
    $registration = Registration::factory()->create();

    $mail = (new CertificateIssued($registration))->toMail($registration->participant);

    expect($mail->subject)->toContain($registration->event->title)
        ->and($mail->viewData['lookupUrl'])->toBe(route('certificate-lookup.index'));
});
