<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

/**
 * The composable capabilities an event can enable, stored in events.modules.
 * Any subset is valid — registration-only, attendance-only, certificate-only,
 * or any combination.
 */
enum EventModule: string implements HasLabel
{
    use HasOptions;

    case Registration = 'registration';
    case Attendance = 'attendance';
    case Certificate = 'certificate';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Registration',
            self::Attendance => 'Attendance',
            self::Certificate => 'Certificate',
        };
    }
}
