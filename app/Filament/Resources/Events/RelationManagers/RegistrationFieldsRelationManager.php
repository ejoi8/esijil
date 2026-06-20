<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Per-event registration questions: CustomField rows with entity = registration
 * scoped to this event (event_id). They appear only on this event's admin and
 * public registration forms. Global registration fields are managed separately
 * in Settings → Custom Fields.
 */
class RegistrationFieldsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'registrationFields';

    protected static ?string $title = 'Registration Form Fields';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question')
                    ->schema([
                        Hidden::make('entity')
                            ->default(CustomFieldEntity::Registration->value),
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
                            ->helperText('Lowercase letters, numbers and underscores. Cannot be changed later.')
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                    ->where('entity', CustomFieldEntity::Registration->value)
                                    ->where('event_id', $this->getOwnerRecord()->getKey()),
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
                            ->visible(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->required(fn (Get $get): bool => $get('type') === CustomFieldType::Select->value)
                            ->columnSpanFull(),
                        Select::make('scope')
                            ->options(CustomFieldScope::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CustomFieldScope::fromMixed($state)?->value)
                            ->default(CustomFieldScope::PublicForm->value)
                            ->required()
                            ->helperText('Public-form questions also appear in the admin panel.'),
                        TextInput::make('sort')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('required'),
                        Toggle::make('active')
                            ->default(true),
                        Textarea::make('help_text')
                            ->label('Help text')
                            ->maxLength(500)
                            ->columnSpanFull(),
                        TextInput::make('cert_var')
                            ->label('Certificate variable')
                            ->maxLength(255)
                            ->rule('regex:/^[a-zA-Z0-9_]*$/')
                            ->helperText('Optional. Exposes this answer to certificate templates as {{your_variable}}.')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable(),
                TextColumn::make('key')
                    ->color('gray'),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('scope')
                    ->badge(),
                IconColumn::make('required')
                    ->boolean(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('sort')
                    ->sortable(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->headerActions([
                CreateAction::make()
                    ->label('Add field'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
