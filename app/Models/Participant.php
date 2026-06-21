<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'public_token',
    'external_id',
    'full_name',
    'email',
    'phone',
    'details',
])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use BelongsToOrganization, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'details' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // An unguessable public identity carried in the QR / certificate / status
        // link, regardless of how the participant entered (registration/CSV/manual).
        static::creating(function (Participant $participant): void {
            if (blank($participant->public_token)) {
                $participant->public_token = (string) Str::ulid();
            }
        });
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(Registration::class)
            ->whereNotNull('certificate_template_id');
    }
}
