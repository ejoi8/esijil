<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum EventStatus: string implements HasLabel
{
    use HasOptions;

    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Completed => 'Completed',
        };
    }
}
