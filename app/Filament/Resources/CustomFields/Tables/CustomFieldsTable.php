<?php

namespace App\Filament\Resources\CustomFields\Tables;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomFieldsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entity')
                    ->badge()
                    ->sortable(),
                TextColumn::make('label')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->color('gray')
                    ->searchable(),
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
            ->filters([
                SelectFilter::make('entity')
                    ->options(CustomFieldEntity::options()),
                SelectFilter::make('type')
                    ->options(CustomFieldType::options()),
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
