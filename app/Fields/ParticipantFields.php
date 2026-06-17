<?php

namespace App\Fields;

/**
 * Single source of truth for the flexible participant fields defined in
 * config/participant_fields.php. Every surface (admin form/table/infolist,
 * public registration form, validation, certificate variables) iterates this
 * accessor, so a field is added/removed with one config entry. Values live in
 * the participants.details JSON column. See FLEXIBLE_FIELDS.md.
 */
class ParticipantFields
{
    /**
     * Active fields, sorted by their `sort` weight.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $fields = array_filter(
            (array) config('participant_fields', []),
            fn (mixed $field): bool => is_array($field) && ($field['active'] ?? true) === true,
        );

        uasort($fields, fn (array $a, array $b): int => ($a['sort'] ?? 999) <=> ($b['sort'] ?? 999));

        return $fields;
    }

    /**
     * Active fields collected on the public registration form (scope = public).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function publicFields(): array
    {
        return array_filter(self::all(), fn (array $field): bool => ($field['scope'] ?? 'public') === 'public');
    }

    /**
     * Validation rules keyed by "details.<key>".
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(string $scope = 'public'): array
    {
        $set = $scope === 'public' ? self::publicFields() : self::all();
        $rules = [];

        foreach ($set as $key => $field) {
            $fieldRules = $field['rules'] ?? ['nullable'];

            if (($field['type'] ?? null) === 'select' && ! empty($field['options'])) {
                $fieldRules[] = 'in:'.implode(',', array_keys($field['options']));
            }

            $rules["details.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    /**
     * Resolve a stored value to its display label (maps select options).
     * Reads the raw config so soft-removed fields still render existing data.
     */
    public static function display(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $field = config("participant_fields.{$key}");

        if (is_array($field) && ($field['type'] ?? null) === 'select') {
            return (string) ($field['options'][$value] ?? $value);
        }

        return (string) $value;
    }
}
