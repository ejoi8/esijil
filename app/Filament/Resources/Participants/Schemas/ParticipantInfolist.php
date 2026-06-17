<?php

namespace App\Filament\Resources\Participants\Schemas;

use App\Fields\ParticipantFields;
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
                TextEntry::make('nokp'),
                TextEntry::make('phone')
                    ->placeholder('-'),
                TextEntry::make('branch.name')
                    ->label('Branch')
                    ->placeholder('-'),
                TextEntry::make('membership_status'),
                TextEntry::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations'),
                TextEntry::make('membership_notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                ...self::detailEntries(),
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

    /**
     * Infolist entries for the flexible fields in config/participant_fields.php.
     *
     * @return array<int, TextEntry>
     */
    protected static function detailEntries(): array
    {
        $entries = [];

        foreach (ParticipantFields::all() as $key => $field) {
            $entries[] = TextEntry::make("details.{$key}")
                ->label($field['label'] ?? str($key)->headline()->toString())
                ->formatStateUsing(fn (mixed $state): string => ParticipantFields::display($key, $state))
                ->placeholder('-');
        }

        return $entries;
    }
}
