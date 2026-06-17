<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Registered = 'registered';
    case Attended = 'attended';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Registered => 'Registered',
            self::Attended => 'Attended',
            self::NoShow => 'No-show',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $options, self $status): array {
            $options[$status->value] = $status->label();

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
