<?php

namespace App\Filament\Resources\CertificateTemplates\Tables;

use App\Filament\Resources\CertificateTemplates\CertificateTemplateResource;
use App\Models\CertificateTemplate;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CertificateTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('events_count')
                    ->counts('events')
                    ->label('Events')
                    ->sortable(),
                TextColumn::make('issued_certificates_count')
                    ->counts('issuedCertificates')
                    ->label('Issued Certificates')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TrashedFilter::make(),
            ])
            // This resource uses the full-page Designer as its record screen
            // (instead of a View/Edit page), so the row actions below
            // intentionally diverge from the View→Edit convention.
            ->recordUrl(fn ($record): string => CertificateTemplateResource::getUrl('designer', ['record' => $record]))
            ->recordActions([
                ReplicateAction::make('duplicate')
                    ->label('Duplicate')
                    ->icon(Heroicon::OutlinedSquare2Stack)
                    ->color('gray')
                    ->modalDescription('Create a new template from this design with a fresh internal key.')
                    ->excludeAttributes([
                        'events_count',
                        'issued_certificates_count',
                    ])
                    ->mutateRecordDataUsing(function (array $data, CertificateTemplate $record): array {
                        $data['name'] = $record->duplicateName();
                        $data['key'] = $record->duplicateKey();

                        return $data;
                    }),
                Action::make('designer')
                    ->label('Designer')
                    ->icon(Heroicon::OutlinedPaintBrush)
                    ->color('primary')
                    ->url(fn ($record): string => CertificateTemplateResource::getUrl('designer', ['record' => $record])),
                Action::make('settings')
                    ->label('Settings')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn ($record): string => CertificateTemplateResource::getUrl('edit', ['record' => $record])),
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
