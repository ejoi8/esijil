<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Enums\CertificateType;
use App\Enums\CustomFieldEntity;
use App\Enums\EventStatus;
use App\Fields\CustomFields;
use App\Models\CertificateTemplate;
use App\Models\Event;
use App\Support\QrCode;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->components([
                Section::make('Event Details')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->compact()
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Seminar Integriti Kebangsaan')
                            ->autofocus()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->placeholder('Shown on the public registration page.')
                            ->columnSpanFull(),
                        TextInput::make('organizer_name')
                            ->required()
                            ->default('PUSPANITA Kebangsaan')
                            ->maxLength(255),
                        Select::make('created_by')
                            ->relationship('creator', 'name')
                            ->label('Created By')
                            ->default(fn (): ?int => auth()->id())
                            ->searchable()
                            ->preload()
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Schedule')
                    ->icon(Heroicon::OutlinedClock)
                    ->compact()
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->afterOrEqual('starts_at'),
                        TextInput::make('start_time_text')
                            ->label('Display start time')
                            ->maxLength(255)
                            ->placeholder('8:00 AM'),
                        TextInput::make('end_time_text')
                            ->label('Display end time')
                            ->maxLength(255)
                            ->placeholder('5:00 PM'),
                        TextInput::make('venue')
                            ->maxLength(255)
                            ->placeholder('Dewan Serbaguna PUSPANITA')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Additional Details')
                    ->icon(Heroicon::OutlinedRectangleStack)
                    ->compact()
                    ->schema(CustomFields::formComponents(CustomFieldEntity::Event))
                    ->columns(2)
                    ->columnSpanFull()
                    ->hidden(fn (): bool => CustomFields::definitions(CustomFieldEntity::Event)->isEmpty()),
                Section::make('Certificate Setup')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->compact()
                    ->schema([
                        Select::make('certificate_type')
                            ->label('Certificate Type')
                            ->options(CertificateType::options())
                            ->formatStateUsing(fn (mixed $state): ?string => CertificateType::fromMixed($state)?->value)
                            ->default(CertificateType::ParticipationCertificate->value)
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('certificate_template_id', null);
                                $set('template_key', null);
                            })
                            ->required(),
                        Select::make('certificate_template_id')
                            ->label('Certificate Template')
                            ->relationship(
                                'certificateTemplate',
                                'name',
                                modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                    $selectedType = CertificateType::fromMixed($get('certificate_type'));

                                    if ($selectedType === null) {
                                        return $query->whereKey([]);
                                    }

                                    return $query->forDocumentType($selectedType);
                                },
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->disabled(fn (Get $get): bool => blank($get('certificate_type')))
                            ->placeholder('Select a certificate type first')
                            ->helperText('Only designs matching the type above.')
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('template_key', CertificateTemplate::keyFor($state));
                            })
                            ->rule(function (Get $get): Closure {
                                return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    if (blank($value)) {
                                        return;
                                    }

                                    $selectedType = CertificateType::fromMixed($get('certificate_type'));

                                    if ($selectedType === null) {
                                        $fail('Select a certificate type before choosing a template.');

                                        return;
                                    }

                                    $matchesSelectedType = CertificateTemplate::matchesDocumentType($value, $selectedType);

                                    if (! $matchesSelectedType) {
                                        $fail('The selected template must match the selected certificate type.');
                                    }
                                };
                            })
                            ->required(),
                        // Auto-filled from the chosen template (shown read-only on the View
                        // page); kept hidden here so it persists without cluttering the form.
                        Hidden::make('template_key'),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 7])
                    ->extraAttributes(['class' => 'h-full']),
                Section::make('Registration Access')
                    ->icon(Heroicon::OutlinedLink)
                    ->compact()
                    ->schema([
                        Select::make('status')
                            ->options(EventStatus::options())
                            ->formatStateUsing(fn (mixed $state): ?string => EventStatus::fromMixed($state)?->value)
                            ->default(EventStatus::Draft->value)
                            ->live()
                            ->required()
                            ->helperText('Draft: hidden · Published: link active · Completed: ended.')
                            ->columnSpanFull(),
                        Toggle::make('registration_open')
                            ->label('Registration open')
                            ->helperText('Off: public page shows a closed message and rejects sign-ups.')
                            ->columnSpanFull(),
                        TextEntry::make('public_registration_guidance')
                            ->hiddenLabel()
                            ->state(function (?Event $record, Get $get): string {
                                if ($record === null) {
                                    return 'Save the event to generate its registration link.';
                                }

                                if (EventStatus::fromMixed($get('status') ?? $record->status) !== EventStatus::Published) {
                                    return 'Publish the event to activate the registration link.';
                                }

                                return 'Share this link, or use the toggle to open / close sign-ups.';
                            })
                            ->columnSpanFull(),
                        TextEntry::make('public_registration_url')
                            ->label('Signed Registration URL')
                            ->state(fn (?Event $record): string => $record?->publicRegistrationUrl() ?? '-')
                            ->copyable()
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                        Actions::make([
                            Action::make('view_qr')
                                ->label('View QR code')
                                ->icon(Heroicon::OutlinedQrCode)
                                ->color('gray')
                                ->modalHeading('Registration QR code')
                                ->modalDescription('Scan to open the signed registration link.')
                                ->modalContent(fn (Event $record): HtmlString => new HtmlString(
                                    '<div style="display:flex;justify-content:center;padding:1rem"><img src="'.e(static::registrationQrCodeUrl($record)).'" alt="Registration QR code" style="width:240px;height:240px"></div>'
                                ))
                                ->modalSubmitAction(false)
                                ->modalCancelActionLabel('Close'),
                        ])
                            ->visible(fn (?Event $record, Get $get): bool => $record !== null && EventStatus::fromMixed($get('status') ?? $record->status) === EventStatus::Published)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpan(['default' => 'full', 'lg' => 5])
                    ->extraAttributes(['class' => 'h-full']),
            ]);
    }

    protected static function registrationQrCodeUrl(Event $event): string
    {
        // Generated locally so the signed registration URL (which carries an
        // HMAC signature) is never sent to a third-party QR service.
        return QrCode::dataUri($event->publicRegistrationUrl());
    }
}
