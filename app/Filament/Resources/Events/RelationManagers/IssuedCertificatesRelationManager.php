<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IssuedCertificatesRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'issuedCertificates';

    protected static ?string $relatedResource = RegistrationResource::class;

    protected static ?string $title = 'Issued Certificates';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['participant', 'certificateTemplate']))
            ->columns([
                TextColumn::make('participant.full_name')
                    ->label('Participant')
                    ->searchable(),
                TextColumn::make('participant.nokp')
                    ->label('No. KP')
                    ->searchable(),
                TextColumn::make('certificate_type')
                    ->label('Certificate Type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('certificateTemplate.name')
                    ->label('Certificate Template')
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('cert_serial_number')
                    ->searchable(),
                TextColumn::make('certificate_issued_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('certificate_issued_at', 'desc')
            ->recordActions([
                Action::make('view')
                    ->url(fn (Registration $record): string => RegistrationResource::getUrl('view', ['record' => $record])),
                Action::make('download_certificate')
                    ->label('Download PDF')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('primary')
                    ->url(fn (Registration $record): string => RegistrationResource::certificateDownloadUrl($record))
                    ->openUrlInNewTab(),
                Action::make('edit')
                    ->url(fn (Registration $record): string => RegistrationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
