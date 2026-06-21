<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\CertificateRelease;
use App\Enums\CustomFieldEntity;
use App\Enums\EventModule;
use App\Enums\EventStatus;
use App\Enums\ScanMatchMode;
use App\Fields\CustomFields;
use App\Models\Event;
use App\Support\QrCode;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                // 1. Event Details (lg:7) — identity, status, and where it happens.
                Section::make('Event Details')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->compact()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Seminar Integriti Kebangsaan')
                            ->autofocus()
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(EventStatus::options())
                            ->formatStateUsing(fn (mixed $state): ?string => EventStatus::fromMixed($state)?->value)
                            ->default(EventStatus::Draft->value)
                            ->live()
                            ->required()
                            ->helperText('Draft: hidden · Published: link active · Completed: ended.')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Shown on the public registration page.')
                            ->columnSpanFull(),
                        TextInput::make('organizer_name')
                            ->required()
                            ->default('PUSPANITA Kebangsaan')
                            ->maxLength(255),
                        TextInput::make('venue')
                            ->maxLength(255)
                            ->placeholder('Dewan Serbaguna PUSPANITA'),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                // 2. Schedule (lg:5) — a clean time-only grid.
                Section::make('Schedule')
                    ->icon(Heroicon::OutlinedClock)
                    ->compact()
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->afterOrEqual('starts_at'),
                        TextInput::make('start_time_text')
                            ->label('Display start time')
                            ->maxLength(255)
                            ->placeholder('8:00 AM'),
                        TextInput::make('end_time_text')
                            ->label('Display end time')
                            ->maxLength(255)
                            ->placeholder('5:00 PM'),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
                // 3. Additional Details (full) — organization-defined custom fields.
                Section::make('Additional Details')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->compact()
                    ->schema(CustomFields::formComponents(CustomFieldEntity::Event))
                    ->columns(2)
                    ->columnSpanFull()
                    ->hidden(fn (): bool => CustomFields::definitions(CustomFieldEntity::Event)->isEmpty()),
                // 4. Modules & Certificate (lg:7) — capabilities and the settings they gate.
                Section::make('Modules & Certificate')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->compact()
                    ->schema([
                        CheckboxList::make('modules')
                            ->options(EventModule::options())
                            ->default([EventModule::Registration->value, EventModule::Certificate->value])
                            ->live()
                            ->helperText('Which capabilities this event uses — any combination.')
                            ->columnSpanFull(),
                        Select::make('certificate_template_id')
                            ->label('Certificate Template')
                            ->relationship('certificateTemplate', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('No certificate for this event')
                            ->helperText('Assign a template to issue certificates for this event; leave empty to issue none.')
                            ->visible(fn (Get $get): bool => in_array(EventModule::Certificate->value, $get('modules') ?? [], true)),
                        Select::make('scan_match_mode')
                            ->label('Scan matches by')
                            ->options(ScanMatchMode::options())
                            ->formatStateUsing(fn (mixed $state): ?string => ScanMatchMode::fromMixed($state)?->value)
                            ->default(ScanMatchMode::Token->value)
                            ->visible(fn (Get $get): bool => in_array(EventModule::Attendance->value, $get('modules') ?? [], true)),
                        Select::make('certificate_release')
                            ->label('Release certificate')
                            ->options(CertificateRelease::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CertificateRelease::fromMixed($state)?->value)
                            ->default(CertificateRelease::Immediate->value)
                            ->visible(fn (Get $get): bool => in_array(EventModule::Certificate->value, $get('modules') ?? [], true)),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                // 5. Publishing & Access (lg:5) — availability and the signed link / QR.
                Section::make('Publishing & Access')
                    ->icon(Heroicon::OutlinedLink)
                    ->compact()
                    ->schema([
                        Toggle::make('registration_open')
                            ->label('Registration open')
                            ->helperText('Off: public page shows a closed message and rejects sign-ups.')
                            ->columnSpanFull(),
                        TextInput::make('capacity')
                            ->label('Seat capacity')
                            ->numeric()
                            ->minValue(1)
                            ->placeholder('Unlimited')
                            ->helperText('Max public sign-ups; leave blank for unlimited. Admin & import are not capped.')
                            ->columnSpanFull(),
                        Toggle::make('listed')
                            ->label('List publicly (discoverable in search)')
                            ->helperText('On: a public landing page (/e/…) becomes indexable by search engines. Off (default): the event stays unlisted — reachable only via the shared link.')
                            ->columnSpanFull(),
                        TextEntry::make('public_registration_guidance')
                            ->hiddenLabel()
                            ->state(function (?Event $record, Get $get): string {
                                if ($record === null) {
                                    return 'Save the event to generate its registration link.';
                                }

                                if (EventStatus::fromMixed($get('status') ?? $record->status) !== EventStatus::Published) {
                                    return 'Publish the event to activate the registration link.';
                                }

                                return 'Share this link, or use the toggle to open / close sign-ups.';
                            })
                            ->columnSpanFull(),
                        TextEntry::make('public_registration_url')
                            ->label('Signed Registration URL')
                            ->state(fn (?Event $record): string => $record?->publicRegistrationUrl() ?? '-')
                            ->copyable()
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                        Actions::make([
                            Action::make('view_qr')
                                ->label('View QR code')
                                ->icon(Heroicon::OutlinedQrCode)
                                ->color('gray')
                                ->modalHeading('Registration QR code')
                                ->modalDescription('Scan to open the signed registration link.')
                                ->modalContent(fn (Event $record): HtmlString => new HtmlString(
                                    '<div style="display:flex;justify-content:center;padding:1rem"><img src="'.e(static::registrationQrCodeUrl($record)).'" alt="Registration QR code" style="width:240px;height:240px"></div>'
                                ))
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ])
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
                // 6. Record (full) — ownership; created_at/updated_at are read-only on the view.
                Section::make('Record')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->compact()
                    ->schema([
                        Select::make('created_by')
                            ->relationship('creator', 'name')
                            ->label('Created By')
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    protected static function registrationQrCodeUrl(Event $event): string
    {
        // Generated locally so the signed registration URL (which carries an
        // HMAC signature) is never sent to a third-party QR service.
        return QrCode::dataUri($event->publicRegistrationUrl());
    }
}
