<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

/**
 * When a certificate becomes downloadable — decoupled from attendance so
 * certificate-only events work too.
 */
enum CertificateRelease: string implements HasLabel
{
    use HasOptions;

    case Immediate = 'immediate';
    case OnImport = 'on_import';
    case AfterCheckin = 'after_checkin';
    case AfterEvent = 'after_event';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediately',
            self::OnImport => 'On import',
            self::AfterCheckin => 'After check-in',
            self::AfterEvent => 'After the event ends',
            self::Manual => 'Manually released',
        };
    }
}
