<?php

namespace App\Filament\Resources\Members\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Members\MemberResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn (User $record): ?string => $record->roles->first()?->name)
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? ucfirst($state) : '—')
                    ->color(fn (?string $state): string => $state === UserRole::Admin->value ? 'success' : 'gray'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                Action::make('remove')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove member')
                    ->modalDescription(fn (User $record): string => "Remove {$record->name} from this organization? Their account is not deleted.")
                    ->visible(fn (User $record): bool => ! MemberResource::isLastAdmin($record))
                    ->action(function (User $record): void {
                        $record->organizations()->detach(Filament::getTenant()?->getKey());
                        $record->syncRoles([]);

                        Notification::make()
                            ->title('Member removed from organization')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
