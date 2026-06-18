<?php

namespace App\Filament\Resources\CertificateTemplates\Schemas;

use App\Models\CertificateTemplate;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificateTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Used internally to identify this template.'),
                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Designer Workflow')
                    ->schema([
                        TextEntry::make('designer_workflow')
                            ->hiddenLabel()
                            ->state(fn (?CertificateTemplate $record): string => $record === null
                                ? 'Save this template first. After that, open the designer to add text, images, and define the full certificate layout.'
                                : 'Use the designer page for layout editing, fonts, and images. This settings page only keeps the template metadata.'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
