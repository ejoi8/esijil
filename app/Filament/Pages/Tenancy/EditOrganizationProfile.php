<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Registration;
use App\Notifications\RegistrationSubmitted;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\MailSettings;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Per-organization settings, edited by the organization's own admins. Currently
 * the notification preferences, stored under the tenant's settings.notifications.
 */
class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Organization';
    }

    public static function canView(Model $tenant): bool
    {
        return Filament::auth()->user()?->can('settings.manage') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registration Notifications')
                    ->description('Control automatic emails sent from this organization\'s public registration flow.')
                    ->schema([
                        Toggle::make('registration_submitted_enabled')
                            ->label('Send registration confirmation')
                            ->helperText('When enabled, participants receive a confirmation email immediately after a new registration is submitted.')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Certificate Notifications')
                    ->description('Control certificate emails sent to this organization\'s participants.')
                    ->schema([
                        Toggle::make('certificate_issued_enabled')
                            ->label('Allow certificate emails')
                            ->helperText('When enabled, admins can email participants a link to retrieve their certificate (from the Registrations list or an event\'s registrations).')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Notification Tests')
                    ->description('Send a sample notification to verify delivery.')
                    ->schema([
                        TextEntry::make('registration_submitted_notification')
                            ->label('Registration confirmation')
                            ->state('Pengesahan pendaftaran dihantar kepada peserta selepas pendaftaran baharu diterima.'),
                        Actions::make([
                            $this->getSendTestNotificationAction(),
                        ])
                            ->key('test_notification_actions'),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            'registration_submitted_enabled' => (bool) data_get($data, 'settings.notifications.registration_submitted_enabled', true),
            'certificate_issued_enabled' => (bool) data_get($data, 'settings.notifications.certificate_issued_enabled', true),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $settings = $this->tenant->settings ?? [];

        $settings['notifications'] = [
            'registration_submitted_enabled' => (bool) ($data['registration_submitted_enabled'] ?? true),
            'certificate_issued_enabled' => (bool) ($data['certificate_issued_enabled'] ?? true),
        ];

        return ['settings' => $settings];
    }

    public function getSendTestNotificationAction(): Action
    {
        return Action::make('sendTestNotification')
            ->label('Send test notification')
            ->icon(Heroicon::OutlinedBellAlert)
            ->color('gray')
            ->schema([
                Select::make('notification')
                    ->options([
                        'registration_submitted' => 'Registration confirmation',
                    ])
                    ->default('registration_submitted')
                    ->required()
                    ->native(false),
                TextInput::make('recipient')
                    ->label('Recipient email')
                    ->email()
                    ->required()
                    ->default(fn (): ?string => auth()->user()?->email),
                Select::make('registration_id')
                    ->label('Sample registration')
                    ->options(fn (): array => Registration::query()
                        ->with(['event', 'participant'])
                        ->latest('id')
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Registration $registration): array => [
                            $registration->id => sprintf(
                                '#%s - %s - %s',
                                $registration->id,
                                $registration->participant?->full_name ?? 'Participant',
                                $registration->event?->title ?? 'Event',
                            ),
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->native(false),
            ])
            ->modalHeading('Send test notification')
            ->modalSubmitActionLabel('Send')
            ->modalWidth(Width::Large)
            ->action(function (array $data): void {
                $this->save();

                $settings = app(MailSettings::class);
                $settings->refresh();

                app(MailSettingsConfigurator::class)->apply($settings, forgetResolvedMailers: true);

                $registration = Registration::query()
                    ->with(['event', 'participant'])
                    ->findOrFail($data['registration_id']);

                try {
                    match ($data['notification']) {
                        'registration_submitted' => NotificationFacade::route('mail', $data['recipient'])
                            ->notify(new RegistrationSubmitted($registration)),
                    };
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Test notification could not be sent.')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Test notification sent.')
                    ->success()
                    ->send();
            });
    }
}
