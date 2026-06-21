<?php

namespace App\Http\Requests;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Fields\CustomFields;
use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'full_name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
            ],
            CustomFields::rules(CustomFieldEntity::Participant, 'public', 'participant_details', $this->event()),
            CustomFields::rules(CustomFieldEntity::Registration, 'public', 'registration_details', $this->event()),
        );
    }

    /**
     * The event being registered for (route-model-bound), used to scope
     * per-event registration custom fields.
     */
    protected function event(): ?Event
    {
        $event = $this->route('event');

        return $event instanceof Event ? $event : null;
    }

    /**
     * Submitted values for the public participant custom fields.
     *
     * @return array<string, mixed>
     */
    public function publicParticipantDetails(): array
    {
        return $this->collectDetails(CustomFieldEntity::Participant, 'participant_details', $this->event());
    }

    /**
     * Submitted values for the public registration custom fields.
     *
     * @return array<string, mixed>
     */
    public function publicRegistrationDetails(): array
    {
        return $this->collectDetails(CustomFieldEntity::Registration, 'registration_details', $this->event());
    }

    /**
     * Collect submitted values for an entity's public fields, limited to defined
     * keys so arbitrary keys can't be injected into the details bag.
     *
     * @return array<string, mixed>
     */
    protected function collectDetails(CustomFieldEntity $entity, string $prefix, ?Event $event = null): array
    {
        $details = [];

        foreach (CustomFields::publicDefinitions($entity, $event) as $field) {
            if ($field->type === CustomFieldType::File) {
                $file = $this->file("{$prefix}.{$field->key}");

                if ($file !== null) {
                    // Private disk — never publicly accessible; admins download
                    // via the auth-gated custom-field-file route.
                    $details[$field->key] = $file->store('custom-fields', 'local');
                }

                continue;
            }

            $value = $this->input("{$prefix}.{$field->key}");

            if ($value !== null && $value !== '') {
                $details[$field->key] = $value;
            }
        }

        return $details;
    }

    /**
     * @return array<string, string|null>
     */
    public function participantData(): array
    {
        return [
            'full_name' => (string) $this->input('full_name'),
            'email' => (string) $this->input('email'),
            'phone' => $this->filled('phone') ? (string) $this->input('phone') : null,
        ];
    }
}
