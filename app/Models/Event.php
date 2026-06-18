<?php

namespace App\Models;

use App\Enums\CertificateRelease;
use App\Enums\CustomFieldEntity;
use App\Enums\EventModule;
use App\Enums\EventStatus;
use App\Enums\ScanMatchMode;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'legacy_id',
    'title',
    'description',
    'details',
    'starts_at',
    'ends_at',
    'start_time_text',
    'end_time_text',
    'venue',
    'organizer_name',
    'registration_open',
    'status',
    'certificate_template_id',
    'modules',
    'scan_match_mode',
    'certificate_release',
    'created_by',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_open' => 'boolean',
            'status' => EventStatus::class,
            'modules' => 'array',
            'scan_match_mode' => ScanMatchMode::class,
            'certificate_release' => CertificateRelease::class,
            'details' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (blank($event->public_id)) {
                $event->public_id = static::generatePublicId();
            }

            if ($event->modules === null) {
                $event->modules = [EventModule::Registration->value, EventModule::Certificate->value];
            }
        });
    }

    /**
     * An opaque, non-sequential token used in the public registration URL in
     * place of the numeric id.
     */
    protected static function generatePublicId(): string
    {
        do {
            $token = Str::random(22);
        } while (static::query()->where('public_id', $token)->exists());

        return $token;
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

    /**
     * Per-event registration custom fields (the questions shown only on this
     * event's registration form). Managed via the Event relation manager.
     */
    public function registrationFields(): HasMany
    {
        return $this->hasMany(CustomField::class)
            ->where('entity', CustomFieldEntity::Registration->value);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(Registration::class)
            ->whereNotNull('certificate_template_id');
    }

    public function hasModule(EventModule $module): bool
    {
        return in_array($module->value, $this->modules ?? [], true);
    }

    /**
     * A signed, non-expiring registration URL. The signature keeps the link
     * unguessable + tamper-proof (events are unlisted); whether registration is
     * actually accepted is gated solely by the `registration_open` toggle.
     */
    public function publicRegistrationUrl(): string
    {
        return URL::signedRoute('events.register.show', ['event' => $this->public_id]);
    }
}
