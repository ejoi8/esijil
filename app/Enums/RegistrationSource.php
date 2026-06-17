<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case PublicForm = 'public_form';
    case Admin = 'admin';
    case LegacyImport = 'legacy_import';

    public function label(): string
    {
        return match ($this) {
            self::PublicForm => 'Public Form',
            self::Admin => 'Admin',
            self::LegacyImport => 'Legacy Import',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $options, self $source): array {
            $options[$source->value] = $source->label();

            return $options;
        }, []);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromMixed(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        return self::tryFrom($value);
    }

    public static function labelFor(mixed $value): string
    {
        return self::fromMixed($value)?->label() ?? (string) $value;
    }
}
