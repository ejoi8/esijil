<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum RegistrationSource: string implements HasLabel
{
    use HasOptions;

    case PublicForm = 'public_form';
    case Admin = 'admin';
    case Import = 'import';
    case LegacyImport = 'legacy_import';

    public function label(): string
    {
        return match ($this) {
            self::PublicForm => 'Public Form',
            self::Admin => 'Admin',
            self::Import => 'CSV Import',
            self::LegacyImport => 'Legacy Import',
        };
    }
}
