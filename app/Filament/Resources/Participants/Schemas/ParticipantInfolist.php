<?php

namespace App\Filament\Resources\Participants\Schemas;

use App\Enums\CustomFieldEntity;
use App\Fields\CustomFields;
use App\Models\Participant;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ParticipantInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('full_name'),
                TextEntry::make('email')
                    ->label('Email'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations'),
                ...CustomFields::infolistEntries(CustomFieldEntity::Participant),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Participant $record): bool => $record->trashed()),
            ]);
    }
}
