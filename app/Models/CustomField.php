<?php

namespace App\Models;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldScope;
use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\CustomFieldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-defined custom field. The definition lives here; the values live in
 * the target entity's `details` JSON column, keyed by `key`. Managed from the
 * dashboard via CustomFieldResource and surfaced everywhere by App\Fields\CustomFields.
 */
#[Fillable([
    'organization_id',
    'entity',
    'event_id',
    'key',
    'label',
    'type',
    'options',
    'max_file_kb',
    'accepted_file_types',
    'required',
    'scope',
    'help_text',
    'cert_var',
    'sort',
    'active',
])]
class CustomField extends Model
{
    /** @use HasFactory<CustomFieldFactory> */
    use BelongsToOrganization, HasFactory;

    protected function casts(): array
    {
        return [
            'entity' => CustomFieldEntity::class,
            'event_id' => 'integer',
            'type' => CustomFieldType::class,
            'scope' => CustomFieldScope::class,
            'options' => 'array',
            'max_file_kb' => 'integer',
            'accepted_file_types' => 'array',
            'required' => 'boolean',
            'active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
