<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\CertificateType;
use App\Enums\EventStatus;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Support\QrCode;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Event Details')
                    ->description('Core information shown on the public registration form and certificate records.')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Seminar Integriti Kebangsaan')
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),
                        TextInput::make('organizer_name')
                            ->required()
                            ->default('PUSPANITA Kebangsaan')
                            ->maxLength(255),
                        Select::make('status')
                            ->options(EventStatus::options())
                            ->formatStateUsing(fn (mixed $state): ?string => EventStatus::fromMixed($state)?->value)
                            ->default(EventStatus::Draft->value)
                            ->required(),
                        Select::make('created_by')
                            ->relationship('creator', 'name')
                            ->label('Created By')
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Captured from the current admin session.'),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Schedule')
                    ->description('Set when and where the event takes place.')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->afterOrEqual('starts_at'),
                        TextInput::make('start_time_text')
                            ->maxLength(255)
                            ->helperText('Optional public-facing start time label.'),
                        TextInput::make('end_time_text')
                            ->maxLength(255)
                            ->helperText('Optional public-facing end time label.'),
                        TextInput::make('venue')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Issued Certificate Settings')
                    ->description('Choose the certificate type and template issued for successful registrations.')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema([
                        Select::make('certificate_type')
                            ->label('Certificate Type')
                            ->options(CertificateType::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CertificateType::fromMixed($state)?->value)
                            ->default(CertificateType::ParticipationCertificate->value)
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('certificate_template_id', null);
                                $set('template_key', null);
                            })
                            ->required(),
                        Select::make('certificate_template_id')
                            ->label('Default Certificate Template')
                            ->relationship(
                                'certificateTemplate',
                                'name',
                                modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                    $selectedType = CertificateType::fromMixed($get('certificate_type'));

                                    if ($selectedType === null) {
                                        return $query->whereKey([]);
                                    }

                                    return $query->forDocumentType($selectedType);
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('certificate_type')))
                            ->helperText('Only templates matching the selected certificate type are available.')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('template_key', CertificateTemplate::keyFor($state));
                            })
                            ->rule(function (Get $get): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    $selectedType = CertificateType::fromMixed($get('certificate_type'));

                                    if ($selectedType === null) {
                                        $fail('Select a certificate type before choosing a template.');

                                        return;
                                    }

                                    $matchesSelectedType = CertificateTemplate::matchesDocumentType($value, $selectedType);

                                    if (! $matchesSelectedType) {
                                        $fail('The selected template must match the selected certificate type.');
                                    }
                                };
                            })
                            ->required(),
                        TextInput::make('template_key')
                            ->label('Template Key')
                            ->maxLength(255)
                            ->readOnly()
                            ->helperText('Filled automatically from the selected template.'),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Registration Access')
                    ->description('Control the registration window and share the signed URL only after the event is ready for public registration.')
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        DateTimePicker::make('registration_opens_at'),
                        DateTimePicker::make('registration_closes_at')
                            ->afterOrEqual('registration_opens_at')
                            ->helperText('Must be after the registration opening date and time.'),
                        TextEntry::make('public_registration_guidance')
                            ->hiddenLabel()
                            ->state(function (?Event $record, Get $get): string {
                                if ($record === null) {
                                    return 'Save the event first. After that, this page will show the signed registration URL that you can share directly with participants.';
                                }

                                if (EventStatus::fromMixed($get('status') ?? $record->status) !== EventStatus::Published) {
                                    return 'Publish the event to activate the signed registration URL. The link is not intended to be publicly listed.';
                                }

                                return 'Share this signed URL directly with participants. It expires 24 hours after the event finishes.';
                            }),
                        TextEntry::make('public_registration_url')
                            ->label('Signed Registration URL')
                            ->state(fn (?Event $record): string => $record?->publicRegistrationUrl() ?? '-')
                            ->copyable()
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                        Image::make(
                            fn (?Event $record): string => $record === null ? '' : static::registrationQrCodeUrl($record),
                            'Signed registration QR code',
                        )
                            ->imageSize(220)
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                        TextEntry::make('registration_link_expires_at')
                            ->label('Link Expires At')
                            ->state(fn (?Event $record): string => $record?->registrationLinkExpiresAt()->format('d M Y H:i') ?? '-')
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
            ]);
    }

    protected static function registrationQrCodeUrl(Event $event): string
    {
        // Generated locally so the signed registration URL (which carries an
        // HMAC signature) is never sent to a third-party QR service.
        return QrCode::dataUri($event->publicRegistrationUrl());
    }
}
