<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\ScannerStation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScannerStationsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'scannerStations';

    protected static ?string $title = 'Scanner Stations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Door A'),
                TextInput::make('pin')
                    ->password()
                    ->revealable()
                    ->minLength(4)
                    ->maxLength(20)
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->afterStateHydrated(fn (TextInput $component) => $component->state(null))
                    ->helperText('Set a PIN (min 4 chars) to require it before anyone can check in via the link. Logged-in organization members skip it. Leave blank to keep the current PIN (or for an open link on a new station).'),
                DateTimePicker::make('expires_at')
                    ->helperText('Optional — leave empty for no expiry.'),
                Toggle::make('active')
                    ->default(true)
                    ->inline(false),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->searchable(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('scanner_url')
                    ->label('Scanner link')
                    ->state(fn (ScannerStation $record): string => route('scan.show', $record->token))
                    ->copyable()
                    ->wrap(),
                TextColumn::make('expires_at')
                    ->dateTime('d M Y H:i')
                    ->placeholder('No expiry'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add station'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open scanner')
                    ->icon(Heroicon::OutlinedQrCode)
                    ->color('gray')
                    ->url(fn (ScannerStation $record): string => route('scan.show', $record->token))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
