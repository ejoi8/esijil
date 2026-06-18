<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    use HasOptions;

    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Staff => 'Staff',
        };
    }
}
