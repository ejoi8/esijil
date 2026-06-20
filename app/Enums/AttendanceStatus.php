<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum AttendanceStatus: string implements HasLabel
{
    use HasOptions;

    case Registered = 'registered';
    case Attended = 'attended';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Attended => 'Attended',
            self::NoShow => 'No-show',
        };
    }
}
