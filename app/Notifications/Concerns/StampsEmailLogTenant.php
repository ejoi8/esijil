<?php

namespace App\Notifications\Concerns;

use Ejoi8\FilamentEmailLogs\Support\EmailLogHeaders;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

trait StampsEmailLogTenant
{
    /**
     * Attach the owning organization to the outgoing mail as an email-log
     * header. The filament-email-logs listener reads this header first, so the
     * message is attributed to the correct tenant even when it is sent from a
     * queue worker or a public (non-panel) request — where the active Filament
     * tenant, and therefore the Context-based resolver, is unavailable.
     */
    protected function stampEmailLogTenant(MailMessage $mail, int|string|null $tenantKey): MailMessage
    {
        if (blank($tenantKey)) {
            return $mail;
        }

        return $mail->withSymfonyMessage(function (Email $message) use ($tenantKey): void {
            $message->getHeaders()->addTextHeader(EmailLogHeaders::TENANT_ID, (string) $tenantKey);
        });
    }
}
