<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum CertificatePdfRenderer: string implements HasLabel
{
    use HasOptions;

    case Pdfme = 'pdfme';
    case Dompdf = 'dompdf';

    public function label(): string
    {
        return match ($this) {
            self::Pdfme => 'pdfme (exact designer output)',
            self::Dompdf => 'Dompdf (server-friendly fallback)',
        };
    }
}
