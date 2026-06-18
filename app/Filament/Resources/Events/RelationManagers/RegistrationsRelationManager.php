<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\AttendanceStatus;
use App\Enums\CustomFieldEntity;
use App\Enums\RegistrationSource;
use App\Fields\CustomFields;
use App\Filament\Actions\EmailCertificate;
use App\Filament\Imports\ParticipantImporter;
use App\Filament\Resources\Registrations\RegistrationResource;
use App\Models\Participant;
use App\Models\Registration;
use App\Services\Certificates\RegistrationCertificateIssuer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\ImportAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class RegistrationsRelationManager extends RelationManager
{
    protected static bool $isLazy = false;

    protected static string $relationship = 'registrations';

    protected static ?string $relatedResource = RegistrationResource::class;

    protected static ?string $title = 'Registrations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Registration Details')
                    ->schema([
                        Select::make('participant_id')
                            ->relationship('participant', 'full_name')
                            ->getOptionLabelFromRecordUsing(fn (Participant $record): string => "{$record->full_name} ({$record->nokp})")
                            ->searchable()
                            ->preload()
                            ->unique(
                                ignoreRecord: true,
                                modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('event_id', $this->getOwnerRecord()->getKey()),
                            )
                            ->required(),
                        DateTimePicker::make('registered_at')
                            ->default(now())
                            ->required(),
                        Select::make('attendance_status')
                            ->options(AttendanceStatus::options())
                            ->default(AttendanceStatus::Registered->value)
                            ->required(),
                        Select::make('source')
                            ->options(RegistrationSource::options())
                            ->default(RegistrationSource::Admin->value)
                            ->required(),
                        DateTimePicker::make('checked_in_at'),
                        DateTimePicker::make('completed_at'),
                        Textarea::make('remarks')
                            ->columnSpanFull(),
                        ...CustomFields::formComponents(CustomFieldEntity::Registration, $this->getOwnerRecord()),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
                ->with(['participant', 'certificateTemplate']))
            ->columns([
                TextColumn::make('participant.full_name')
                    ->label('Participant')
                    ->searchable(),
                TextColumn::make('participant.nokp')
                    ->label('No. KP')
                    ->searchable(),
                TextColumn::make('registered_at')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('attendance_status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('source')
                    ->badge()
                    ->searchable(),
                TextColumn::make('cert_serial_number')
                    ->label('Certificate Serial')
                    ->placeholder('-')
                    ->searchable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Registration')
                    ->after(fn (Registration $record): Registration => app(RegistrationCertificateIssuer::class)->issueFor($record)),
                ImportAction::make()
                    ->importer(ParticipantImporter::class)
                    ->label('Import CSV')
                    ->options(fn (): array => [
                        'event_id' => $this->getOwnerRecord()->getKey(),
                        'organization_id' => $this->getOwnerRecord()->organization_id,
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EmailCertificate::recordAction(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    EmailCertificate::bulkAction(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
