<?php

namespace App\Filament\Resources\Registrations\Pages;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Services\Certificates\RegistrationCertificateIssuer;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditRegistration extends EditRecord
{
    protected static string $resource = RegistrationResource::class;

    protected function afterSave(): void
    {
        // Only re-issue when a field that affects the certificate changed, so
        // editing unrelated fields (e.g. remarks) does not silently overwrite
        // a per-registration certificate type/template with the event's.
        if ($this->record->wasChanged(['event_id', 'attendance_status', 'completed_at', 'certificate_template_id'])) {
            app(RegistrationCertificateIssuer::class)->issueFor($this->record);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            Action::make('download_certificate')
                ->label('Download PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary')
                ->visible(fn (): bool => $this->record->certificate_template_id !== null)
                ->url(fn (): string => RegistrationResource::certificateDownloadUrl($this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
