<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for the backed "label" enums: options/values/fromMixed/labelFor
 * plus getLabel() so the enum satisfies Filament's HasLabel contract (tables,
 * infolists and selects auto-render the label). The using enum supplies only
 * cases() + label().
 */
trait HasOptions
{
    abstract public function label(): string;

    public function getLabel(): ?string
    {
        return $this->label();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromMixed(mixed $value): ?static
    {
        if ($value instanceof static) {
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
