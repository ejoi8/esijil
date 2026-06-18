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

#[Fillable([
    'organization_id',
    'full_name',
    'email',
    'nokp',
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
