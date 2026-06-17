<?php

namespace App\Filament\Resources\Participants\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Enums\CertificateType;
use App\Enums\RegistrationSource;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RegistrationsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'registrations';

    protected static ?string $relatedResource = RegistrationResource::class;

    protected static ?string $title = 'Registrations';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['event', 'certificateTemplate']))
            ->columns([
                TextColumn::make('event.title')
                    ->label('Event')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('registered_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('attendance_status')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => AttendanceStatus::labelFor($state)),
                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => RegistrationSource::labelFor($state)),
                TextColumn::make('certificate_type')
                    ->label('Certificate Type')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => filled($state) ? CertificateType::labelFor($state) : '-'),
                TextColumn::make('cert_serial_number')
                    ->label('Certificate Serial')
                    ->placeholder('-')
                    ->searchable(),
            ])
            ->defaultSort('registered_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Registration $record): string => RegistrationResource::getUrl('view', ['record' => $record])),
                Action::make('download_certificate')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->visible(fn (Registration $record): bool => $record->certificate_type !== null)
                    ->url(fn (Registration $record): string => RegistrationResource::certificateDownloadUrl($record))
                    ->openUrlInNewTab(),
                EditAction::make()
                    ->url(fn (Registration $record): string => RegistrationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
