<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

/**
 * The input types a custom field can take. Each drives the admin form component,
 * the public form control, and the derived validation rules (see App\Fields\CustomFields).
 */
enum CustomFieldType: string implements HasLabel
{
    use HasOptions;

    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Email = 'email';
    case Checkbox = 'checkbox';
    case File = 'file';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Text',
            self::Textarea => 'Paragraph',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Dropdown',
            self::Email => 'Email',
            self::Checkbox => 'Checkbox',
            self::File => 'File upload',
        };
    }

    /** Whether this type stores a list of choices in `options`. */
    public function hasOptions(): bool
    {
        return $this === self::Select;
    }
}
