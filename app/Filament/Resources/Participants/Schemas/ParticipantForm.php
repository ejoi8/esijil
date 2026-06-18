<?php

namespace App\Filament\Resources\Participants\Schemas;

use App\Enums\CustomFieldEntity;
use App\Fields\CustomFields;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ParticipantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Participant Details')
                    ->schema([
                        TextInput::make('full_name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('nokp')
                            ->label('No. KP')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        ...CustomFields::formComponents(CustomFieldEntity::Participant),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }
}
