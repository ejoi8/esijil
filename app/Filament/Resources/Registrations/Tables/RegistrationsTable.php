<?php

namespace App\Filament\Resources\Registrations\Tables;

use App\Enums\AttendanceStatus;
use App\Enums\CertificateType;
use App\Enums\RegistrationSource;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RegistrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.title')
                    ->label('Event')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('participant.full_name')
                    ->searchable(),
                TextColumn::make('participant.nokp')
                    ->label('No. KP')
                    ->searchable(),
                TextColumn::make('registered_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('attendance_status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => AttendanceStatus::labelFor($state))
                    ->searchable(),
                TextColumn::make('checked_in_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('completed_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => RegistrationSource::labelFor($state))
                    ->searchable(),
                TextColumn::make('certificate_type')
                    ->label('Certificate Type')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? CertificateType::labelFor($state) : '-')
                    ->searchable(),
                TextColumn::make('cert_serial_number')
                    ->label('Certificate Serial')
                    ->searchable(),
                TextColumn::make('certificate_issued_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
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
            ->defaultSort('registered_at', 'desc')
            ->filters([
                SelectFilter::make('source')
                    ->options(RegistrationSource::options()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('download_certificate')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->visible(fn (Registration $record): bool => $record->certificate_type !== null)
                    ->url(fn (Registration $record): string => RegistrationResource::certificateDownloadUrl($record))
                    ->openUrlInNewTab(),
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
