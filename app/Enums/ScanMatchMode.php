<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

/**
 * How a scanned code is matched at check-in: the platform-issued public_token,
 * or an imported external_id (e.g. NOKP / staff card the platform never issued).
 */
enum ScanMatchMode: string implements HasLabel
{
    use HasOptions;

    case Token = 'token';
    case ExternalId = 'external_id';

    public function label(): string
    {
        return match ($this) {
            self::Token => 'Platform QR token',
            self::ExternalId => 'External ID (e.g. NOKP / staff card)',
        };
    }
}
