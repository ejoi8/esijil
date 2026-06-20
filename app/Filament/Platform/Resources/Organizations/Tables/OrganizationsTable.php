<?php

namespace App\Filament\Platform\Resources\Organizations\Tables;

use App\Models\Organization;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'active' ? 'success' : 'danger'),
                TextColumn::make('events_count')
                    ->counts('events')
                    ->label('Events'),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Members'),
                TextColumn::make('created_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                // Jump into this organization's own tenant dashboard.
                Action::make('open_dashboard')
                    ->label('Open dashboard')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->color('gray')
                    ->url(fn (Organization $record): ?string => Filament::getPanel('auth')->getUrl($record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
