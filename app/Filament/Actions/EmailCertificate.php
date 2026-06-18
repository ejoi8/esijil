<?php

namespace App\Filament\Actions;

use App\Models\Registration;
use App\Notifications\CertificateIssued;
use App\Settings\NotificationSettings;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Admin-triggered "email the participant a link to retrieve their certificate"
 * actions, shared by the Registrations table and the Event registrations
 * relation manager. Gated by the certificate_issued_enabled setting; only
 * registrations that actually have a certificate are emailed.
 */
class EmailCertificate
{
    public static function recordAction(): Action
    {
        return Action::make('email_certificate')
            ->label('Email certificate')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Registration $record): bool => static::enabled() && $record->certificate_template_id !== null)
            ->requiresConfirmation()
            ->modalHeading('Email certificate')
            ->modalDescription(fn (Registration $record): string => "Email {$record->participant->full_name} a link to retrieve their certificate?")
            ->action(function (Registration $record): void {
                $record->participant->notify(new CertificateIssued($record));

                Notification::make()
                    ->title('Certificate email sent')
                    ->success()
                    ->send();
            });
    }

    public static function bulkAction(): BulkAction
    {
        return BulkAction::make('email_certificates')
            ->label('Email certificate')
            ->icon(Heroicon::OutlinedEnvelope)
            ->visible(fn (): bool => static::enabled())
            ->requiresConfirmation()
            ->modalHeading('Email certificates')
            ->modalDescription('Email each selected participant who has an issued certificate a link to retrieve it.')
            ->action(function (Collection $records): void {
                $sent = $records
                    ->filter(fn (Registration $record): bool => $record->certificate_template_id !== null)
                    ->each(fn (Registration $record) => $record->participant->notify(new CertificateIssued($record)))
                    ->count();

                Notification::make()
                    ->title($sent.' certificate email(s) sent')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function enabled(): bool
    {
        return app(NotificationSettings::class)->certificate_issued_enabled;
    }
}
