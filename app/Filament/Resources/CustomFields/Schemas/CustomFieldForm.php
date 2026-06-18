<?php

namespace App\Filament\Resources\CustomFields\Schemas;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CustomFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Field')
                    ->schema([
                        Select::make('entity')
                            ->label('Applies to')
                            ->options(CustomFieldEntity::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CustomFieldEntity::fromMixed($state)?->value)
                            ->default(CustomFieldEntity::Participant->value)
                            ->required()
                            ->live()
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated()
                            ->helperText('Which record this field is attached to. Cannot be changed later.'),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create') {
                                    $set('key', Str::slug((string) $state, '_'));
                                }
                            }),
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->rule('regex:/^[a-z][a-z0-9_]*$/')
                            ->helperText('Lowercase letters, numbers and underscores. Stored in the details JSON; cannot be changed later.')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule
                                    ->where('entity', $get('entity'))
                                    ->whereNull('event_id'),
                            )
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(),
                        Select::make('type')
                            ->options(CustomFieldType::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CustomFieldType::fromMixed($state)?->value)
                            ->default(CustomFieldType::Text->value)
                            ->required()
                            ->live(),
                        KeyValue::make('options')
                            ->label('Dropdown choices')
                            ->keyLabel('Value (stored)')
                            ->valueLabel('Label (shown)')
                            ->helperText('Each choice has a stored value and the label shown to users.')
                            ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->required(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->columnSpanFull(),
                        TextInput::make('max_file_kb')
                            ->label('Max file size (KB)')
                            ->numeric()
                            ->minValue(1)
                            ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::File->value)
                            ->helperText('Maximum upload size in kilobytes (e.g. 5120 = 5 MB). Leave empty for no limit.'),
                        TagsInput::make('accepted_file_types')
                            ->label('Allowed file types')
                            ->placeholder('pdf')
                            ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::File->value)
                            ->helperText('File extensions, e.g. pdf, jpg, png. Leave empty to allow any type.'),
                        Select::make('scope')
                            ->options(CustomFieldScope::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CustomFieldScope::fromMixed($state)?->value)
                            ->default(CustomFieldScope::Admin->value)
                            ->required()
                            ->helperText('Where the field is shown. Public-form fields also appear in the admin panel.'),
                        TextInput::make('sort')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Lower numbers appear first.'),
                        Toggle::make('required')
                            ->helperText('Make this field mandatory.'),
                        Toggle::make('active')
                            ->default(true)
                            ->helperText('Inactive fields are hidden everywhere; existing data is kept.'),
                        Textarea::make('help_text')
                            ->label('Help text')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('cert_var')
                            ->label('Certificate variable')
                            ->maxLength(255)
                            ->rule('regex:/^[a-zA-Z0-9_]*$/')
                            ->helperText('Optional. Exposes this value to certificate templates as {{your_variable}}.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
