<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Registration;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * The record types that can carry admin-defined custom fields. Each maps to an
 * Eloquent model whose `details` JSON column stores the field values.
 */
enum CustomFieldEntity: string implements HasLabel
{
    use HasOptions;

    case Participant = 'participant';
    case Registration = 'registration';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Participant => 'Participant',
            self::Registration => 'Registration',
            self::Event => 'Event',
        };
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Participant => Participant::class,
            self::Registration => Registration::class,
            self::Event => Event::class,
        };
    }
}
