<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'full_name',
    'email',
    'nokp',
    'phone',
    'branch_id',
    'membership_status',
    'membership_notes',
])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'membership_status' => MembershipStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
}
