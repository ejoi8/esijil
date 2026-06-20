<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\RegistrationSource;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id',
    'legacy_id',
    'event_id',
    'participant_id',
    'registered_at',
    'attendance_status',
    'checked_in_at',
    'checked_in_station_id',
    'completed_at',
    'source',
    'remarks',
    'details',
    'certificate_template_id',
    'cert_serial_number',
    'certificate_issued_at',
    'certificate_metadata',
])]
class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'completed_at' => 'datetime',
            'attendance_status' => AttendanceStatus::class,
            'source' => RegistrationSource::class,
            'certificate_issued_at' => 'datetime',
            'certificate_metadata' => 'array',
            'details' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }
}
