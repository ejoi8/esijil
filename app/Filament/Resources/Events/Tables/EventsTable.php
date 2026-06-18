<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\CertificateType;
use App\Enums\CustomFieldEntity;
use App\Enums\EventStatus;
use App\Fields\CustomFields;
use App\Models\CertificateTemplate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->sortable()
                    ->wrap()
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime('d M Y H:i')
                    ->wrap()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime('d M Y H:i')
                    ->wrap()
                    ->sortable(),
                TextColumn::make('venue')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('organizer_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                SelectColumn::make('status')
                    ->options(EventStatus::options())
                    ->searchable(),
                IconColumn::make('registration_open')
                    ->label('Reg. Open')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('certificate_type')
                    ->label('Certificate Type')
                    ->badge()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations')
                    ->sortable(),
                ...CustomFields::tableColumns(CustomFieldEntity::Event),
                TextColumn::make('certificateTemplate.name')
                    ->label('Certificate Template')
                    ->searchable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('certificate_type')
                    ->label('Certificate Type')
                    ->options(CertificateType::options()),
                SelectFilter::make('certificate_template_id')
                    ->label('Certificate Template')
                    ->relationship('certificateTemplate', 'name')
                    ->getOptionLabelFromRecordUsing(fn (CertificateTemplate $record): string => $record->name),
                ...CustomFields::tableFilters(CustomFieldEntity::Event),
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
}
