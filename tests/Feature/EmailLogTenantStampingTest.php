<?php

use App\Models\Registration;
use App\Notifications\CertificateIssued;
use App\Notifications\Concerns\StampsEmailLogTenant;
use App\Notifications\RegistrationSubmitted;
use Ejoi8\FilamentEmailLogs\Support\EmailLogHeaders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

uses(RefreshDatabase::class);

/**
 * Apply a MailMessage's queued Symfony callbacks to a fresh Email and return
 * the X-Email-Log-Tenant-ID header the filament-email-logs listener reads —
 * the same callbacks the mail channel runs when the message is actually sent.
 */
function emailLogTenantHeaderFor(MailMessage $mail): ?string
{
    $message = new Email;

    foreach ($mail->callbacks as $callback) {
        $callback($message);
    }

    $value = $message->getHeaders()->getHeaderBody(EmailLogHeaders::TENANT_ID);

    return $value !== null ? (string) $value : null;
}

it('stamps the owning organization on the registration confirmation email', function () {
    // RegistrationSubmitted is also dispatched from the public (non-panel)
    // registration controller, where no Filament tenant is bound — so it must
    // carry its own tenant header rather than relying on the active tenant.
    $registration = Registration::factory()->create();

    $mail = (new RegistrationSubmitted($registration))->toMail($registration->participant);

    expect(emailLogTenantHeaderFor($mail))->toBe((string) $registration->organization_id);
});

it('stamps the owning organization on the certificate email', function () {
    $registration = Registration::factory()->create();

    $mail = (new CertificateIssued($registration))->toMail($registration->participant);

    expect(emailLogTenantHeaderFor($mail))->toBe((string) $registration->organization_id);
});

it('omits the tenant header when the organization is unknown', function () {
    $notification = new class
    {
        use StampsEmailLogTenant;

        public function build(): MailMessage
        {
            return $this->stampEmailLogTenant(new MailMessage, null);
        }
    };

    expect(emailLogTenantHeaderFor($notification->build()))->toBeNull();
});
