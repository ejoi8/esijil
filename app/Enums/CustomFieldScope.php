<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a custom field is shown: admin panel only, the public registration form
 * only, or both. (Stored data is always visible in the admin panel regardless.)
 */
enum CustomFieldScope: string implements HasLabel
{
    use HasOptions;

    case Admin = 'admin';
    case PublicForm = 'public';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin only',
            self::PublicForm => 'Public form only',
            self::Both => 'Admin + public form',
        };
    }

    /** Whether the field is collected on the public registration form. */
    public function onPublicForm(): bool
    {
        return $this === self::PublicForm || $this === self::Both;
    }
}
