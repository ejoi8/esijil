<?php

namespace App\Filament\Platform\Resources\Organizations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
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
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rule('alpha_dash')
                            ->unique(ignoreRecord: true)
                            ->helperText('Tenant path segment, e.g. /auth/your-slug.'),
                        Select::make('locale')
                            ->options(['en' => 'English', 'ms' => 'Bahasa Malaysia'])
                            ->default('en')
                            ->required(),
                        Select::make('status')
                            ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
