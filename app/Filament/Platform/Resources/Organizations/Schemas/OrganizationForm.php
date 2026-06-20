<?php

namespace App\Filament\Platform\Resources\Organizations\Schemas;

use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->description('Tenant identity and access settings.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set, string $operation) => $operation === 'create'
                                ? $set('slug', Str::slug((string) $state))
                                : null)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->rule('alpha_dash')
                            ->unique(ignoreRecord: true)
                            ->prefix('/auth/')
                            ->helperText('Tenant URL segment. Changing it changes this organization\'s links.'),
                        Select::make('status')
                            ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                            ->default('active')
                            ->required()
                            ->helperText('Operational status of this tenant.'),
                        Select::make('locale')
                            ->label('Default language')
                            ->options(['en' => 'English', 'ms' => 'Bahasa Malaysia'])
                            ->default('en')
                            ->required(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('At a glance')
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->schema([
                        TextEntry::make('events')
                            ->label('Events')
                            ->state(fn (?Organization $record): int => $record?->events()->count() ?? 0),
                        TextEntry::make('members')
                            ->label('Members')
                            ->state(fn (?Organization $record): int => $record?->users()->count() ?? 0),
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->state(fn (?Organization $record): string => $record?->created_at?->format('d M Y') ?? '-'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
                    ->hidden(fn (?Organization $record): bool => $record === null),
            ]);
    }
}
