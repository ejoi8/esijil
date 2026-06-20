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
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                // 1. Event Details (span 7) — mirrors the form's lead section.
                Section::make('Event Details')
                    ->description('Core event details, publication status, and where the event takes place.')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->schema([
                        TextEntry::make('title')
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpanFull(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (mixed $state): string => match (EventStatus::fromMixed($state)) {
                                EventStatus::Draft => 'gray',
                                EventStatus::Published => 'success',
                                EventStatus::Completed => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('registrations_count')
                            ->counts('registrations')
                            ->label('Registrations')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('description')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('organizer_name')
                            ->label('Organizer')
                            ->placeholder('-'),
                        TextEntry::make('venue')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpan(7),
                // 2. Schedule (span 5) — same position and width as the form.
                Section::make('Schedule')
                    ->description('When the event happens and which public-facing time labels are shown.')
                    ->icon(Heroicon::OutlinedClock)
                    ->schema([
                        TextEntry::make('starts_at')
                            ->label('Starts At')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('ends_at')
                            ->label('Ends At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('start_time_text')
                            ->label('Start Time Label')
                            ->placeholder('-'),
                        TextEntry::make('end_time_text')
                            ->label('End Time Label')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpan(5),
                // 3. Additional Details (full) — organization-defined custom fields.
                Section::make('Additional Details')
                    ->description('Organization-defined custom fields captured for this event.')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema(CustomFields::infolistEntries(CustomFieldEntity::Event))
                    ->columns(2)
                    ->columnSpanFull()
                    ->hidden(fn (): bool => CustomFields::definitions(CustomFieldEntity::Event)->isEmpty()),
                // 4. Modules & Certificate (span 7) — capabilities (now shown on the view) + their settings.
                Section::make('Modules & Certificate')
                    ->description('Capabilities enabled for this event, plus the certificate template, scan matching, and release timing applied to its registrations.')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema([
                        TextEntry::make('modules')
                            ->label('Enabled modules')
                            ->badge()
                            ->formatStateUsing(fn (mixed $state): string => EventModule::labelFor($state))
                            ->placeholder('No modules enabled')
                            ->columnSpanFull(),
                        TextEntry::make('certificateTemplate.name')
                            ->label('Certificate Template')
                            ->placeholder('No certificate for this event')
                            ->visible(fn (Event $record): bool => in_array(EventModule::Certificate->value, $record->modules ?? [], true)),
                        TextEntry::make('scan_match_mode')
                            ->label('Scan matches by')
                            ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : ScanMatchMode::fromMixed($state)?->label())
                            ->placeholder('-')
                            ->visible(fn (Event $record): bool => in_array(EventModule::Attendance->value, $record->modules ?? [], true)),
                        TextEntry::make('certificate_release')
                            ->label('Release certificate')
                            ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : CertificateRelease::fromMixed($state)?->label())
                            ->placeholder('-')
                            ->visible(fn (Event $record): bool => in_array(EventModule::Certificate->value, $record->modules ?? [], true)),
                    ])
                    ->columns(2)
                    ->columnSpan(7),
                // 5. Publishing & Access (span 5) — availability, signed URL, QR.
                Section::make('Publishing & Access')
                    ->description('Public registration availability and the signed URL and QR used for participant access.')
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        IconEntry::make('registration_open')
                            ->label('Registration Open')
                            ->boolean(),
                        TextEntry::make('public_registration_url')
                            ->label('Signed Registration URL')
                            ->state(fn (Event $record): ?string => EventStatus::fromMixed($record->status) === EventStatus::Published
                                ? $record->publicRegistrationUrl()
                                : null)
                            ->placeholder('Publish the event to generate a shareable registration URL.')
                            ->copyable()
                            ->url(fn (Event $record): ?string => EventStatus::fromMixed($record->status) === EventStatus::Published
                                ? $record->publicRegistrationUrl()
                                : null, shouldOpenInNewTab: true)
                            ->columnSpanFull(),
                        Image::make(
                            fn (Event $record): string => static::registrationQrCodeUrl($record),
                            'Signed registration QR code',
                        )
                            ->imageSize(220)
                            ->visible(fn (Event $record): bool => EventStatus::fromMixed($record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(5),
                // 6. Record (full) — ownership and lifecycle timestamps.
                Section::make('Record')
                    ->description('Record ownership and lifecycle timestamps.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        TextEntry::make('creator.name')
                            ->label('Created By')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('deleted_at')
                            ->label('Deleted At')
                            ->dateTime('d M Y H:i')
                            ->visible(fn (Event $record): bool => $record->trashed()),
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
