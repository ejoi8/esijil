<?php

namespace App\Filament\Resources\Participants\Tables;

use App\Enums\MembershipStatus;
use App\Fields\ParticipantFields;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ParticipantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('nokp')
                    ->label('No. KP')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('membership_status')
                    ->badge()
                    ->searchable(),
                ...self::detailColumns(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('full_name')
            ->filters([
                SelectFilter::make('membership_status')
                    ->label('Membership Status')
                    ->options(MembershipStatus::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Toggleable columns for the flexible fields in config/participant_fields.php.
     *
     * @return array<int, TextColumn>
     */
    protected static function detailColumns(): array
    {
        $columns = [];

        foreach (ParticipantFields::all() as $key => $field) {
            $columns[] = TextColumn::make("details.{$key}")
                ->label($field['label'] ?? str($key)->headline()->toString())
                ->formatStateUsing(fn (mixed $state): string => ParticipantFields::display($key, $state))
                ->toggleable(isToggledHiddenByDefault: true);
        }

        return $columns;
    }
}
