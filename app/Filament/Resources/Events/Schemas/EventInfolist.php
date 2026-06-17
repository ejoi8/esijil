<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\CertificateType;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Support\QrCode;
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
                Section::make('Overview')
                    ->description('Core event details shown to admins and used across registration and certificate flows.')
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
                            })
                            ->formatStateUsing(fn (mixed $state): string => EventStatus::labelFor($state)),
                        TextEntry::make('registrations_count')
                            ->counts('registrations')
                            ->label('Registrations')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('organizer_name')
                            ->label('Organizer')
                            ->placeholder('-'),
                        TextEntry::make('venue')
                            ->placeholder('-')
                            ->columnSpan(2),
                        TextEntry::make('description')
                            ->placeholder('No event description provided.')
                            ->columnSpanFull(),
                    ])
                    ->columns(4)
                    ->columnSpan(8),
                Section::make('Certificate Setup')
                    ->description('Default certificate behavior for registrations under this event.')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->schema([
                        TextEntry::make('certificate_type')
                            ->label('Certificate Type')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn (mixed $state): string => CertificateType::labelFor($state)),
                        TextEntry::make('certificateTemplate.name')
                            ->label('Certificate Template')
                            ->placeholder('No template selected.'),
                        TextEntry::make('template_key')
                            ->label('Template Key')
                            ->copyable()
                            ->placeholder('-'),
                    ])
                    ->columns(1)
                    ->columnSpan(4),
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
                    ->columnSpan(6),
                Section::make('Registration Access')
                    ->description('Public registration availability and the signed URL used for participant access.')
                    ->icon(Heroicon::OutlinedLink)
                    ->schema([
                        TextEntry::make('registration_opens_at')
                            ->label('Registration Opens')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Always open until manually closed.'),
                        TextEntry::make('registration_closes_at')
                            ->label('Registration Closes')
                            ->dateTime('d M Y H:i')
                            ->placeholder('No closing time configured.'),
                        TextEntry::make('registration_link_expires_at')
                            ->label('Signed Link Expires')
                            ->state(fn (Event $record): ?string => EventStatus::fromMixed($record->status) === EventStatus::Published
                                ? $record->registrationLinkExpiresAt()->format('d M Y H:i')
                                : null)
                            ->placeholder('Publish the event to activate the signed registration link.'),
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
                    ->columnSpan(6),
                Section::make('Audit Trail')
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
