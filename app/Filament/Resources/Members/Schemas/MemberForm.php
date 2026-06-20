<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('role')
                            ->options(UserRole::class)
                            ->default(UserRole::Staff->value)
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabledOn('edit')
                            ->helperText(fn (string $operation): ?string => $operation === 'create'
                                ? 'If this email already has an account, they will be added to this organization.'
                                : null),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->visibleOn('create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Optional. For a brand-new account; leave blank to set a random password they can reset.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
