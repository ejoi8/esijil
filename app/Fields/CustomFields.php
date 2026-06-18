<?php

namespace App\Fields;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\Event;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Single source of truth for admin-defined custom fields (see the CustomField
 * model). Every surface — admin form/table/infolist, the public registration
 * form, validation, and certificate variables — goes through this accessor, so a
 * field added or removed in the dashboard appears everywhere with no code change.
 * Values are stored in each entity's `details` JSON column, keyed by `key`.
 *
 * Registration fields can be global (event_id null, every event) or scoped to a
 * single event (per-event questions). Pass the $event to merge both, with the
 * per-event field overriding a global one that shares its key.
 */
class CustomFields
{
    /**
     * Active definitions for an entity, ordered for display.
     *
     * @return Collection<int, CustomField>
     */
    public static function definitions(CustomFieldEntity|string $entity, ?Event $event = null): Collection
    {
        $entityValue = $entity instanceof CustomFieldEntity ? $entity->value : $entity;

        $query = CustomField::query()
            ->where('entity', $entityValue)
            ->where('active', true);

        if ($entityValue === CustomFieldEntity::Registration->value && $event !== null) {
            $query->where(fn (Builder $inner): Builder => $inner
                ->whereNull('event_id')
                ->orWhere('event_id', $event->getKey()));
        } else {
            $query->whereNull('event_id');
        }

        $definitions = $query->orderBy('sort')->orderBy('id')->get();

        // Only registration-with-event can yield a global + per-event key clash;
        // the per-event field wins, then we restore display order.
        if ($entityValue !== CustomFieldEntity::Registration->value || $event === null) {
            return $definitions;
        }

        return $definitions
            ->sortByDesc(fn (CustomField $field): bool => $field->event_id !== null)
            ->unique('key')
            ->sortBy('sort')
            ->values();
    }

    /**
     * Definitions collected on the public registration form (scope public/both).
     *
     * @return Collection<int, CustomField>
     */
    public static function publicDefinitions(CustomFieldEntity|string $entity, ?Event $event = null): Collection
    {
        return self::definitions($entity, $event)
            ->filter(fn (CustomField $field): bool => $field->scope->onPublicForm())
            ->values();
    }

    /**
     * Validation rules keyed by "<prefix>.<key>" for the given context. The
     * public form namespaces participant vs registration fields (which both map
     * to a `details` bag but on different models), so the prefix is overridable.
     *
     * @return array<string, list<string>>
     */
    public static function rules(CustomFieldEntity|string $entity, string $context = 'public', string $prefix = 'details', ?Event $event = null): array
    {
        $set = $context === 'public'
            ? self::publicDefinitions($entity, $event)
            : self::definitions($entity, $event);

        $rules = [];

        foreach ($set as $field) {
            $rules["{$prefix}.{$field->key}"] = self::rulesFor($field);
        }

        return $rules;
    }

    /**
     * Validation rules derived from a field's type + required flag.
     *
     * @return list<string>
     */
    public static function rulesFor(CustomField $field): array
    {
        $rules = [$field->required ? 'required' : 'nullable'];

        return array_merge($rules, match ($field->type) {
            CustomFieldType::Textarea => ['string', 'max:2000'],
            CustomFieldType::Number => ['numeric'],
            CustomFieldType::Date => ['date'],
            CustomFieldType::Email => ['string', 'email', 'max:255'],
            CustomFieldType::Select => ['string', 'in:'.implode(',', array_keys($field->options ?? []))],
            CustomFieldType::Checkbox => $field->required ? ['accepted'] : ['boolean'],
            default => ['string', 'max:255'],
        });
    }

    /**
     * Resolve a stored value to its display label (maps select options).
     */
    public static function display(CustomField $field, mixed $value): string
    {
        if ($field->type === CustomFieldType::Checkbox) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : 'Tidak';
        }

        if ($value === null || $value === '') {
            return '';
        }

        if ($field->type === CustomFieldType::Select) {
            return (string) (($field->options ?? [])[$value] ?? $value);
        }

        return (string) $value;
    }

    /**
     * Filament form components for an entity's custom fields.
     *
     * @return array<int, Field>
     */
    public static function formComponents(CustomFieldEntity|string $entity, ?Event $event = null): array
    {
        return self::definitions($entity, $event)
            ->map(function (CustomField $field): Field {
                $name = "details.{$field->key}";

                $component = match ($field->type) {
                    CustomFieldType::Textarea => Textarea::make($name)->columnSpanFull(),
                    CustomFieldType::Select => Select::make($name)->options($field->options ?? []),
                    CustomFieldType::Number => TextInput::make($name)->numeric(),
                    CustomFieldType::Date => DatePicker::make($name),
                    CustomFieldType::Email => TextInput::make($name)->email()->maxLength(255),
                    CustomFieldType::Checkbox => Toggle::make($name),
                    default => TextInput::make($name)->maxLength(255),
                };

                return $component
                    ->label($field->label)
                    ->required($field->required)
                    ->helperText($field->help_text);
            })
            ->all();
    }

    /**
     * Toggleable table columns for an entity's custom fields.
     *
     * @return array<int, TextColumn>
     */
    public static function tableColumns(CustomFieldEntity|string $entity, ?Event $event = null): array
    {
        return self::definitions($entity, $event)
            ->map(fn (CustomField $field): TextColumn => TextColumn::make("details.{$field->key}")
                ->label($field->label)
                ->formatStateUsing(fn (mixed $state): string => self::display($field, $state))
                ->toggleable(isToggledHiddenByDefault: true))
            ->all();
    }

    /**
     * Table filters for an entity's select-type custom fields (filtered on the
     * JSON value — unindexed, fine at this scale).
     *
     * @return array<int, SelectFilter>
     */
    public static function tableFilters(CustomFieldEntity|string $entity, ?Event $event = null): array
    {
        return self::definitions($entity, $event)
            ->filter(fn (CustomField $field): bool => $field->type === CustomFieldType::Select && ! empty($field->options))
            ->map(fn (CustomField $field): SelectFilter => SelectFilter::make("detail_{$field->key}")
                ->label($field->label)
                ->options($field->options ?? [])
                ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                    ? $query->where("details->{$field->key}", $data['value'])
                    : $query))
            ->all();
    }

    /**
     * Infolist entries for an entity's custom fields.
     *
     * @return array<int, TextEntry>
     */
    public static function infolistEntries(CustomFieldEntity|string $entity, ?Event $event = null): array
    {
        return self::definitions($entity, $event)
            ->map(fn (CustomField $field): TextEntry => TextEntry::make("details.{$field->key}")
                ->label($field->label)
                ->formatStateUsing(fn (mixed $state): string => self::display($field, $state))
                ->placeholder('-'))
            ->all();
    }
}
