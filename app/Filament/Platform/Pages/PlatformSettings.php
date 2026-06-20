<?php

namespace App\Filament\Platform\Pages;

use App\Enums\CertificatePdfRenderer;
use App\Mail\TestApplicationSettingsMail;
use App\Services\Mail\MailSettingsConfigurator;
use App\Settings\CertificateSettings;
use App\Settings\MailSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
use Throwable;

/**
 * Platform-wide infrastructure settings (mail server + certificate render
 * engine). Lives in the platform panel — these are global to the whole platform
 * and must NOT be editable by individual tenant admins.
 */
class PlatformSettings extends SettingsPage
{
    protected static ?string $title = 'Platform Settings';

    protected static ?string $navigationLabel = 'Platform Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 10;

    protected static string $settings = MailSettings::class;

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_platform_admin;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Platform Settings')
                    ->contained()
                    ->columnSpanFull()
                    ->persistTabInQueryString('platform-settings-tab')
                    ->tabs([
                        Tab::make('Email')
                            ->icon(Heroicon::OutlinedEnvelope)
                            ->schema([
                                Section::make('Delivery')
                                    ->description('The mail driver and SMTP server used for all outgoing email across the platform.')
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
                                    ->columnSpanFull(),
                                Section::make('Sender')
                                    ->description('The global From address platform emails are sent with.')
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
                                    ->columnSpanFull(),
                                Section::make('Test Email')
                                    ->description('Send a simple email using the current delivery and sender settings.')
                                    ->schema([
                                        Actions::make([
                                            $this->getSendTestEmailAction(),
                                        ])
                                            ->key('test_email_actions'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columns(12),
                        Tab::make('Certificates')
                            ->icon(Heroicon::OutlinedDocumentCheck)
                            ->schema([
                                Section::make('Rendering')
                                    ->description('How downloaded certificates are rendered across the whole platform.')
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
                            ]),
                    ]),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...app(CertificateSettings::class)->toArray(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $certificateSettings = app(CertificateSettings::class);
        $certificateSettings->fill(Arr::only($data, ['renderer']));
        $certificateSettings->save();

        return Arr::except($data, ['renderer']);
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
}
