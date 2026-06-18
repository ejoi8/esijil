<?php

namespace App\Filament\Pages;

use App\Enums\CertificatePdfRenderer;
use App\Mail\TestApplicationSettingsMail;
use App\Models\Registration;
use App\Notifications\RegistrationSubmitted;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\CertificateSettings;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class ManageApplicationSettings extends SettingsPage
{
    protected static ?string $title = 'Application Settings';

    protected static ?string $navigationLabel = 'Application Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static string $settings = MailSettings::class;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->can('settings.manage') ?? false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Application Settings')
                    ->contained()
                    ->columnSpanFull()
                    ->persistTabInQueryString('settings-tab')
                    ->tabs([
                        Tab::make('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema([
                                Section::make('Delivery')
                                    ->description('Configure the default mail driver and SMTP server used for outgoing email.')
                                    ->schema([
                                        Select::make('mailer')
                                            ->label('Default Mailer')
                                            ->options([
                                                'smtp' => 'SMTP',
                                                'log' => 'Log',
                                                'array' => 'Array',
                                            ])
                                            ->default('smtp')
                                            ->required()
                                            ->live()
                                            ->native(false),
                                        Select::make('scheme')
                                            ->options([
                                                'tls' => 'TLS',
                                                'smtps' => 'SMTPS',
                                            ])
                                            ->placeholder('Automatic / None')
                                            ->native(false)
                                            ->visible(fn (Get $get): bool => $get('mailer') === 'smtp'),
                                        TextInput::make('host')
                                            ->required()
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('mailer') === 'smtp'),
                                        TextInput::make('port')
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(65535)
                                            ->numeric()
                                            ->required()
                                            ->visible(fn (Get $get): bool => $get('mailer') === 'smtp'),
                                        TextInput::make('username')
                                            ->maxLength(255)
                                            ->visible(fn (Get $get): bool => $get('mailer') === 'smtp'),
                                        TextInput::make('password')
                                            ->autocomplete('new-password')
                                            ->maxLength(255)
                                            ->password()
                                            ->revealable()
                                            ->visible(fn (Get $get): bool => $get('mailer') === 'smtp'),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'h-full']),
                                Section::make('Sender')
                                    ->description('Set the global From address that application emails should use.')
                                    ->schema([
                                        TextInput::make('from_address')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('from_name')
                                            ->required()
                                            ->maxLength(255),
                                        TextEntry::make('mail_runtime_notice')
                                            ->columnSpanFull()
                                            ->state('Restart queue workers after changing SMTP settings so long-running processes pick up the new mail configuration.'),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'h-full']),
                                Section::make('Test Email')
                                    ->description('Send a simple email using the current delivery and sender settings.')
                                    ->schema([
                                        TextEntry::make('test_email_notice')
                                            ->hiddenLabel()
                                            ->state('Save and verify the SMTP configuration before using it for participant notifications.'),
                                        Actions::make([
                                            $this->getSendTestEmailAction(),
                                        ])
                                            ->key('test_email_actions'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(12),
                        Tab::make('General')
                            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                            ->schema([
                                Section::make('Certificates')
                                    ->description('Choose how downloaded certificates are rendered across the application.')
                                    ->schema([
                                        Select::make('renderer')
                                            ->label('Certificate PDF Renderer')
                                            ->options(CertificatePdfRenderer::options())
                                            ->default(CertificatePdfRenderer::Dompdf->value)
                                            ->helperText('pdfme matches the designer output but requires Node.js on the server. Dompdf is more portable but can differ from the designer preview.')
                                            ->native(false)
                                            ->required(),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Coming Soon')
                                    ->description('Use this page as the single home for app-wide configuration.')
                                    ->schema([
                                        TextEntry::make('general_settings_placeholder')
                                            ->hiddenLabel()
                                            ->state('General application settings can be added here later as new requirements are introduced.'),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Notifications')
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->schema([
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
                                    ->description('Available notification test templates.')
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
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...app(CertificateSettings::class)->toArray(),
            ...app(NotificationSettings::class)->toArray(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $certificateSettings = app(CertificateSettings::class);
        $certificateSettings->fill(Arr::only($data, [
            'renderer',
        ]));
        $certificateSettings->save();

        $notificationSettings = app(NotificationSettings::class);
        $notificationSettings->fill(Arr::only($data, [
            'registration_submitted_enabled',
            'certificate_issued_enabled',
        ]));
        $notificationSettings->save();

        return Arr::except($data, [
            'renderer',
            'registration_submitted_enabled',
            'certificate_issued_enabled',
        ]);
    }

    public function getSendTestEmailAction(): Action
    {
        return Action::make('sendTestEmail')
            ->label('Send test email')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->schema([
                TextInput::make('recipient')
                    ->label('Recipient email')
                    ->email()
                    ->required()
                    ->default(fn (): ?string => auth()->user()?->email),
            ])
            ->modalHeading('Send test email')
            ->modalSubmitActionLabel('Send')
            ->modalWidth(Width::Medium)
            ->action(function (array $data): void {
                $this->save();

                $settings = app(MailSettings::class);
                $settings->refresh();

                app(MailSettingsConfigurator::class)->apply($settings, forgetResolvedMailers: true);

                try {
                    Mail::to($data['recipient'])->send(new TestApplicationSettingsMail);
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('Test email could not be sent.')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Test email sent.')
                    ->success()
                    ->send();
            });
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
