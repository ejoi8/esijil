<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

enum CertificateType: string implements HasLabel
{
    use HasOptions;

    case AttendanceSlip = 'attendance_slip';
    case ParticipationCertificate = 'participation_certificate';

    public function label(): string
    {
        return match ($this) {
            self::AttendanceSlip => 'Attendance Slip',
            self::ParticipationCertificate => 'Participation Certificate',
        };
    }

    public function templateKey(): string
    {
        return match ($this) {
            self::AttendanceSlip => 'default-attendance',
            self::ParticipationCertificate => 'default-participation',
        };
    }

    public function documentTitle(): string
    {
        return match ($this) {
            self::AttendanceSlip => 'Slip Kehadiran',
            self::ParticipationCertificate => 'Sijil Penyertaan',
        };
    }

    public function bodyIntro(): string
    {
        return match ($this) {
            self::AttendanceSlip => 'telah menyertai program berikut',
            self::ParticipationCertificate => 'telah menyertai program berikut',
        };
    }

    public function legacyTemplateKey(): string
    {
        return match ($this) {
            self::AttendanceSlip => 'legacy-slip',
            self::ParticipationCertificate => 'legacy-certificate',
        };
    }
}
