<?php

namespace App\Filament\Resources\Registrations\Schemas;

use App\Enums\CustomFieldEntity;
use App\Fields\CustomFields;
use App\Models\Registration;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RegistrationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('legacy_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('event.title')
                    ->label('Event'),
                TextEntry::make('participant.full_name')
                    ->label('Participant'),
                TextEntry::make('participant.nokp')
                    ->label('No. KP'),
                TextEntry::make('registered_at')
                    ->dateTime(),
                TextEntry::make('attendance_status'),
                TextEntry::make('checked_in_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('completed_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('source'),
                TextEntry::make('certificate_type')
                    ->label('Certificate Type')
                    ->placeholder('-'),
                TextEntry::make('certificateTemplate.name')
                    ->label('Certificate Template')
                    ->placeholder('-'),
                TextEntry::make('cert_serial_number')
                    ->label('Certificate Serial')
                    ->placeholder('-'),
                TextEntry::make('certificate_issued_at')
                    ->label('Certificate Issued At')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('remarks')
                    ->placeholder('-')
                    ->columnSpanFull(),
                ...CustomFields::infolistEntries(CustomFieldEntity::Registration),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Registration $record): bool => $record->trashed()),
            ]);
    }
}
