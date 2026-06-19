<?php

namespace App\Filament\Pages;

use App\Models\Registration;
use App\Notifications\RegistrationSubmitted;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\MailSettings;
use App\Settings\NotificationSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * Per-tenant notification preferences. Platform infrastructure (mail server,
 * certificate renderer) moved to the platform panel's PlatformSettings, since a
 * tenant admin must not change platform-wide config.
 */
class ManageApplicationSettings extends SettingsPage
{
    protected static ?string $title = 'Notification Settings';

    protected static ?string $navigationLabel = 'Notifications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string $settings = NotificationSettings::class;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('settings.manage') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registration Notifications')
                    ->description('Control automatic emails sent from the public registration flow.')
                    ->schema([
                        Toggle::make('registration_submitted_enabled')
                            ->label('Send registration confirmation')
                            ->helperText('When enabled, participants receive a confirmation email immediately after a new registration is submitted.')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columnSpanFull(),
                Section::make('Certificate Notifications')
                    ->description('Control certificate emails sent to participants.')
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
