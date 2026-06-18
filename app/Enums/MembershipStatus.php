<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum MembershipStatus: string implements HasLabel
{
    use HasOptions;

    case Member = 'member';
    case NonMember = 'non_member';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::NonMember => 'Non-member',
        };
    }
}
