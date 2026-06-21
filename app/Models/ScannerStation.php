<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ScannerStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A per-event scanning device authorized by an unguessable token (no account).
 * The token is embedded in the scanner URL; treat it as a bearer secret.
 */
#[Fillable([
    'organization_id',
    'event_id',
    'token',
    'pin',
    'label',
    'active',
    'expires_at',
])]
class ScannerStation extends Model
{
    /** @use HasFactory<ScannerStationFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'expires_at' => 'datetime',
            'pin' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ScannerStation $station): void {
            if (blank($station->token)) {
                $station->token = Str::random(40);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
