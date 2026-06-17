<?php

namespace App\Filament\Resources\Participants\Schemas;

use App\Enums\MembershipStatus;
use App\Fields\ParticipantFields;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                        Select::make('branch_id')
                            ->label('Branch')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('membership_status')
                            ->options(MembershipStatus::options())
                            ->required(),
                        Textarea::make('membership_notes')
                            ->columnSpanFull(),
                        ...self::detailComponents(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    /**
     * Form components for the flexible fields in config/participant_fields.php.
     *
     * @return array<int, Field>
     */
    protected static function detailComponents(): array
    {
        $components = [];

        foreach (ParticipantFields::all() as $key => $field) {
            $name = "details.{$key}";
            $label = $field['label'] ?? str($key)->headline()->toString();

            $component = match ($field['type'] ?? 'text') {
                'textarea' => Textarea::make($name)->columnSpanFull(),
                'select' => Select::make($name)->options($field['options'] ?? []),
                default => TextInput::make($name),
            };

            $components[] = $component->label($label)->required($field['required'] ?? false);
        }

        return $components;
    }
}
