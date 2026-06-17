<?php

namespace App\Models;

use App\Enums\CertificateType;
use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

#[Fillable([
    'legacy_id',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'start_time_text',
    'end_time_text',
    'venue',
    'organizer_name',
    'registration_opens_at',
    'registration_closes_at',
    'status',
    'certificate_type',
    'template_key',
    'certificate_template_id',
    'created_by',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'status' => EventStatus::class,
            'certificate_type' => CertificateType::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(Registration::class)
            ->whereNotNull('certificate_type');
    }

    public function registrationLinkExpiresAt(): Carbon
    {
        return ($this->ends_at ?? $this->starts_at ?? now())->copy()->addDay();
    }

    public function publicRegistrationUrl(): string
    {
        return URL::temporarySignedRoute(
            'events.register.show',
            $this->registrationLinkExpiresAt(),
            ['event' => $this],
        );
    }
}
